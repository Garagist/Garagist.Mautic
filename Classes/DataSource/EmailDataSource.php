<?php

namespace Garagist\Mautic\DataSource;

use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\EmailService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\DataSource\AbstractDataSource;

class EmailDataSource extends AbstractDataSource
{
    /**
     * @var string
     */
    protected static $identifier = 'garagist-mautic-email';

    #[Flow\Inject]
    protected EmailService $emailService;

    #[Flow\Inject]
    protected ApiService $apiService;

    /**
     * Get data
     *
     * @param NodeInterface $node The node that is currently edited (optional)
     * @param array $arguments Additional arguments (key / value)
     * @return array JSON serializable data
     */
    public function getData(NodeInterface $node = null, array $arguments = []): array
    {
        $fQ = new FlowQuery([$node]);
        $node = $fQ->context(['workspaceName' => 'live'])->get(0);

        if (!$node) {
            return [
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => false,
                'message' => 'Carbon.Newsletter:NodeTypes.EmailDataSource:nodeNotLive',
                'messageType' => 'warn',
            ];
        }

        $ping = $this->apiService->ping();
        if (!$ping) {
            return [
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => false,
                'message' => 'Carbon.Newsletter:NodeTypes.EmailDataSource:isOffline',
                'messageType' => 'error',
            ];
        }

        $action = $arguments['action'] ?? null;
        $segmentEmail = $arguments['segmentEmail'] ?? null;
        $segmentEmail = $segmentEmail == 'true' ? true : false;

        if ($action === 'create' || $action === 'update') {
            $email = $this->emailService->call($segmentEmail, $arguments['domain'], $node, allowSave: false);
            $returnValue = [
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => true,
                'message' => 'Carbon.Newsletter:NodeTypes.EmailDataSource:done.' . $action,
                'messageType' => 'success',
            ];
            if ($action === 'create') {
                $returnValue['value'] = $email['id'];
            }

            return $returnValue;
        }

        if ($action === 'delete') {
            $this->emailService->call($segmentEmail, $arguments['domain'], $node, mode: 'delete', allowSave: false);
            return [
                'value' => '',
                'canCreate' => true,
                'canUpdate' => false,
                'canDelete' => false,
                'message' => 'Carbon.Newsletter:NodeTypes.EmailDataSource:done.delete',
                'messageType' => 'dark',
            ];
        }

        $check = $this->emailService->emailCheck($node);
        if ($check['canUpdate']) {
            $check['message'] = 'Carbon.Newsletter:NodeTypes.EmailDataSource:outdated';
            $check['messageType'] = 'warn';
        }
        return $check;
    }
}
