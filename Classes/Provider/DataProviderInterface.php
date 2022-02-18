<?php

declare(strict_types=1);

namespace Garagist\Mautic\Provider;

use Garagist\Mautic\Domain\Model\MauticEmail;
use Neos\Flow\Annotations as Flow;

interface DataProviderInterface
{
    public function getDataForSegmentSendOut(MauticEmail $email, array $segments): array;

    public function getSegmentsForSendOut(MauticEmail $email): array;
}
