<?php
namespace Garagist\Mautic\Domain\Model;

use Neos\Flow\Annotations as Flow;

/**
 *
 * @Flow\Entity
 */
class MauticEmail {

    /**
     * @var string
     */
    protected $templateUrl;

    /**
     * @var string
     */
    protected $nodeIdentifier;

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
}
