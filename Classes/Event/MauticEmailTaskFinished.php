<?php

declare(strict_types=1);

namespace Garagist\Mautic\Event;

use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class MauticEmailTaskFinished implements DomainEventInterface
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
    private $task;

    /**
     * @var string
     */
    private $error;

    public function __construct(string $emailIdentifier, string $nodeIdentifier, string $task, string $error)
    {
        $this->emailIdentifier = $emailIdentifier;
        $this->nodeIdentifier = $nodeIdentifier;
        $this->task = $task;
        $this->error = $error;
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
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * @param string $error
     */
    public function setError(string $error): void
    {
        $this->error = $error;
    }
}
