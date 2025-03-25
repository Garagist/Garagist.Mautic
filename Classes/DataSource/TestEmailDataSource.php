<?php

namespace Garagist\Mautic\DataSource;

use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\EmailService;
use Carbon\Newsletter\Service\TestEmailService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\DataSource\AbstractDataSource;

class TestEmailDataSource extends AbstractDataSource
{
    /**
     * @var string
     */
    protected static $identifier = 'garagist-mautic-test-email';

    #[Flow\Inject]
    protected TestEmailService $testEmailService;

    #[Flow\Inject]
    protected ApiService $apiService;

    #[Flow\Inject]
    protected EmailService $emailService;

    /**
     * Get data
     *
     * @param NodeInterface $node The node that is currently edited (optional)
     * @param array $arguments Additional arguments (key / value)
     * @return array JSON serializable data
     */
    public function getData(NodeInterface $node = null, array $arguments = []): array
    {
        $action = $arguments['action'] ?? null;
        if (!$node || !$action) {
            return [];
        }

        switch ($action) {
            case 'recipients':
                return $this->testEmailService->getRecipients($node);
            case 'contact':
                return $this->getContacts($arguments['contact']);
            case 'send':
                return $this->sendTestEmail($node, $arguments);
            default:
                return [];
        }
    }

    private function sendTestEmail(NodeInterface $node, array $arguments): array
    {
        if (!isset($arguments['recipients']) || !isset($arguments['contactId'])) {
            return [
                'message' => 'Carbon.Newsletter:NodeTypes.TestEmail.error.missingParameter',
                'messageType' => 'error'
            ];
        }

        $result = $this->emailService->sendTestEmail($node, $arguments['recipients'], $arguments['contactId']);
        if ($result['error'] ?? false) {
            if (in_array($result['error'], ['send', 'notPublished'])) {
                $result['error'] = sprintf('Carbon.Newsletter:NodeTypes.TestEmail.error.%s', $result['error']);
            }

            return [
                'message' => $result['error'],
                'messageType' => 'error'
            ];
        }

        return [
            'message' => 'Carbon.Newsletter:NodeTypes.TestEmail.success',
            'messageType' => 'success'
        ];
    }

    private function getContacts(string $search): ?array
    {
        $contacts = $this->apiService->getList(ApiService::ENDPOINT_CONTACTS, $search, limit: 10, publishedOnly: true);
        $result = [];

        foreach ($contacts['contacts'] as $contact) {
            $firstname = $contact['fields']['all']['firstname'] ?? '';
            $lastname = $contact['fields']['all']['lastname'] ?? '';
            $label = sprintf('%s %s', $firstname, $lastname);
            $result[trim($label)] = $contact['id'];
        }

        if (empty($result)) {
            return null;
        }

        return $result;
    }
}
