<?php

declare(strict_types=1);

namespace Garagist\Mautic\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Garagist\Mautic\Service\NodeService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Garagist\Mautic\Domain\Model\MauticEmail;

#[Flow\Scope('singleton')]
class SettingsService
{
    #[Flow\Inject]
    protected ConfigurationManager $configurationManager;

    #[Flow\Inject]
    protected NodeService $nodeService;

    /**
     * Get the path from the configuration
     *
     * @param string $settingPath
     * @param string|NodeInterface|MauticEmail|null $siteNameOrSite
     * @param string $rootPackage
     * @return string
     */
    public function path(string $settingPath, $sub = null, $rootPackage = 'Garagist.Mautic'): string
    {
        if ($sub instanceof MauticEmail) {
            $sub = $this->nodeService->getNodeById($sub->getNodeIdentifier());
        }
        if ($sub instanceof NodeInterface) {
            $sub = $this->nodeService->getSiteNameBasedOnNode($sub);
        }

        $siteName = is_string($sub) ? $sub : null;
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
    protected function getSetting(string $rootPackage, string $settingPath, $siteName = null): mixed
    {
        if ($siteName && is_string($siteName)) {
            $settingPath = sprintf('siteSettings.%s.%s', $siteName, $settingPath);
        }
        if (!str_starts_with($settingPath, $rootPackage)) {
            $settingPath = sprintf('%s.%s', $rootPackage, $settingPath);
        }

        $setting = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, $settingPath);
        return $setting ?? null;
    }
}
