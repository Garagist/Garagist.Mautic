<?php

namespace Garagist\Mautic\EelHelper;

use Garagist\Mautic\Service\SettingsService;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;

class MauticHelper implements ProtectedContextAwareInterface
{

    #[Flow\Inject]
    protected SettingsService $settingsService;

    public function settings(string $settingPath, ?string $siteName = null, $rootPackage = 'Garagist.Mautic'): string
    {
        return $this->settingsService->path($settingPath, $siteName, $rootPackage);
    }

    /**
     * All methods are considered safe
     *
     * @param string $methodName The name of the method
     * @return bool
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
