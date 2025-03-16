<?php

namespace Garagist\Mautic\DataSource;

use Carbon\Newsletter\Service\NodeService;
use Carbon\Newsletter\Service\VariantEmailService;
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
    protected VariantEmailService $variantEmailService;

    #[Flow\Inject]
    protected EmailService $emailService;

    #[Flow\Inject]
    protected ApiService $apiService;

    #[Flow\Inject]
    protected NodeService $nodeService;

    #[Flow\InjectConfiguration('emailAutomatation')]
    protected $emailAutomatation;

    /**
     * Get data
     *
     * @param NodeInterface $node The node that is currently edited (optional)
     * @param array $arguments Additional arguments (key / value)
     * @return array JSON serializable data
     */
    public function getData(NodeInterface $node = null, array $arguments = []): array
    {
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
        $node = $this->nodeService->getLiveNode($node);

        if (!$node || $action == 'publishFirst') {
            if ($this->emailAutomatation['create']) {
                $key = 'publishFirst.create';
            } else if (!$node) {
                $key = 'nodeNotLive';
            } else {
                $key = 'publishFirst';
            }

            return [
                'id' => null,
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => false,
                'message' => 'Carbon.Newsletter:NodeTypes.EmailDataSource:' . $key,
            ];
        }

        if ($action === 'create' || $action === 'update') {
            $email = $this->createOrUpdate($node, $arguments['domain']);
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
            $this->emailService->delete($node);

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

        if (($this->emailAutomatation['create'] && $check['canCreate']) || $this->emailAutomatation['update'] && $check['canUpdate']) {
            $email = $this->createOrUpdate($node, $arguments['domain']);
            return [
                'id' => $email['id'],
                'canDelete' => true,
                'canUpdate' => false,
                'canCreate' => false,
                'idle' => true,
            ];
        }

        if ($check['canUpdate']) {
            $check['message'] = 'Carbon.Newsletter:NodeTypes.EmailDataSource:outdated';
            $check['messageType'] = 'warn';
        }
        if ($check['parentNeedPublishFirst'] ?? false) {
            $check['message'] = 'Carbon.Newsletter:NodeTypes.EmailDataSource:parentNeedPublishFirst';
            $check['messageType'] = 'warn';
        }
        return $check;
    }


    private function createOrUpdate(NodeInterface $node, $domain): array
    {
        $segmentIds = null;
        $excludedSegmentIds = null;
        $category = null;

        $category = $this->variantEmailService->getProperty($node, 'category') ?? null;

        if ($node->getNodeType()->isOfType('Carbon.Newsletter:Document.Newsletter')) {
            $fQ = new FlowQuery([$node]);
            $rootNode = $fQ->closest('[instanceof Carbon.Newsletter:Mixin.Container]')->get(0);
            $newsletterSystemConfig = $rootNode->getProperty('newsletterSystemConfig') ?? [];
            $category = $category ?? $newsletterSystemConfig['categories']['newsletter'] ?? null;
            $segmentsFromNode = $this->variantEmailService->getProperty($node, 'segments') ?? [];
            $segmentIds = count($segmentsFromNode)
                ? $segmentsFromNode
                : $newsletterSystemConfig['newsletterSegment'];
            $excludedSegmentIds = $newsletterSystemConfig['systemSegments']['optInPending'] ?? null;
        }

        return $this->emailService->call(
            $node,
            $domain,
            $category,
            $segmentIds,
            $excludedSegmentIds
        );
    }
}
