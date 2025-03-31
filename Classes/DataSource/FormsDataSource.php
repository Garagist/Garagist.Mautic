<?php

namespace Garagist\Mautic\DataSource;

use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\SettingsService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\DataSource\AbstractDataSource;

class FormsDataSource extends AbstractDataSource
{
    /**
     * @var string
     */
    protected static $identifier = 'garagist-mautic-forms';

    #[Flow\Inject]
    protected ApiService $apiService;

    #[Flow\Inject]
    protected SettingsService $settingsService;

    /**
     * Get the list of all forms
     *
     * @param NodeInterface $node The node that is currently edited
     * @param array $arguments
     * @return array JSON serializable data
     */
    public function getData(NodeInterface $node = null, array $arguments = [])
    {
        $mauticInstances = $this->settingsService->getAll($node);
        $data = [];
        $setGroup = count($mauticInstances) > 1;

        // Go through all mautic instances
        foreach ($mauticInstances as $url => $apiSettings) {
            $response = $this->apiService->getList(
                $apiSettings,
                ApiService::ENDPOINT_FORMS,
                orderBy: 'id',
                publishedOnly: true,
                ray: false,
                throwExeptions: false
            );
            if ($response['total'] === 0) {
                continue;
            }

            /** @var TraversableNodeInterface $node */
            $node = $apiSettings['node'] ?? null;
            $idSuffix = $node ? $node->getNodeAggregateIdentifier() : 'default';
            foreach ($response['forms'] as $form) {
                $key = sprintf('%s---%s', $form['id'], $idSuffix);
                $data[$key] = [
                    'label' => sprintf('%s: %s', $form['id'], $form['name']),
                ];
                if ($setGroup) {
                    // Remove protocol from the url
                    $data[$key]['group'] = preg_replace('#^[^:/.]*[:/]+#i', '', $url);
                }
            }
        }

        return $data;
    }
}
