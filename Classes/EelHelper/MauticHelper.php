<?php

namespace Garagist\Mautic\EelHelper;

use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Garagist\Mautic\Service\SettingsService;

class MauticHelper implements ProtectedContextAwareInterface
{

    #[Flow\Inject]
    protected SettingsService $settingsService;

    public function settings(string $settingPath, ?string $siteName = null, $rootPackage = 'Garagist.Mautic'): string
    {
        return $this->settingsService->path($settingPath, $siteName, $rootPackage);
    }
}
