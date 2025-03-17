<?php

namespace Garagist\Mautic\Service;

use Carbon\Newsletter\Service\NodeService;
use Carbon\Newsletter\Service\PersonalizationService;
use Carbon\Newsletter\Service\UtmTagsService;
use Carbon\Newsletter\Service\VariantEmailService;
use Carbon\Newsletter\Utils;
use Garagist\Mautic\Service\ApiService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use DateTime;

#[Flow\Scope('singleton')]
class EmailService
{
    const NEOS_DATA_TOKEN = 'NeosData';
    const MAUTIC_EMAIL_DATE_FORMAT = 'Y-m-d H:i:s';

    #[Flow\Inject]
    protected ApiService $apiService;

    #[Flow\Inject]
    protected NodeService $nodeService;

    #[Flow\Inject]
    protected PersonalizationService $personalizationService;

    #[Flow\Inject]
    protected UtmTagsService $utmTagsService;

    #[Flow\Inject]
    protected VariantEmailService $variantEmailService;

    #[Flow\InjectConfiguration('emailAutomatation')]
    protected $emailAutomatation;

    /**
     * Check the status of the email node
     */
    public function emailCheck(NodeInterface $node): array
    {
        // Check if the parent is published (if it is a variant email)
        if ($this->variantEmailService->isVariantEmail($node)) {
            $parentNodeIsPublished = false;
            try {
                $parentNodeIsPublished = !!$this->getEmail($node->findParentNode());
            } catch (\Exception $e) {
                // Do nothing
            }
            if (!$parentNodeIsPublished) {
                return [
                    'id' => null,
                    'canDelete' => false,
                    'canUpdate' => false,
                    'canCreate' => false,
                    'idle' => false,
                    'parentNeedPublishFirst' => true,
                ];
            }
        }

        // Check if the email exists
        $email = $this->getEmail($node);
        if (!isset($email)) {
            return [
                'id' => null,
                'canDelete' => false,
                'canUpdate' => false,
                'canCreate' => true,
                'idle' => false,
            ];
        }

        // Get the lastest publication date of the document and content
        $fQ = new FlowQuery([$node]);
        $newestNode = $fQ
            ->children('[instanceof Neos.Neos:ContentCollection]')
            ->find('[instanceof Neos.Neos:Content]')
            ->add($node)
            ->sort('_lastPublicationDateTime', 'DESC')
            ->get(0);
        $lastNodePublication = $newestNode->getLastPublicationDateTime()->getTimestamp();

        // On variant email the dateModified will not get updated, so we use the DynamicContent token
        $lastEmailModification = strtotime($this->getNeosData($email, 'DateModified'));

        // Compare the dates
        $canUpdate = $lastNodePublication > $lastEmailModification;

        return [
            'id' => $email['id'],
            'canDelete' => true,
            'canUpdate' => $canUpdate,
            'canCreate' => false,
            'idle' => !$canUpdate,
        ];
    }

    public function afterNodePublishing(NodeInterface $node)
    {
        if (!$node->getNodeType()->isOfType('Carbon.Newsletter:Mixin.Email')) {
            return;
        }

        if ($this->emailAutomatation['remove'] && $node->isRemoved()) {
            $this->delete($node);
        }
    }

    /**
     * Adjust global sender name if changed on the container
     */
    public function nodePropertyChanged(NodeInterface $node, string $propertyName, mixed $oldValue, mixed $value): void
    {
        if ($propertyName !== 'globalSenderName' || !$node->getNodeType()->isOfType('Carbon.Newsletter:Mixin.Container')) {
            return;
        }
        $fQ = new FlowQuery([$node]);
        $nodes = $fQ->find('[instanceof Carbon.Newsletter:Mixin.Email]')->get();
        foreach ($nodes as $node) {
            $node->setProperty($propertyName, $value);
        }
    }

    /**
     * Delete email
     */
    public function delete(NodeInterface $node): void
    {
        $email = $this->getEmail($node);
        if ($email) {
            $this->apiService->delete(ApiService::ENDPOINT_EMAILS, $email['id']);
        }
    }

