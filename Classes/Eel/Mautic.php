<?php

namespace Garagist\Mautic\Eel;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Garagist\Mautic\Provider\DataProviderInterface;
use Neos\Eel\ProtectedContextAwareInterface;

class Mautic implements ProtectedContextAwareInterface
{
    /**
     * @Flow\Inject
     * @var DataProviderInterface
     */
    protected $data;

    /**
     * @param NodeInterface $node
     * @return string
     */
    public function getPublicUrl(NodeInterface $node): string
    {
        return $this->data->getPublicUrl($node);
    }

    /**
     * @param string $methodName
     * @return bool
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}