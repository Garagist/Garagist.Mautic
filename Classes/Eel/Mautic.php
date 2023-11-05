<?php

namespace Garagist\Mautic\Eel;

use Garagist\Mautic\Provider\DataProviderInterface;
use Neos\Eel\ProtectedContextAwareInterface;

class Mautic implements ProtectedContextAwareInterface
{
    /**
     * @Flow\Inject
     * @var DataProviderInterface
     */
    protected $data;

    public function getPublicUrl(): string
    {
        return $this->data->getPublicUrl();
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