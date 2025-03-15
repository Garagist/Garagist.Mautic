<?php

namespace Garagist\Mautic\Service;

use Carbon\Newsletter\Service\NodeService;
use Carbon\Newsletter\Service\PersonalizationService;
use Carbon\Newsletter\Service\UtmTagsService;
use Carbon\Newsletter\Utils;
use Garagist\Mautic\Service\ApiService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;

#[Flow\Scope('singleton')]
class EmailService
{
    #[Flow\Inject]
    protected ApiService $apiService;

    #[Flow\Inject]
    protected NodeService $nodeService;

    #[Flow\Inject]
    protected PersonalizationService $personalizationService;

    #[Flow\Inject]
    protected UtmTagsService $utmTagsService;

    #[Flow\InjectConfiguration('removeEmailIfNodeRemoved')]
    protected $removeEmailIfNodeRemoved;

    /**
     * Check email
     *
     * @param NodeInterface $node
     * @return array
     */
    public function emailCheck(NodeInterface $node): array
    {
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

        $fQ = new FlowQuery([$node]);
        $newestNode = $fQ
            ->children('[instanceof Neos.Neos:ContentCollection]')
            ->find('[instanceof Neos.Neos:Content]')
            ->add($node)
            ->sort('_lastPublicationDateTime', 'DESC')
            ->get(0);
        $lastNodePublication = $newestNode->getLastPublicationDateTime()->getTimestamp();
        $lastEmailModification = strtotime($email['dateModified']);
        $canUpdate = $lastNodePublication > $lastEmailModification;

        return [
            'id' => $email['id'],
            'canDelete' => true,
            'canUpdate' => $canUpdate,
            'canCreate' => false,
            'idle' => !$canUpdate,
        ];
    }

    public function nodeRemoved(NodeInterface $node): void
    {
        if (!$this->removeEmailIfNodeRemoved) {
           return;
        }
        $email = $this->getEmail($node);
        if ($email) {
            $this->apiService->delete('emails', $email['id']);
        }
    }

    public function nodePropertyChanged(NodeInterface $node, $propertyName, $oldValue, $value): void
    {
        if ($propertyName !== 'globalSenderName' || !$node->getNodeType()->isOfType('Carbon.Newsletter:Mixin.Container')) {
            return;
        }
        $fQ = new FlowQuery([$node]);
        $nodes = $fQ->find('[instanceof Carbon.Newsletter:Mixin.Email]')->get();
        foreach ($nodes as $node) {
            $node->setProperty('globalSenderName', $value);
        }
    }

    /**
     * Create or edit email
     *
     * @param string $domain
     * @param NodeInterface|null $node
     * @param integer|null $category
     * @param string|null $from
     * @param string|null $mode 'create' / 'edit' / delete. If null, it will be created if not exists, otherwise edited.
     * @param int[]|int|null $segmentIds
     * @param int[]|int|null $excludedSegmentIds
     * @return array|null
     */
    public function call(
        string $domain,
        ?NodeInterface $node = null,
        ?int $category = null,
        ?string $from = null,
        ?string $mode = null,
        array|int|null $segmentIds = null,
        array|int|null $excludedSegmentIds = null
    ): ?array {
        if (!$node) {
            return null;
        }

        $email = $this->getEmail($node);

        if ($mode === 'delete') {
            if ($email) {
                $this->apiService->delete('emails', $email['id']);
            }
            return null;
        }

        if ($mode === 'edit' && !isset($email)) {
            return null;
        }
        if ($mode === 'create' && isset($email)) {
            return null;
        }

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
            'isPublished' => 1,
            'language' => $this->nodeService->getLanguage($node),
            'utmTags' => $this->utmTagsService->getUtmTags($node),
            'dynamicContent' => [
                [
                    'tokenName' => 'NodeIdentifier',
                    'content' => $this->getNodeIdentifier($node),
                ],
            ],
        ];

        if ($emailType === 'list') {
            $publish = $this->getPublishDateRange($node);
            $lists = is_array($segmentIds) ? $segmentIds : [$segmentIds];
            $excludedLists =
                !isset($excludedSegmentIds) || is_array($excludedSegmentIds)
                    ? $excludedSegmentIds
                    : [$excludedSegmentIds];
            $data = array_merge($data, $publish, [
                'lists' => $lists,
                'excludedLists' => $excludedLists ?? [],
            ]);
        }

        if (isset($from)) {
            $data['fromName'] = $from;
        } else {
            $data['fromName'] = $node->getProperty('senderName') ?: $node->getProperty('globalSenderName') ?: null;
        }
        if (isset($category)) {
            $data['category'] = $category;
        }

        if (isset($email)) {
            return $this->apiService->edit('emails', $email['id'], $data)['email'];
        }

        return $this->apiService->create('emails', $data)['email'];
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

        foreach ($emails['emails'] as $email) {
            $dynamicContent = $email['dynamicContent'];
            foreach ($dynamicContent as $value) {
                if ($value['tokenName'] === 'NodeIdentifier' && $value['content'] === $nodeIdentifier) {
                    return $email;
                }
            }
        }
        return null;
    }

    private function getPublishDateRange(NodeInterface $node): array
    {
        $publishUp = $node->getProperty('publishDate');
        $publishDown = null;
        $format = 'Y-m-d H:i';
        if ($publishUp) {
            $publishDown = clone $publishUp;
            $publishDateRange = $node->getProperty('publishDateRange');
            $amount = $publishDateRange['amount'] ?? null;
            $unit = $publishDateRange['unit'] ?? 'day';
            $publishUp = $publishUp->format($format);
            if ($amount) {
                $publishDown->modify(sprintf('+%s %s%s', $amount, $unit, $amount > 1 ? 's' : ''));
                $publishDown = $publishDown->format($format);
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

                $publishDown = $publishDown->format($format);
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
