<?php

namespace Garagist\Mautic\DataSource;

use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\SettingsService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\DataSource\AbstractDataSource;

class SegmentsDataSource extends AbstractDataSource
{
    /**
     * @var string
     */
    protected static $identifier = 'garagist-mautic-segments';

    #[Flow\Inject]
    protected ApiService $apiService;

    #[Flow\Inject]
    protected SettingsService $settingsService;

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
            return [];
        }
        $fQ = new FlowQuery([$node]);
        $config = $fQ->closest('[instanceof Carbon.Newsletter:Mixin.Container]')->property('newsletterSystemConfig');
        $systemSegments = $config['systemSegments'] ?? [];
        $result = [];
        $segments = $this->apiService->getList($apiSettings, ApiService::ENDPOINT_SEGMENTS);

        foreach ($segments['lists'] as $segement) {
            if ($segement['isPublished'] && !in_array($segement['id'], $systemSegments)) {
                $result[] = [
                    'value' => $segement['id'],
                    'label' => $segement['name'],
                    'secondaryLabel' => $segement['description'] ?? null,
                ];
            }
        }
        return $result;
    }
}
