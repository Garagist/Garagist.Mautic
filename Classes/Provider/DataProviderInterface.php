<?php

declare(strict_types=1);

namespace Garagist\Mautic\Provider;

use Garagist\Mautic\Domain\Model\MauticEmail;
use Neos\ContentRepository\Domain\Model\NodeInterface;

interface DataProviderInterface
{
    public function getEmailSegments(MauticEmail $email): ?array;

    public function getSubject(MauticEmail $email): string;

    public function getLanguageFromHtml(string $html): string;

    public function getHtml(MauticEmail $email): string;

    public function getPlaintext(MauticEmail $email): string;

    public function getUtmTags(string $campaign, string $medium = 'email', string $source = 'newsletter'): array;

    public function getData(MauticEmail $email, array $segmentIds): array;

    public function getSendOutSegments(MauticEmail $email): array;

    public function getPreCheckedSegments(NodeInterface $node): array;

    public function getSelectableSegments(NodeInterface $node): array;

    public function getCategoryNode(NodeInterface $node): ?NodeInterface;
}
