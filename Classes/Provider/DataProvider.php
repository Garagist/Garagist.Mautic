<?php

declare(strict_types=1);

namespace Garagist\Mautic\Provider;

use Neos\Flow\Annotations as Flow;
use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\MauticService;
use Garagist\Mautic\Domain\Model\MauticEmail;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Flow\Exception;
use Psr\Log\LoggerInterface;

/**
 * @Flow\Scope("singleton")
 */
class DataProvider implements DataProviderInterface
{

    /**
     * @var array
     * @Flow\InjectConfiguration(path="segmentMapping", package="Garagist.Mautic")
     */
    protected $segmentMapping;

    /**
     * @Flow\Inject
     * @var MauticService
     */
    protected $mauticService;

    /**
     * @Flow\Inject(name="Garagist.Mautic:MauticLogger")
     * @var LoggerInterface
     */
    protected $mauticLogger;

    /**
     * @Flow\Inject
     * @var ApiService
     */
    protected $apiService;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @throws Exception
     * @throws \Mautic\Exception\ContextNotFoundException
     */
    protected function initializeObject(): void
    {
        $this->initContext();
    }

    /**
     * @param string $workspace
     * @param array $dimensions
     */
    private function initContext(string $workspace = 'live', array $dimensions = []): void
    {
        $this->context = $this->contextFactory->create(
            [
                'workspaceName' => $workspace,
                'dimensions' => $dimensions,
            ]
        );
    }

    /**
     * @param MauticEmail $email
     * @param array $segments
     * @return iterable
     * @throws \Neos\ContentRepository\Exception\NodeException
     * @throws \Neos\Eel\Exception
     * @throws \Neos\Flow\Http\Client\InfiniteRedirectionException
     */
    public function getDataForSegmentSendOut(MauticEmail $email, array $segments): array
    {
        $this->mauticLogger->debug(sprintf('Using %s DataProvider', static::class));
        $node = $this->getNode($email->getNodeIdentifier());
        $nlTemplate = $this->mauticService->getNewsletterTemplate($email->getTemplateUrl());

        $data = array(
            'title' => $node->getProperty('title'),
            'name' => $node->getProperty('title') . ' | [' . $email->getEmailIdentifier() . ']',
            'subject' => $node->getProperty('title') . ' | subject',
            'description' => $node->getProperty('title') . ' | description',
            'template' => 'blank',
            'isPublished' => 0,
            'customHtml' => $nlTemplate,
            'plainText' => $node->getProperty('title'),
            'emailType' => 'list',
            'lists' => $segments,
        );

        return $data;
    }

    /**
     * @param MauticEmail $email
     * @return array
     */
    public function getSegmentsForSendOut(MauticEmail $email): array
    {
        $segments = $this->apiService->getAllSegments();

        return array_map(function ($n) {
            return $n->getId();
        }, $segments);
    }

    /**
     * @param $nodeIdentifier
     * @return \Neos\ContentRepository\Domain\Model\NodeInterface|null
     */
    protected function getNode($nodeIdentifier)
    {
        return $this->context->getNodeByIdentifier($nodeIdentifier);
    }
}