    /**
     * Create or edit email
     *
     * @param NodeInterface $node
     * @param string $domain
     * @param integer|null $category
     * @param int[]|int|null $segmentIds
     * @param int[]|int|null $excludedSegmentIds
     * @return array|null
     */
    public function call(
        NodeInterface $node,
        string $domain,
        ?int $category = null,
        array|int|null $segmentIds = null,
        array|int|null $excludedSegmentIds = null,
    ): ?array {
        $email = $this->getEmail($node);

        $preheaderText = $node->getProperty('previewText') ?: '';
        $emailType = isset($segmentIds) ? 'list' : 'template';

        $name = $node->getProperty('title');
        $subject = $node->getProperty('subject') ?: $name;

        $data = [
            'name' => $name,
            'subject' => $this->personalizationService->mail($subject),
            'preheaderText' => $this->personalizationService->mail($preheaderText),
            'plainText' => Utils::contentsFromUrl($this->nodeService->getNodeUri($node, $domain, 'plaintext')),
            'customHtml' => Utils::contentsFromUrl($this->nodeService->getNodeUri($node, $domain, 'email')),
            'template' => 'mautic_code_mode',
            'emailType' => $emailType,
            'isPublished' => !$node->isHidden(),
            'language' => $this->nodeService->getLanguage($node),
            'utmTags' => $this->utmTagsService->getUtmTags($node),
            'fromName' => $node->getProperty('senderName') ?: $node->getProperty('globalSenderName') ?: null,
        ];

        if (isset($category)) {
            $data['category'] = $category;
        }

        $dynamicContentData = [
            'NodeIdentifier' => $this->getNodeIdentifier($node),
            'DateModified' => (new DateTime())->format(self::MAUTIC_EMAIL_DATE_FORMAT),
        ];

        $isVariantEmail = $this->variantEmailService->isVariantEmail($node);
        if ($isVariantEmail) {
            $parentNode = $this->variantEmailService->getParentEmailNode($node);
            $parentEmail = $this->getEmail($parentNode);
            if (!$parentEmail) {
                return null;
            }
            $data['variantParent'] = $parentEmail['id'];
            $data['variantSettings'] = [
                'weight' => $node->getProperty('variantSettingsWeight'),
                'winnerCriteria' => $node->getProperty('variantSettingsWinnerCriteria'),
            ];
        }
        if ($emailType === 'list') {
            $publish = $isVariantEmail ? [] : $this->generatePublishDateRange($node);
            $lists = is_array($segmentIds) ? $segmentIds : [$segmentIds];
            $excludedLists =
                !isset($excludedSegmentIds) || is_array($excludedSegmentIds)
                    ? $excludedSegmentIds
                    : [$excludedSegmentIds];
            $data = array_filter(array_merge($data, $publish, [
                'lists' => $lists,
                'excludedLists' => $excludedLists ?? [],
            ]));
        }

        $data['dynamicContent'] = $this->generateNeosData($dynamicContentData, $email);

        if (isset($email)) {
            $email = $this->apiService->edit(ApiService::ENDPOINT_EMAILS, $email['id'], $data)['email'];
        } else {
            $email = $this->apiService->create(ApiService::ENDPOINT_EMAILS, $data)['email'];
        }

        if ($isVariantEmail) {
            $variantChildren = [$email['id']];
            $parentEmailVariantChildren = $parentEmail['variantChildren'] ?? [];
            foreach ($parentEmailVariantChildren as $value) {
                if ($value['id'] !== $email['id']) {
                    $variantChildren[] = $value['id'];
                }
            }

            // We have to set dynamicContent, otherwise it will be overriden
            $parentData = [
                'variantChildren' => $variantChildren,
                'dynamicContent' => $parentEmail['dynamicContent'],
            ];

            $this->apiService->edit(ApiService::ENDPOINT_EMAILS, $parentEmail['id'], $parentData);
        }

        return $email;
    }

    private function getNodeIdentifier(NodeInterface $node): string
    {
        /** @var TraversableNodeInterface $node */
        return $node->getNodeAggregateIdentifier();
    }

    /**
     * Get email by node
     *
     * @param NodeInterface $node
     * @return array|null
     */
    private function getEmail(NodeInterface $node): ?array
    {
        $emails = $this->apiService->getList(ApiService::ENDPOINT_EMAILS);
        $nodeIdentifier = $this->getNodeIdentifier($node);

        $variants = [];

        foreach ($emails['emails'] as $email) {
            $result = $this->compareNodeIdentifier($email, $nodeIdentifier);
            if ($result) {
                return $result;
            }
            if (isset($email['variantChildren']) && count($email['variantChildren'])) {
                $variants = array_merge($variants, $email['variantChildren']);
            }
        }
        $variantId = null;
        foreach ($variants as $variant) {
            $result = $this->compareNodeIdentifier($variant, $nodeIdentifier);
            if ($result) {
                $variantId = $result['id'];
                break;
            }
        }
        if (isset($variantId)) {
            return $this->apiService->makeCall([ApiService::ENDPOINT_EMAILS, $variantId])['email'];
        }

        return null;
    }

