<?php

namespace Garagist\Mautic\Service;

use Carbon\Newsletter\Service\NodeService;
use Carbon\Newsletter\Service\PersonalizationService;
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
        $newestNode = $fQ->children('[instanceof Neos.Neos:ContentCollection]')->find('[instanceof Neos.Neos:Content]')->add($node)->sort('_lastPublicationDateTime', 'DESC')->get(0);
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

    /**
     * Create or edit email
     *
     * @param string $domain
     * @param NodeInterface|null $node
     * @param integer|null $category
     * @param string|null $from
     * @param string|null $mode 'create' / 'edit' / delete. If null, it will be created if not exists, otherwise edited.
     * @param int[]|null $segmentIds
     * @param int[]|null $excludedSegmentIds
     * @param bool $allowSave
     * @return array|null
     */
    public function call(
        string $domain,
        ?NodeInterface $node = null,
        ?int $category = null,
        ?string $from = null,
        ?string $mode = null,
        ?array $segmentIds = null,
        ?array $excludedSegmentIds = null
    ): ?array {
        if (!$node) {
            return null;
        }

        $email = $this->getEmail($node);

        if ($mode === 'delete') {
            //$this->emailRepoService->delete($node);
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

        $data = [
            'name' => $node->getProperty('name'),
            'subject' => $this->personalizationService->mail($node->getProperty('title')),
            'preheaderText' => $this->personalizationService->mail($preheaderText),
            'plainText' => Utils::contentsFromUrl($this->nodeService->getNodeUri($node, $domain, 'plaintext')),
            'customHtml' => Utils::contentsFromUrl($this->nodeService->getNodeUri($node, $domain, 'email')),
            'template' => 'mautic_code_mode',
            'emailType' => $emailType,
            'isPublished' => 1,
            'language' => $this->nodeService->getLanguage($node),
            'dynamicContent' => [
                [
                    'tokenName' => 'NodeIdentifier',
                    'content' => $this->getNodeIdentifier($node),
                ],
            ]
        ];

        if ($emailType === 'list') {
            $data['lists'] = $segmentIds;
            $data['listsExcluded'] = $excludedSegmentIds ?? [];
        }

        if (isset($from)) {
            $data['fromName'] = $from;
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
}
