<?php

namespace Garagist\Mautic\DataSource;

use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\EmailService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\DataSource\AbstractDataSource;

class UpdateDataSource extends AbstractDataSource
{
    /**
     * @var string
     */
    protected static $identifier = 'garagist-mautic-update';

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
                'message' => 'Carbon.Newsletter:NodeTypes.Mixin.Name:nodeNotLive',
                'messageType' => 'warn',
            ];
        }

        $ping = $this->apiService->ping();
        if (!$ping) {
            return [
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => false,
                'message' => 'Garagist.Mautic:NodeTypes.Override.Newsletter:mauticIsOffline',
                'messageType' => 'error',
            ];
        }

        $action = $arguments['action'] ?? null;

        if ($action === 'create' || $action === 'update') {
            $this->emailService->call($arguments['domain'], $node);

            return [
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => true,
                'message' => 'Carbon.Newsletter:NodeTypes.Mixin.Name:done.' . $action,
                'messageType' => 'success',
            ];
        }

        if ($action === 'delete') {
            $this->emailService->call($arguments['domain'], $node, mode: 'delete');
            return [
                'canCreate' => true,
                'canUpdate' => false,
                'canDelete' => false,
                'message' => 'Carbon.Newsletter:NodeTypes.Mixin.Name:done.delete',
                'messageType' => 'dark',
            ];
        }

        $check = $this->emailService->emailCheck($node);
        if ($check['canUpdate']) {
            $check['message'] = 'Carbon.Newsletter:NodeTypes.Mixin.Name:outdated';
            $check['messageType'] = 'warn';
        }
        return $check;
    }
}
