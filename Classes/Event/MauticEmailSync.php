<?php

declare(strict_types=1);

namespace Garagist\Mautic\Event;

use Neos\EventSourcing\Event\DomainEventInterface;

/**
 * @Flow\Proxy(false)
 */
final class MauticEmailSync implements DomainEventInterface
{
    /**
     * @var string
     */
    private $nodeIdentifier;

    /**
     * @var string
     */
    private $emailIdentifier;


    public function __construct(string $emailIdentifier, string $nodeIdentifier)
    {
        $this->emailIdentifier = $emailIdentifier;
        $this->nodeIdentifier = $nodeIdentifier;
    }

    /**
     * @return string
     */
    public function getNodeIdentifier(): string
    {
        return $this->nodeIdentifier;
    }

    /**
     * @param string $nodeIdentifier
     */
    public function setNodeIdentifier(string $nodeIdentifier): void
    {
        $this->nodeIdentifier = $nodeIdentifier;
    }

    /**
     * @return string
     */
    public function getEmailIdentifier(): string
    {
        return $this->emailIdentifier;
    }

    /**
     * @param string $emailIdentifier
     */
    public function setEmailIdentifier(string $emailIdentifier): void
    {
        $this->emailIdentifier = $emailIdentifier;
    }
}
