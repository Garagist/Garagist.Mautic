<?php

namespace Garagist\Mautic\FusionObjects;

use Garagist\Mautic\Service\ApiService;
use Neos\Flow\Annotations as Flow;
use Neos\Fusion\FusionObjects\AbstractFusionObject;

class ApiSettingsImplementation extends AbstractFusionObject
{
    #[Flow\Inject]
    protected ApiService $apiService;

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

        return $this->apiService->getSettingsFromPropertyValue($node, $propertyName);
    }
}
