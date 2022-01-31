<?php

declare(strict_types=1);

namespace Garagist\Mautic\Event;

use Neos\EventSourcing\Event\DomainEventInterface;

final class MauticEmailCreate implements DomainEventInterface
{
    /**
     * @var string
     */
    private $nodeIdentifier;

    /**
     * @var string
     */
    private $emailIdentifier;

    /**
     * @var string
     */
    private $templateUrl;

    public function __construct(string $emailIdentifier, string $nodeIdentifier, string $templateUrl)
    {
        $this->emailIdentifier = $emailIdentifier;
        $this->nodeIdentifier = $nodeIdentifier;
        $this->templateUrl = $templateUrl;
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
    public function getTemplateUrl(): string
    {
        return $this->templateUrl;
    }

    /**
     * @param string $templateUrl
     */
    public function setTemplateUrl(string $templateUrl): void
    {
        $this->templateUrl = $templateUrl;
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
