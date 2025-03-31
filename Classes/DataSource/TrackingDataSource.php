<?php

namespace Garagist\Mautic\DataSource;

use Garagist\Mautic\Service\SettingsService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Carbon\Eel\EelHelper\BackendHelper;
use Neos\Neos\Service\DataSource\AbstractDataSource;

class TrackingDataSource extends AbstractDataSource
{
    /**
     * @var string
     */
    protected static $identifier = 'garagist-mautic-tracking';

    #[Flow\Inject]
    protected SettingsService $settingsService;

    #[Flow\Inject]
    protected BackendHelper $backendHelper;

    /**
     * Get data
     *
     * @param NodeInterface $node The node that is currently edited (optional)
     * @param array $arguments Additional arguments (key / value)
     * @return array JSON serializable data
     */
    public function getData(NodeInterface $node = null, array $arguments = []): array
    {
        $settings = $this->settingsService->getAll($node, false);
        $result = [
            [
                'value' => '',
                'icon' => 'times',
                'label' => $this->backendHelper->translate('Garagist.Mautic:NodeTypes.Mixin.Tracking:disabled'),
            ],
        ];

        if (empty($settings)) {
            return [
                'hidden' => true,
            ];
        }
        $label =
            count($settings) == 1
                ? $this->backendHelper->translate('Garagist.Mautic:NodeTypes.Mixin.Tracking:enabled')
                : null;

        foreach ($settings as $setting) {
            $result[] = [
                'value' => $setting['url'],
                // Remove protocol from the url
                'label' => $label ?? preg_replace('#^[^:/.]*[:/]+#i', '', $setting['url']),
                'icon' => $label ? 'check' : 'link',
            ];
        }

        return $result;
    }
}
