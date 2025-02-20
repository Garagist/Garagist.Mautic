<?php

declare(strict_types=1);

namespace Garagist\Mautic\Provider;

use Carbon\Newsletter\Service\PersonalizationService;
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
     * @Flow\Inject
     * @var PersonalizationService
     */
    protected $personalizationService;

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
        $this->context = $this->contextFactory->create([
            'workspaceName' => $workspace,
            'dimensions' => $dimensions,
        ]);
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
        return $this->personalizationService->mail($title, pattern: 'mautic');
    }

    /**
     * Get preheaderText from email
     *
     * @param MauticEmail $email
     * @return string
     */
    public function getPreheaderText(MauticEmail $email): string
    {
        $text = $email->getProperty('preheaderText');

        if (is_string($text) && $text !== '') {
            return $text;
        }

        $node = $this->getNode($email->getNodeIdentifier());
        $text = $node->getProperty('previewText');
        return $this->personalizationService->mail($text, pattern: 'mautic');
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
        return str_replace('-', '_', $language);
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
        $title = $this->personalizationService->web($node->getProperty('title'), pattern: 'mautic');
        $publishUp = $node->getProperty('publishDate');

        $publishDown = null;
        $format = 'Y-m-d H:i';
        if ($publishUp) {
            $publishDown = clone $publishUp;
            $publishDateRange = $node->getProperty('publishDateRange');
            $amount = $publishDateRange['amount'] ?? null;
            $unit = $publishDateRange['unit'] ?? 'day';
            $publishUp = $publishUp->format($format);
            if ($amount) {
                $publishDown->modify(sprintf('+%s %s%s', $amount, $unit, $amount > 1 ? 's' : ''));
                $publishDown = $publishDown->format($format);
            } elseif ($amount === 0) {
                // If the amount is 0 we take the unit as the amount
                // Example 0 hour == Till the next hour // 0 day == Till the next day
                switch ($unit) {
                    case 'minute':
                        $publishDown->modify('+1 minute');
                        break;
                    case 'hour':
                        // The next full hour
                        $publishDown->modify('+1 hour');
                        $hours = $publishDown->format('H');
                        $publishDown->modify($hours . ':00');
                        break;
                    case 'week':
                        $publishDown->modify('next monday');
                        $publishDown->modify('midnight');
                        break;
                    case 'month':
                        $publishDown->modify('+1 month');
                        $publishDown->modify('01.' . $publishDown->format('m.Y') . '00:00');
                        break;
                    default:
                        // We take day as default
                        $publishDown->modify('midnight');
                        $publishDown->modify('+1 day');
                        break;
                }

                $publishDown = $publishDown->format($format);
            } else {
                $publishDown = null;
            }
        }

        $subject = $this->getSubject($email);

        $name = [$title, $emailIdentifier];

        // TODO
        // * dynamicContent

        return [
            'title' => $title,
            'name' => join(' ❯ ', $name),
            'subject' => $subject,
            'category' => (int) $this->settings['category']['newsletter'],
            'template' => 'blank',
            'isPublished' => true,
            'customHtml' => $html,
            'plainText' => $this->getPlaintext($email),
            'preheaderText' => $this->getPreheaderText($email),
            'publishUp' => $publishUp,
            'publishDown' => $publishDown,
            'emailType' => 'list',
            'lists' => $segmentIds,
            'language' => $this->getLanguageFromHtml($html),
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
        $choosenSegments = $this->getChoosenSegments($email);
        if (is_array($choosenSegments) && count($choosenSegments)) {
            return $choosenSegments;
        }

        return $this->getAllSegmentIDsFromMautic($segmentsFromMautic);
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
        return array_map(function ($entry) {
            return $entry['id'];
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
                $id = $segment['id'];
            }
            return !in_array($id, $hiddenSegments);
        });
    }
}