    /**
     * Return the email if the NodeIdentifier matches in the dynamicContent field
     *
     * @param array $email
     * @param string $nodeIdentifier
     * @return array|null
     */
    private function compareNodeIdentifier(array $email, string $nodeIdentifier): ?array
    {
        if ($this->getNeosData($email, 'NodeIdentifier') === $nodeIdentifier) {
            return $email;
        }
        return null;
    }

    /**
     * Return Neos data in the dynamic Content field as JSON
     *
     * @param array $data
     * @param array|null $email
     * @param string|null $key
     * @return array
     */
    // private function generateNeosData(array $data, ?array $email = null, ?string $key = null): array
    private function generateNeosData(array $data, ?array $email = null): array
    {
        $data['Label'] = 'Do not change or remove this field';
        $dataAsString = json_encode($data);
        $dynamicContent = $email['dynamicContent'] ?? [];
        $hasToken = false;

        if (count($dynamicContent)) {
            foreach ($dynamicContent as $key => $value) {
                if ($value['tokenName'] === self::NEOS_DATA_TOKEN) {
                    $hasToken = true;
                    $dynamicContent[$key]['content'] = $dataAsString;
                    break;
                }
            }
        }

        if (!$hasToken) {
            $dynamicContent[] = [
                'tokenName' => self::NEOS_DATA_TOKEN,
                'content' => $dataAsString,
            ];
        }

        return $dynamicContent;
    }

    /**
     * Get Neos data from the dynamic Content field
     */
    private function getNeosData(array $email, ?string $key = null): mixed
    {
        $dynamicContent = $email['dynamicContent'];
        foreach ($dynamicContent as $value) {
            if ($value['tokenName'] === self::NEOS_DATA_TOKEN) {
                $data = json_decode($value['content'], true);
                if (isset($key)) {
                    return $data[$key] ?? null;
                }
                return $data;
            }
        }
        return null;
    }

    /**
     * Generate the publish date range
     */
    private function generatePublishDateRange(NodeInterface $node): array
    {
        $publishUp = $node->getProperty('publishDate');
        $publishDown = null;
        if ($publishUp) {
            $publishDown = clone $publishUp;
            $publishDateRange = $node->getProperty('publishDateRange');
            $amount = $publishDateRange['amount'] ?? null;
            $unit = $publishDateRange['unit'] ?? 'day';
            $publishUp = $publishUp->format(self::MAUTIC_EMAIL_DATE_FORMAT);
            if ($amount) {
                $publishDown->modify(sprintf('+%s %s%s', $amount, $unit, $amount > 1 ? 's' : ''));
                $publishDown = $publishDown->format(self::MAUTIC_EMAIL_DATE_FORMAT);
            } elseif ($amount === 0) {
                // If the amount is 0 we take the unit as the amount
                // Example 0 hour == Till the next hour // 0 day == Till the next day
                switch ($unit) {
                    case 'minute':
                        $publishDown->modify('+1 minute');
                        break;
                    case 'hour':
                        // The next full hour
                        $publishDown->modify('+1 hour');
                        $hours = $publishDown->format('H');
                        $publishDown->modify($hours . ':00');
                        break;
                    case 'week':
                        $publishDown->modify('next monday');
                        $publishDown->modify('midnight');
                        break;
                    case 'month':
                        $publishDown->modify('+1 month');
                        $publishDown->modify('01.' . $publishDown->format('m.Y') . '00:00');
                        break;
                    default:
                        // We take day as default
                        $publishDown->modify('midnight');
                        $publishDown->modify('+1 day');
                        break;
                }

                $publishDown = $publishDown->format(self::MAUTIC_EMAIL_DATE_FORMAT);
            } else {
                $publishDown = null;
            }
        }

        return [
            'publishUp' => $publishUp,
            'publishDown' => $publishDown,
        ];
    }
}
