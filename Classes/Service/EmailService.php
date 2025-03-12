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
                'canDelete' => false,
                'canUpdate' => false,
                'canCreate' => true
            ];
        }

        $fQ = new FlowQuery([$node]);
        $newestNode = $fQ->children('[instanceof Neos.Neos:ContentCollection]')->find('[instanceof Neos.Neos:Content]')->add($node)->sort('_lastPublicationDateTime', 'DESC')->get(0);

        $lastNodePublication = $newestNode->getLastPublicationDateTime()->getTimestamp();
        $lastEmailModification = strtotime($email['dateModified']);
        $canUpdate = $lastNodePublication > $lastEmailModification;

        return [
            'canDelete' => true,
            'canUpdate' => $canUpdate,
            'canCreate' => false
        ];
    }

    /**
     * Create or edit email
     *
     * @param bool $segmentEmail
     * @param string $domain
     * @param NodeInterface|null $node
     * @param integer|null $category
     * @param string|null $from
     * @param string|null $mode 'create' / 'edit' / delete. If null, it will be created if not exists, otherwise edited.
     * @param bool $allowSave
     * @return array|null
     */
    public function call(
        bool $segmentEmail,
        string $domain,
        ?NodeInterface $node = null,
        ?int $category = null,
        ?string $from = null,
        ?string $mode = null,
        bool $allowSave = true
    ): ?array {
        if (!$node) {
            return null;
        }

        $email = $this->getEmail($node);

        if ($mode === 'delete') {
            if ($email) {
                $this->apiService->delete('emails', $email['id']);
            }
            if ($allowSave) {
                $node = $node->setProperty('id', null);
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

        $data = [
            'name' => $node->getProperty('name'),
            'subject' => $this->personalizationService->mail($node->getProperty('title')),
            'preheaderText' => $this->personalizationService->mail($preheaderText),
            'plainText' => Utils::contentsFromUrl($this->nodeService->getNodeUri($node, $domain, 'plaintext')),
            'customHtml' => Utils::contentsFromUrl($this->nodeService->getNodeUri($node, $domain, 'email')),
            'template' => 'mautic_code_mode',
            'emailType' => $segmentEmail ? 'list' : 'template',
            'isPublished' => 1,
            'language' => $this->nodeService->getLanguage($node),
        ];

        if (isset($from)) {
            $data['fromName'] = $from;
        }
        if (isset($category)) {
            $data['category'] = $category;
        }

        if (isset($email)) {
            return $this->apiService->edit('emails', $email['id'], $data)['email'];
        }

        $email = $this->apiService->create('emails', $data)['email'];
        if ($allowSave) {
            $node->setProperty('id', $email['id']);
            sleep(1);
            // Edit it again to get the correct dateModified
            $email = $this->apiService->edit('emails', $email['id'], $data)['email'];
        }
        return $email;
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
        $id = $node->getProperty('id') ?? null;
        if (!isset($id)) {
            return null;
        }
        foreach ($emails['emails'] as $email) {
            if (isset($email['id']) && $email['id'] == $id) {
                return $email;
            }
        }
        return null;
    }
}
