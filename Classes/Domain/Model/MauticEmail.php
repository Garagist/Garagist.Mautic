<?php

namespace Garagist\Mautic\Domain\Model;

use Neos\Flow\Annotations as Flow;
use Doctrine\ORM\Mapping as ORM;
use DateTime;

/**
 *
 * @Flow\Entity
 */
class MauticEmail
{

    /**
     * @var string
     */
    const IDLE = 'idle';

    /**
     * @var string
     */
    const TASK_PUBLISH = 'publish';

    /**
     * @var string
     */
    const TASK_UN_PUBLISH = 'unPublish';

    /**
     * @var string
     */
    const TASK_UPDATE = 'update';

    /**
     * @var string
     */
    const TASK_CREATE = 'create';

    /**
     * @var string
     */
    const TASK_SEND = 'send';

    /**
     * @var string
     */
    const TASK_FAILED = 'failed';

    /**
     * Properties of this Email
     *
     * @ORM\Column(type="flow_json_array")
     * @var array<mixed>
     */
    protected $properties = [];

    /**
     * @var string
     */
    protected $emailIdentifier;

    /**
     * @var string
     */
    protected $nodeIdentifier;

    /**
     * @var bool
     */
    protected $published;

    /**
     * @var DateTime
     */
    protected $dateCreated;

    /**
     * @ORM\Column(nullable=true)
     * @var DateTime
     */
    protected $dateModified;


    /**
     * @var DateTime
     * @ORM\Column(nullable=true)
     */
    protected $dateSent;

    /**
     * @var string
     */
    protected $task;

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
     * Make sure the properties are always an array.
     *
     * If the JSON in the DB is corrupted, decoding it can fail, leading to
     * a null value. This may lead to errors later, when the value is used with
     * functions that expect an array.
     *
     * @return void
     * @ORM\PostLoad
     */
    public function ensurePropertiesIsNeverNull()
    {
        if (!is_array($this->properties)) {
            $this->properties = [];
        }
    }

    /**
     * Sets the specified property.
     *
     * @param string $propertyName Name of the property
     * @param mixed $value Value of the property
     * @return void
     */
    public function setProperty($propertyName, $value): void
    {
        if (array_key_exists($propertyName, $this->properties) && $this->properties[$propertyName] === $value) {
            return;
        }

        $this->properties[$propertyName] = $value;
    }

    /**
     * If this email has a property with the given name.
     *
     * @param string $propertyName Name of the property to test for
     * @return boolean
     */
    public function hasProperty($propertyName): bool
    {
        return array_key_exists($propertyName, $this->properties);
    }

    /**
     * Returns the specified property.
     *
     * @param string $propertyName Name of the property
     * @return mixed value of the property
     */
    public function getProperty($propertyName)
    {
        return isset($this->properties[$propertyName]) ? $this->properties[$propertyName] : null;
    }

    /**
     * Removes the specified property.
     *
     * @param string $propertyName Name of the property
     * @return void
     */
    public function removeProperty($propertyName): void
    {
        if (array_key_exists($propertyName, $this->properties)) {
            unset($this->properties[$propertyName]);
        }
    }

    /**
     * Returns all properties of this email.
     * 
     * @return array Property values, indexed by their name
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Returns the names of all properties of this email.
     *
     * @return array Property names
     */
    public function getPropertyNames(): array
    {
        return array_keys($this->properties);
    }


    /**
     * @return DateTime
     */
    public function getDateCreated(): DateTime
    {
        return $this->dateCreated;
    }

    /**
     * @param DateTime $dateCreated
     */
    public function setDateCreated(DateTime $dateCreated): void
    {
        $this->dateCreated = $dateCreated;
    }

    /**
     * @return DateTime|null
     */
    public function getDateSent(): ?DateTime
    {
        return $this->dateSent ?? null;
    }

    /**
     * @param DateTime $dateSent
     */
    public function setDateSent(DateTime $dateSent): void
    {
        $this->dateSent = $dateSent;
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

    /**
     * @return bool
     */
    public function isPublished(): bool
    {
        return $this->published;
    }

    /**
     * @param bool $published
     */
    public function setPublished(bool $published): void
    {
        $this->published = $published;
    }

    /**
     * @return string
     */
    public function getTask(): string
    {
        return $this->task;
    }

    /**
     * @param string $task
     */
    public function setTask(string $task): void
    {
        $this->task = $task;
    }

    /**
     * @return ?DateTime
     */
    public function getDateModified(): ?DateTime
    {
        return $this->dateModified;
    }

    /**
     * @param DateTime $dateModified
     */
    public function setDateModified(DateTime $dateModified): void
    {
        $this->dateModified = $dateModified;
    }
}
