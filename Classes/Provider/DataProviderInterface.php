<?php

declare(strict_types=1);

namespace Garagist\Mautic\Provider;

use Garagist\Mautic\Domain\Model\MauticEmail;
use Neos\ContentRepository\Domain\Model\NodeInterface;

interface DataProviderInterface
{
    public function getChoosenSegments(MauticEmail $email): ?array;

    public function getSubject(MauticEmail $email): string;

    public function getLanguageFromHtml(string $html): string;

    public function getHtml(MauticEmail $email): string;

    public function getPlaintext(MauticEmail $email): string;

    public function getUtmTags(string $campaign, string $medium = 'email', string $source = 'newsletter'): array;

    public function getData(MauticEmail $email, array $segmentIds): array;

    public function filterSegments(MauticEmail $email, array $segmentsFromMautic): array;

    public function getCategoryNode(NodeInterface $node): ?NodeInterface;
}
