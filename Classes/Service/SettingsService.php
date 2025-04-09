<?php

namespace Garagist\Mautic\Service;

use Carbon\Newsletter\Service\NodeService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Exception;

#[Flow\Scope('singleton')]
class SettingsService
{
    #[Flow\InjectConfiguration('api')]
    protected array $apiSettings;

    #[Flow\Inject]
    protected NodeService $nodeService;

    /**
     * Returns all settings for the mautic instances
     *
     * @param NodeInterface|null $node
     * @param boolean $throwException
     * @return array
     */
    public function getAll(?NodeInterface $node = null, bool $throwException = true): array
    {
        $result = [];

        // Get the settings from the global config
        $globalSetting = $this->getFromNodeOrConfig(null, false);
        if (isset($globalSetting)) {
            $result[$globalSetting['url']] = $globalSetting;
        }

        if (!isset($node)) {
            if ($throwException && empty($result)) {
                throw new Exception('No Mautic instances found');
            }

            return $result;
        }

        $siteNode = $this->nodeService->getSiteNodeFromNode($node);

        $fq = new FlowQuery([$siteNode]);
        $nodes = $fq->find('[instanceof Carbon.Newsletter:Mixin.Container][mauticSettings]')->get();

        foreach ($nodes as $containerNode) {
            $settings = $this->getFromNode($containerNode);
            if (isset($settings)) {
                // We use the url as key
                $result[$settings['url']] = $settings;
            }
        }

        if ($throwException && empty($result)) {
            throw new Exception('No Mautic instances found');
        }

        return $result;
    }

    /**
     * Get the mautic settings
     *
     * @param NodeInterface|null $node
     * @param bool $throwException
     * @return array
     */
    public function getFromNodeOrConfig(?NodeInterface $node = null, bool $throwException = true): ?array
    {
        if (isset($node)) {
            $fq = new FlowQuery([$node]);
            $containerNode = $fq->closest('[instanceof Carbon.Newsletter:Mixin.Container][mauticSettings]')->get(0);
            $mauticSettings = $this->getFromNode($containerNode);
            if (isset($mauticSettings)) {
                return $mauticSettings;
            }
        }

        $settings = $this->cleanup($this->apiSettings);

        if ($settings) {
            return $settings;
        }

        if ($throwException) {
            throw new Exception('Mautic API settings are not correct');
        }

        return null;
    }

    public function getFromPropertyValue(NodeInterface $node, ?string $propertyName = null): ?array
    {
        $propertyValue = $node->getProperty($propertyName);

        if (!$propertyValue) {
            return null;
        }

        $value = explode('---', $propertyValue);
        if (count($value) !== 2) {
            return null;
        }
        $id = (int) $value[0];
        $identifier = $value[1];

        if ($identifier === 'default') {
            $settings = $this->getFromNodeOrConfig();
        } else {
            $settings = $this->getFromNode($node, $identifier);
        }

        if (!isset($settings)) {
            return null;
        }

        $settings['id'] = $id;
        return $settings;
    }

    /**
     * Get the mautic settings from a node
     *
     * @param NodeInterface|null $node The node to get the settings from
     * @param string|null $identifier If set, it get the node by identifier
     * @return array|null
     */
    private function getFromNode(?NodeInterface $node = null, ?string $identifier = null): ?array
    {
        if (!isset($node)) {
            return null;
        }

        if ($identifier) {
            $siteNode = $this->nodeService->getSiteNodeFromNode($node);
            $node = $this->nodeService->findNodeById($siteNode, $identifier);
        }

        if (!($node instanceof NodeInterface)) {
            return null;
        }

        $node = $this->nodeService->getLiveNode($node);
        $settings = $node ? ($node->getProperty('mauticSettings') ?: null) : null;

        if (!isset($settings)) {
            return null;
        }

        // mauticSettings is an repeatable field, so we get the first (and only) item
        $settings = $settings->toArray();
        if (empty($settings)) {
            return null;
        }

        $settings = $this->cleanup($settings[0]);
        if ($settings) {
            $settings['node'] = $node;
            return $settings;
        }

        return null;
    }

    /**
     * Clean up settings, make sure url has protocol and no trailing slashes
     *
     * @param array|null $array
     * @return array|null
     */
    private function cleanup(?array $array): ?array
    {
        if (empty($array) || empty($array['url']) || empty($array['username']) || empty($array['password'])) {
            return null;
        }

        $array['url'] = trim($array['url'], '/ \n\r\t\v\x00');
        // Prefix the url with https if it's not already
        if (!str_starts_with($array['url'], 'http://') && !str_starts_with($array['url'], 'https://')) {
            $array['url'] = 'https://' . $array['url'];
        }

        return $array;
    }
}
