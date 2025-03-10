<?php

namespace Garagist\Mautic\Service;

use Carbon\Newsletter\Service\NodeService;
use Carbon\Newsletter\Utils;
use Carbon\Newsletter\Service\PersonalizationService;
use Garagist\Mautic\Service\ApiService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
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
     * Create or edit email
     *
     * @param string $domain
     * @param string $language
     * @param NodeInterface|null $node
     * @param integer|null $category
     * @param string|null $from
     * @param string|null $mode 'create' / 'edit'. If null, it will be created if not exists, otherwise edited.
     * @return array|null
     */
    public function call(
        string $domain,
        string $language,
        ?NodeInterface $node = null,
        ?int $category = null,
        ?string $from = null,
        ?string $mode = null
    ): ?array {
        if (!$node) {
            return null;
        }
        $emails = $this->apiService->getList(ApiService::ENDPOINT_EMAILS);
        $name = $node->getProperty('name');
        $emailId = null;
        foreach ($emails['emails'] as $email) {
            if (isset($email['name']) && $email['name'] == $name) {
                $emailId = $email['id'];
            }
        }

        if ($mode === 'edit' && !isset($emailId)) {
            return null;
        }
        if ($mode === 'create' && isset($emailId)) {
            return null;
        }

        $preheaderText = $node->getProperty('previewText') ?: '';

        $data = [
            'name' => $name,
            'subject' => $this->personalizationService->mail($node->getProperty('title')),
            'preheaderText' => $this->personalizationService->mail($preheaderText),
            'plainText' => Utils::contentsFromUrl($this->nodeService->getNodeUri($node, $domain, 'plaintext')),
            'customHtml' => Utils::contentsFromUrl($this->nodeService->getNodeUri($node, $domain, 'email')),
            'template' => 'mautic_code_mode',
            'emailType' => 'template',
            'isPublished' => 1,
        ];

        if (isset($from)) {
            $data['fromName'] = $from;
        }
        if (isset($category)) {
            $data['category'] = $category;
        }
        if (isset($language)) {
            $data['language'] = $language;
        }

        if (isset($emailId)) {
            return $this->apiService->edit('emails', $emailId, $data)['email'];
        }

        return $this->apiService->create('emails', $data)['email'];
    }
}
