<?php

namespace Garagist\Mautic\FusionObjects;

use Garagist\Mautic\Service\PersonalizationService;
use Neos\Flow\Annotations as Flow;
use Neos\Fusion\FusionObjects\AbstractFusionObject;

class PersonalizationImplementation extends AbstractFusionObject
{
    #[Flow\Inject]
    protected PersonalizationService $personalizationService;

    /**
     * @return string
     */
    public function evaluate()
    {
        $enable = $this->fusionValue('enable');
        $content = (string)$this->fusionValue('content');

        if ($this->fusionValue('webview')) {
            return $this->personalizationService->webview($content, $enable);
        }

        return $this->personalizationService->mautic($content, $enable);
    }
}
