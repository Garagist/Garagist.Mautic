<?php

namespace Garagist\Mautic\DataSource;

use Carbon\Newsletter\Service\NodeService;
use Carbon\Newsletter\Service\VariantEmailService;
use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\EmailService;
use Garagist\Mautic\Service\SettingsService;
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

    #[Flow\Inject]
    protected SettingsService $settingsService;

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
        $apiSettings = $this->settingsService->getFromNodeOrConfig($node);
        $ping = $this->apiService->ping($apiSettings);
        if (!$ping) {
            sleep(8);
            return [
                'id' => null,
                'offline' => true,
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => false,
                'message' => $this->getMessage('isOffline'),
                'messageType' => 'error',
            ];
        }

        $action = $arguments['action'] ?? null;
        $liveNode = $this->nodeService->getLiveNode($node);
        if (!$liveNode || $action == 'publishFirst') {
            $returnValue = [
                'id' => null,
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => false,
            ];

            if ($this->emailAutomatation['create'] || $this->emailAutomatation['update']) {
                $check = $this->emailService->emailCheck($node, $apiSettings);
                $key = $check['canCreate'] ? 'publishFirst.create' : 'publishFirst.update';
            } elseif ($liveNode) {
                $key = 'nodeNotLive';
            } else {
                $key = 'publishFirst';
            }
            $returnValue['message'] = $this->getMessage($key);
            return $returnValue;
        }

        if ($action === 'delete') {
            $this->emailService->delete($node, $apiSettings);

            return [
                'id' => null,
                'canCreate' => true,
                'canUpdate' => false,
                'canDelete' => false,
                'message' => $this->getMessage('done.delete'),
                'messageType' => 'dark',
            ];
        }

        $canDelete = !$this->emailAutomatation['create'];
        if ($action === 'create' || $action === 'update') {
            $email = $this->createOrUpdate($liveNode, $arguments['domain']);
            if (!isset($email['id'])) {
                return $this->returnReloadWindow();
            }

            return array_merge(
                [
                    'id' => $email['id'],
                    'idle' => true,
                    'canCreate' => false,
                    'canUpdate' => false,
                    'canDelete' => $canDelete,
                ],
                $this->getStats($node, $email)
            );
        }

        $check = $this->emailService->emailCheck($node);

        if (
            ($this->emailAutomatation['create'] && $check['canCreate']) ||
            ($this->emailAutomatation['update'] && $check['canUpdate'])
        ) {
            $email = $this->createOrUpdate($liveNode, $arguments['domain']);
            if (!isset($email['id'])) {
                return $this->returnReloadWindow();
            }
            $message = $this->messageCheck($node, $check, false);
            return array_merge(
                [
                    'id' => $email['id'],
                    'canDelete' => $canDelete,
                    'canUpdate' => false,
                    'canCreate' => false,
                    'idle' => true,
                    'message' => $message,
                    'messageType' => 'warn',
                ],
                $this->getStats($node, $email)
            );
        }

        $message = $this->messageCheck($node, $check, true);
        if ($message) {
            $check['message'] = $message;
            $check['messageType'] = 'warn';
        }

        if ($check['canDelete']) {
            $check['canDelete'] = $canDelete;
        }
        return $check;
    }

    private function getStats(NodeInterface $node, array $email): array
    {
        $isVariantEmail = $this->variantEmailService->isVariantEmail($node);
        return [
            'sentCount' => $email[$isVariantEmail ? 'variantSentCount' : 'sentCount'] ?: 0,
            'readCount' => $email[$isVariantEmail ? 'variantReadCount' : 'readCount'] ?: 0,
        ];
    }

    private function returnReloadWindow()
    {
        return [
            'id' => null,
            'idle' => false,
            'canCreate' => false,
            'canUpdate' => false,
            'canDelete' => false,
            'reloadWindow' => true,
            'message' => $this->getMessage('error.reloadWindow'),
            'messageType' => 'error',
        ];
    }

    private function messageCheck(NodeInterface $node, array $check, $canUpdateCheck = false): ?string
    {
        if ($check['parentNeedPublishFirst'] ?? false) {
            return $this->getMessage('parentNeedPublishFirst');
        } elseif ($node->isHidden()) {
            return $this->getMessage('isHidden');
        } elseif ($canUpdateCheck && $check['canUpdate']) {
            return $this->getMessage('outdated');
        }
        return null;
    }

    private function getMessage($key): string
    {
        return 'Carbon.Newsletter:EmailView:' . $key;
    }

    private function createOrUpdate(NodeInterface $node, $domain): ?array
    {
        $segmentIds = null;
        $excludedSegmentIds = null;
        $category = null;

        $category = $this->variantEmailService->getProperty($node, 'category') ?? null;

        if ($node->getNodeType()->isOfType('Carbon.Newsletter:Document.Newsletter')) {
            $fQ = new FlowQuery([$node]);
            $rootNode = $fQ->closest('[instanceof Carbon.Newsletter:Mixin.Container]')->get(0);
            $newsletterSystemConfig = $rootNode->getProperty('newsletterSystemConfig') ?? [];
            $category = $category ?? ($newsletterSystemConfig['categories']['newsletter'] ?? null);
            $segmentsFromNode = $this->variantEmailService->getProperty($node, 'segments') ?? [];
            $segmentIds = count($segmentsFromNode) ? $segmentsFromNode : $newsletterSystemConfig['newsletterSegment'];
            $excludedSegmentIds = $newsletterSystemConfig['systemSegments']['optInPending'] ?? null;
        }

        return $this->emailService->call($node, $domain, $category, $segmentIds, $excludedSegmentIds);
    }
}
