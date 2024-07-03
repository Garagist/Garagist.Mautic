<?php

declare(strict_types=1);

namespace Garagist\Mautic\Event;

use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
final class MauticEmailSend implements DomainEventInterface
{
    /**
     * @var string
     */
    private $emailIdentifier;

    /**
     * @var int
     */
    private $mauticIdentifier;

    public function __construct(string $emailIdentifier, int $mauticIdentifier)
    {
        $this->emailIdentifier = $emailIdentifier;
        $this->mauticIdentifier = $mauticIdentifier;
    }

    /**
     * @return string
     */
    public function getEmailIdentifier(): string
    {
        return $this->emailIdentifier ?? '';
    }

    /**
     * @param string $emailIdentifier
     */
    public function setEmailIdentifier(string $emailIdentifier): void
    {
        $this->emailIdentifier = $emailIdentifier;
    }

    /**
     * @return int
     */
    public function getMauticIdentifier(): int
    {
        return $this->mauticIdentifier ?? 0;
    }

    /**
     * @param int $mauticIdentifier
     */
    public function setMauticIdentifier(int $mauticIdentifier): void
    {
        $this->mauticIdentifier = $mauticIdentifier;
    }
}
