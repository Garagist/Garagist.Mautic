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
                'id' => null,
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => false,
                'message' => 'Carbon.Newsletter:NodeTypes.EmailDataSource:nodeNotLive',
                'messageType' => 'warn',
            ];
        }

        $ping = $this->apiService->ping();
        if (!$ping) {
            sleep(8);
            return [
                'id' => null,
                'offline' => true,
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => false,
                'message' => 'Carbon.Newsletter:NodeTypes.EmailDataSource:isOffline',
                'messageType' => 'error',
            ];
        }

        $action = $arguments['action'] ?? null;
        if ($action === 'create' || $action === 'update') {
            $segmentIds = null;
            $excludedSegmentIds = null;
            $category = null;

            $category = $node->getProperty('category') ?? null;

            if ($node->getNodeType()->isOfType('Carbon.Newsletter:Document.Newsletter')) {
                $rootNode = $fQ->closest('[instanceof Carbon.Newsletter:Mixin.Container]')->get(0);
                $newsletterSystemConfig = $rootNode->getProperty('newsletterSystemConfig') ?? [];
                $category = $category ?? $newsletterSystemConfig['categories']['newsletter'] ?? null;
                $segmentsFromNode = $node->getProperty('segments') ?? [];
                $segmentIds = count($segmentsFromNode)
                    ? $segmentsFromNode
                    : $newsletterSystemConfig['newsletterSegment'];
                $excludedSegmentIds = $newsletterSystemConfig['systemSegments']['optInPending'] ?? null;
            }

            $email = $this->emailService->call(
                $arguments['domain'],
                $node,
                $category,
                segmentIds: $segmentIds,
                excludedSegmentIds: $excludedSegmentIds
            );
            $id = $email['id'];

            return [
                'id' => $id,
                'idle' => true,
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => true,
            ];
        }

        if ($action === 'delete') {
            $this->emailService->call($arguments['domain'], $node, mode: 'delete');

            return [
                'id' => null,
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
