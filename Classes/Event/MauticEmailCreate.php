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
    private $htmlTemplateUrl;

    /**
     * @var string
     */
    private $plaintextTemplateUrl;

    /**
     * @var string
     */
    private $subject;

    public function __construct(
        string $emailIdentifier,
        string $nodeIdentifier,
        string $htmlTemplateUrl,
        ?string $plaintextTemplateUrl = null,
        ?string $subject = null
    ) {
        $this->emailIdentifier = $emailIdentifier;
        $this->nodeIdentifier = $nodeIdentifier;
        $this->htmlTemplateUrl = $htmlTemplateUrl;
        $this->plaintextTemplateUrl = $plaintextTemplateUrl ?? '';
        $this->subject = $subject ?? '';
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
    public function getHtmlTemplateUrl(): string
    {
        return $this->htmlTemplateUrl;
    }

    /**
     * @param string $htmlTemplateUrl
     */
    public function setHtmlTemplateUrl(string $htmlTemplateUrl): void
    {
        $this->htmlTemplateUrl = $htmlTemplateUrl;
    }

    /**
     * @return string
     */
    public function getPlaintextTemplateUrl(): string
    {
        return $this->plaintextTemplateUrl;
    }

    /**
     * @param string $plaintextTemplateUrl
     */
    public function setPlaintextTemplateUrl(string $plaintextTemplateUrl): void
    {
        $this->plaintextTemplateUrl = $plaintextTemplateUrl;
    }

    /**
     * @return string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @param string $subject
     */
    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
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
