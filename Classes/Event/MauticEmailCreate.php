<?php

declare(strict_types=1);

namespace Garagist\Mautic\Event;

use Neos\EventSourcing\Event\DomainEventInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @Flow\Proxy(false)
 */
class MauticEmailCreate implements DomainEventInterface
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
     * @ORM\Column(type="flow_json_array")
     * @var array<mixed>
     */
    protected $properties = [];

    /**
     * @param string $emailIdentifier
     * @param string $nodeIdentifier
     * @param array $properties
     */
    public function __construct(string $emailIdentifier, string $nodeIdentifier, array $properties)
    {
        $this->emailIdentifier = $emailIdentifier;
        $this->nodeIdentifier = $nodeIdentifier;
        $this->properties = $properties;
    }

    /* @return void
     * @ORM\PostLoad
     */
    public function ensurePropertiesIsNeverNull()
    {
        if (!is_array($this->properties)) {
            $this->properties = [];
        }
    }

    /**
     * @return array Property values, indexed by their name
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @param array $properties
     * @return void
     */
    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
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
