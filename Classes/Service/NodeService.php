<?php

declare(strict_types=1);

namespace Garagist\Mautic\Service;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Repository\DomainRepository;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\ContentDimensionPresetSourceInterface;

/**
 * A service for retrieving nodes from NeosCR
 *
 * @Flow\Scope("singleton")
 * @api
 */
class NodeService
{

    /**
     * @Flow\Inject
     * @var ContentDimensionPresetSourceInterface
     */
    protected $contentDimensionPresetSource;

    /**
     * @Flow\Inject
     * @var DomainRepository
     */
    protected $domainRepository;

    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @var array
     */
    protected array $options = [];


    /**
     * Get node by id
     *
     * @param string $identifier
     * @return NodeInterface
     */
    public function getNodeById(string $identifier): NodeInterface
    {
        $context = $this->getContentContextDimension();

        return $context->getNodeByIdentifier($identifier);
    }

    /**
     * Get nodes by type
     *
     * @param string $nodeTypeName The name of the node type
     * @param Context|null $context
     *
     * @return array
     * @throws Exception
     */
    public function getNodesByType(string $nodeTypeName, ?Context $context = null): array
    {
        if (!isset($context)) {
            $context = $this->getContentContextDimension();
        }

        $flowQuery = new FlowQuery(array($context->getCurrentSiteNode()));
        return $flowQuery->context([
            'invisibleContentShown' => false
        ])->find('[instanceof ' . $nodeTypeName . ']')->get();
    }

    /**
     * Get parent from a node with a given nodeTypeName
     *
     * @param NodeInterface $node
     * @param string $nodeTypeName
     *
     * @return NodeInterface|null
     */
    public function getParentByType(NodeInterface $node, string $nodeTypeName): ?NodeInterface
    {
        $flowQuery = new FlowQuery(array($node));
        return $flowQuery->parent()->closest('[instanceof ' . $nodeTypeName . ']')->get(0);
    }

    /**
     * Create Dimension Context
     *
     * @return Context
     */
    public function getContentContextDimension(): Context
    {

        $dimensionName = $this->options['dimensionName'] ?? 'language';
        $workspaceName = $this->options['workspaceName'] ?? 'live';

        if (!isset($this->options['dimensionValues']) || empty($this->options['dimensionValues'])) {
            $defaultPreset = $this->contentDimensionPresetSource->getDefaultPreset($dimensionName);
            if (!isset($defaultPreset['values'])) {
                return $this->createContentContext([], $workspaceName);
            }
            $dimensionValues = $defaultPreset['values'];
        }
        $dimensionArray[$dimensionName] = $dimensionValues;

        return $this->createContentContext($dimensionArray, $workspaceName);
    }


    /**
     * Create a ContentContext based on the given workspace name
     *
     * @param array  $dimensions Optional list of dimensions and their values which should be set
     * @param string $workspaceName Optional Name of the workspace to set for the context
     *
     * @return Context
     */
    protected function createContentContext(array $dimensions = [], string $workspaceName = 'live'): Context
    {
        $contextProperties = array(
            'workspaceName' => $workspaceName,
            'invisibleContentShown' => true,
            'inaccessibleContentShown' => true
        );

        if ($dimensions !== array()) {
            $contextProperties['dimensions'] = $dimensions;
            $contextProperties['targetDimensions'] = array_map(function ($dimensionValues) {
                return array_shift($dimensionValues);
            }, $dimensions);
        }

        $currentDomain = $this->domainRepository->findOneByActiveRequest();
        if ($currentDomain !== null) {
            $contextProperties['currentSite'] = $currentDomain->getSite();
            $contextProperties['currentDomain'] = $currentDomain;
        } else {
            $contextProperties['currentSite'] = $this->siteRepository->findFirstOnline();
        }

        return $this->contextFactory->create($contextProperties);
    }

    /**
     * Set options
     *
     * @param array $options Contains options
     *        like string $dimensionName Optional Dimension name,
     *        string $dimensionValues Optional The preferred dimension values, including the fallback dimension like array('en', 'de'); ,
     *        string $workspaceName Optional Name of the workspace to set for the context
     * @return void
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}
