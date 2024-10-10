<?php

namespace Garagist\Mautic\FusionObjects;

use Neos\Flow\Annotations as Flow;
use Neos\Fusion\FusionObjects\AbstractFusionObject;
use Garagist\Mautic\Service\SettingsService;

class SettingsImplementation extends AbstractFusionObject
{
    #[Flow\Inject]
    protected SettingsService $settingsService;

    /**
     * Get setting path
     *
     * @return string|null
     */
    protected function getPath(): ?string
    {
        return $this->fusionValue('path');
    }

    /**
     * Get site name
     *
     * @return string|null
     */
    protected function siteName(): ?string
    {
        return $this->fusionValue('siteName');
    }

    /**
     * @return string
     */
    public function evaluate()
    {
        $path = $this->getPath();
        $siteName = $this->siteName();

        return $this->settingsService->path($path, $siteName);
    }
}
