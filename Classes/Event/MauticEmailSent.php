<?php

declare(strict_types=1);

namespace Garagist\Mautic\Event;

use Neos\EventSourcing\Event\DomainEventInterface;

final class MauticEmailSent implements DomainEventInterface
{
    /**
     * @var string
     */
    private $emailIdentifier;

    /**
     * @var int
     */
    private $mauticIdentifier;

    /**
     * @var int
     */
    private $success;

    /**
     * @var int
     */
    private $sentCount;

    /**
     * @var int
     */
    private $failedRecipients;

    public function __construct(string $emailIdentifier, int $mauticIdentifier, int $success, int $sentCount, int $failedRecipients)
    {
        $this->emailIdentifier = $emailIdentifier;
        $this->mauticIdentifier = $mauticIdentifier;
        $this->success = $success;
        $this->sentCount = $sentCount;
        $this->failedRecipients = $failedRecipients;
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
     * @return int
     */
    public function getMauticIdentifier(): int
    {
        return $this->mauticIdentifier;
    }

    /**
     * @param int $mauticIdentifier
     */
    public function setMauticIdentifier(int $mauticIdentifier): void
    {
        $this->mauticIdentifier = $mauticIdentifier;
    }

    /**
     * @return int
     */
    public function getSuccess(): int
    {
        return $this->success;
    }

    /**
     * @param int $success
     */
    public function setSuccess(int $success): void
    {
        $this->success = $success;
    }

    /**
     * @return int
     */
    public function getSentCount(): int
    {
        return $this->sentCount;
    }

    /**
     * @param int $sentCount
     */
    public function setSentCount(int $sentCount): void
    {
        $this->sentCount = $sentCount;
    }

    /**
     * @return int
     */
    public function getFailedRecipients(): int
    {
        return $this->failedRecipients;
    }

    /**
     * @param int $failedRecipients
     */
    public function setFailedRecipients(int $failedRecipients): void
    {
        $this->failedRecipients = $failedRecipients;
    }
}
