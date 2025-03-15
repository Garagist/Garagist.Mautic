<?php

namespace Garagist\Mautic\DataSource;

use Garagist\Mautic\Service\ApiService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\DataSource\AbstractDataSource;

class CategoriesDataSource extends AbstractDataSource
{
    /**
     * @var string
     */
    protected static $identifier = 'garagist-mautic-categories';

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
        $ping = $this->apiService->ping();
        if (!$ping) {
            return [];
        }
        $fQ = new FlowQuery([$node]);
        $containerNode = $fQ->closest('[instanceof Carbon.Newsletter:Mixin.Container]')->get(0);
        $config = $containerNode->getProperty('newsletterSystemConfig');

        $allowSystemCategories = $arguments['allowSystemCategories'] ?? false;
        if ($allowSystemCategories === 'true' || $allowSystemCategories === true) {
            $allowSystemCategories = true;
        } else {
            $allowSystemCategories = false;
        }

        $systemCategoryId = $config['categories']['system'] ?? null;

        $result = [];
        $categories = $this->apiService->getList(ApiService::ENDPOINT_CATEGORIES);
        foreach ($categories['categories'] as $category) {
            if ($allowSystemCategories || $systemCategoryId !== $category['id']) {
                $result[] = [
                    'value' => $category['id'],
                    'label' => $category['title'],
                    'secondaryLabel' => $category['description'] ?? null,
                ];
            }
        }
        return $result;
    }
}
