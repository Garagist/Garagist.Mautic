<?php

declare(strict_types=1);

namespace Garagist\Mautic\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;

#[Flow\Scope('singleton')]
class SettingsService
{
    #[Flow\Inject]
    protected ConfigurationManager $configurationManager;

    /**
     * Get configuration based on the setting path and sitename
     *
     * @return string
     */
    public function path(string $settingPath, ?string $siteName = null, $rootPackage = 'Garagist.Mautic'): string
    {
        $siteSettings = $this->getSetting($rootPackage, $settingPath, $siteName);

        if (isset($siteSettings)) {
            return $siteSettings;
        }

        return $this->getSetting($rootPackage, $settingPath);
    }

    /**
     * Get the setting from the configuration
     *
     * @param string $settingPath
     * @param string|null $siteName
     * @return mixed
     */
    protected function getSetting(string $rootPackage, string $settingPath, ?string $siteName = null): mixed
    {
        if ($siteName) {
            $settingPath = sprintf('siteSettings.%s.%s', $siteName, $settingPath);
        }
        if (!str_starts_with($settingPath, $rootPackage)) {
            $settingPath = sprintf('%s.%s', $rootPackage, $settingPath);
        }

        $setting = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, $settingPath);
        return $setting ?? null;
    }
}
