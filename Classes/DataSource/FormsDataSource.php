<?php

namespace Garagist\Mautic\DataSource;

use Garagist\Mautic\Service\ApiService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
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

    /**
     * Get data
     *
     * @param NodeInterface $node The node that is currently edited
     * @param array $arguments
     * @return array JSON serializable data
     */
    public function getData(NodeInterface $node = null, array $arguments = [])
    {
        return $this->apiService->getFormsForSelectbox($node);
    }
}
