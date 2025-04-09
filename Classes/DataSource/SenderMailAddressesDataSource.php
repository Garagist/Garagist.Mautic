<?php

namespace Garagist\Mautic\DataSource;

use Neos\Eel\FlowQuery\FlowQuery;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Neos\Service\DataSource\AbstractDataSource;

class SenderMailAddressesDataSource extends AbstractDataSource
{
    /**
     * @var string
     */
    protected static $identifier = 'garagist-mautic-sender-email-addresses';

    /**
     * Get data
     *
     * @param NodeInterface $node The node that is currently edited (optional)
     * @param array $arguments Additional arguments (key / value)
     * @return array JSON serializable data
     */
    public function getData(NodeInterface $node = null, array $arguments = []): array
    {
        $fQ = new FlowQuery([$node]);
        $config = $fQ->closest('[instanceof Carbon.Newsletter:Mixin.Container]')->property('senderEmails');

        if (empty($config)) {
            return [];
        }

        $config = $config->toArray();
        $emails = array_map(function ($item) {
            return $item['email'] ?? null;
        }, $config);

        $emails = array_unique(array_filter($emails));

        return array_map(function ($email) {
            return [
                'value' => $email,
                'label' => $email,
            ];
        }, $emails);
    }
}
