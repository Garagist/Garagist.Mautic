<?php

declare(strict_types=1);

namespace Garagist\Mautic\Provider;

use Neos\ContentRepository\Domain\Model\NodeInterface;
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
     * @var int|null
     * @Flow\InjectConfiguration(path="unconfirmedSegment", package="Garagist.Mautic")
     */
    protected $unconfirmedSegment;

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

        $html = $this->mauticService->getNewsletterTemplate($email->getProperty('htmlUrl'));
        $plaintext = $this->mauticService->getNewsletterTemplate($email->getProperty('plaintextUrl'));
        $subject = $email->getProperty('subject');
        $title = $node->getProperty('title');

        if (!$subject) {
            // Fallback handling for old entries
            $titleOverride = $node->getProperty('titleOverride');
            $subject = $titleOverride ? $titleOverride : $title;
        }

        // Get langauge from html template
        preg_match('/<html.+?lang="([^"]+)"/im', $html, $languageMatch);
        $language = $languageMatch[1] ?? 'en';

        // TODO
        // * category (object/null)
        // * dynamicContent

        return [
            'title' => $title,
            'name' => $email->getEmailIdentifier() . ' ❯ ' . $title,
            'subject' => $subject,
            'template' => 'blank',
            'isPublished' => 0,
            'customHtml' => $html,
            'plainText' => $plaintext,
            'emailType' => 'list',
            'lists' => $segments,
            'language' => $language,
        ];
    }

    /**
     * @param MauticEmail $email
     * @return array
     */
    public function getSegmentsForSendOut(MauticEmail $email): array
    {
        $segments = $this->apiService->getAllSegments();
        $data = array_map(function ($n) {
            return $n->getId();
        }, $segments);

        if (!isset($this->unconfirmedSegment)) {
            return $data;
        }

        return array_filter($data, function ($n) {
            return $n !== $this->unconfirmedSegment;
        });
    }

    /**
     * @param $nodeIdentifier
     * @return NodeInterface|null
     */
    protected function getNode($nodeIdentifier)
    {
        return $this->context->getNodeByIdentifier($nodeIdentifier);
    }
}
