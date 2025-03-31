<?php

namespace Garagist\Mautic\FusionObjects;

use Garagist\Mautic\Service\SettingsService;
use Neos\Flow\Annotations as Flow;
use Neos\Fusion\FusionObjects\AbstractFusionObject;

class ApiSettingsImplementation extends AbstractFusionObject
{
    #[Flow\Inject]
    protected SettingsService $settingsService;

    /**
     * @return array|null
     */
    public function evaluate()
    {
        $node = $this->fusionValue('node');
        $propertyName = $this->fusionValue('propertyName');

        if (!isset($node) || !$propertyName) {
            return null;
        }

        return $this->settingsService->getFromPropertyValue($node, $propertyName);
    }
}
