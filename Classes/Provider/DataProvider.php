<?php

declare(strict_types=1);

namespace Garagist\Mautic\Provider;

use Garagist\Mautic\Domain\Model\MauticEmail;
use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\MauticService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Exception;
use Psr\Log\LoggerInterface;

/**
 * @Flow\Scope("singleton")
 */
class DataProvider implements DataProviderInterface
{
    /**
     * @var array
     * @Flow\InjectConfiguration(package="Garagist.Mautic")
     */
    protected $settings;

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
     * Get the choosen segment from the email
     *
     * @param MauticEmail $email
     * @return array|null
     */
    public function getChoosenSegments(MauticEmail $email): ?array
    {
        return $email->getProperty('segments');
    }

    /**
     * Get subject from email
     *
     * @param MauticEmail $email
     * @return string
     */
    public function getSubject(MauticEmail $email): string
    {
        $subject = $email->getProperty('subject');

        if (is_string($subject) && $subject !== '') {
            return $subject;
        }

        $node = $this->getNode($email->getNodeIdentifier());
        $title = $node->getProperty('title');
        $titleOverride = $node->getProperty('titleOverride');

        return $titleOverride ? $titleOverride : $title;
    }

    /**
     * Get langauge from html template
     *
     * @param string $html
     * @return string
     */
    public function getLanguageFromHtml(string $html): string
    {
        preg_match('/<html.+?lang="([^"]+)"/im', $html, $languageMatch);
        $language = $languageMatch[1] ?? 'en';
        return str_replace("-", "_", $language);
    }

    /**
     * Get HTML from email
     *
     * @param MauticEmail $email
     * @return string
     */
    public function getHtml(MauticEmail $email): string
    {
        return $this->mauticService->getNewsletterTemplate($email->getProperty('htmlUrl'));
    }

    /**
     * Get Plaintext from email
     *
     * @param MauticEmail $email
     * @return string
     */
    public function getPlaintext(MauticEmail $email): string
    {
        return $this->mauticService->getNewsletterTemplate($email->getProperty('plaintextUrl'));
    }

    /**
     * Get the UTM tags for the email
     *
     * @param string $campaign
     * @param string $medium
     * @param string $source
     * @return array
     */
    public function getUtmTags(string $campaign, string $medium = 'email', string $source = 'newsletter'): array
    {
        return [
            'utmSource' => $source,
            'utmMedium' => $medium,
            'utmCampaign' => $campaign,
        ];
    }

    /**
     * @param MauticEmail $email
     * @param array $segments
     * @return iterable
     * @throws \Neos\ContentRepository\Exception\NodeException
     * @throws \Neos\Eel\Exception
     * @throws \Neos\Flow\Http\Client\InfiniteRedirectionException
     */
    public function getData(MauticEmail $email, array $segmentIds): array
    {
        $this->mauticLogger->debug(sprintf('Using %s DataProvider', static::class));
        $node = $this->getNode($email->getNodeIdentifier());
        $emailIdentifier = $email->getEmailIdentifier();

        $html = $this->getHtml($email);
        $plaintext = $this->getPlaintext($email);
        $title = $node->getProperty('title');
        $subject = $this->getSubject($email);
        $language = $this->getLanguageFromHtml($html);

        $name = [$emailIdentifier, $title];
        if ($title != $subject) {
            $name[] = $subject;
        }

        // TODO
        // * dynamicContent

        return [
            'title' => $title,
            'name' => join(' ❯ ', $name),
            'subject' => $subject,
            'category' => (int)$this->settings['category']['newsletter'],
            'template' => 'blank',
            'isPublished' => 0,
            'customHtml' => $html,
            'plainText' => $plaintext,
            'emailType' => 'list',
            'lists' => $segmentIds,
            'language' => $language,
            'utmTags' => $this->getUtmTags($emailIdentifier),
        ];
    }

    /**
     * @param NodeInterface $node
     * @return array of ids
     */
    public function getPrefilledSegments(NodeInterface $node): array
    {
        $segmentMapping = $this->settings['segment']['mapping'];
        if (is_array($segmentMapping)) {
            return $segmentMapping;
        }
        if (is_string($segmentMapping) || is_numeric($segmentMapping)) {
            return [(int) $segmentMapping];
        }

        return [];
    }

    /**
     * @param MauticEmail $email
     * @param array $segmentsFromMautic
     * @return array Segment IDs
     */
    public function filterSegments(MauticEmail $email, array $segmentsFromMautic): array
    {
        $segments = $this->getChoosenSegments($email) ?? $this->getAllSegmentIDsFromMautic($segmentsFromMautic);
        return $segments;
    }

    /**
     * Get the category Node
     *
     * @param NodeInterface $node
     * @return NodeInterface|null
     */
    public function getCategoryNode(NodeInterface $node): ?NodeInterface
    {
        $fq = new FlowQuery([$node]);
        return $fq->closest('[instanceof Garagist.Mautic:Mixin.Category]')->get(0);
    }

    /**
     * @param $nodeIdentifier
     * @return NodeInterface|null
     */
    protected function getNode($nodeIdentifier)
    {
        return $this->context->getNodeByIdentifier($nodeIdentifier);
    }

    /**
     * Get all the segment IDs from Mautic
     *
     * @return array
     * @throws Exception
     */
    protected function getAllSegmentIDsFromMautic(array $segmentsFromMautic): array
    {
        $filteredSegments = $this->filterHiddenSegments($segmentsFromMautic);
        return array_map(function ($n) {
            return $n->getId();
        }, $filteredSegments);
    }

    /**
     * Filter hidden segments
     *
     * @param array $segments
     * @return array
     */
    protected function filterHiddenSegments(array $segments): array
    {
        $hiddenSegments = $this->settings['segment']['hide'];
        if (is_int($hiddenSegments)) {
            $hiddenSegments = [$hiddenSegments];
        }

        if (!is_array($hiddenSegments)) {
            return $segments;
        }

        return array_filter($segments, function ($segment) use ($hiddenSegments) {
            $id = $segment;
            if (!is_numeric($segment)) {
                $id = $segment->getId();
            }
            return !in_array($id, $hiddenSegments);
        });
    }
}
