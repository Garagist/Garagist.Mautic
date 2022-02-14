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
     * @var string
     */
    protected $templateUrl;

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
