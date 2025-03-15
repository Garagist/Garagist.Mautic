<?php

namespace Garagist\Mautic\DataSource;

use Garagist\Mautic\Service\ApiService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\DataSource\AbstractDataSource;
use Neos\Neos\Domain\Service\UserService;

class TestRecipientsDataSource extends AbstractDataSource
{
    /**
     * @var string
     */
    protected static $identifier = 'garagist-mautic-test-recipients';

    #[Flow\Inject]
    protected UserService $userService;

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
        $containerNode = $fQ->closest('[instanceof Carbon.Newsletter:Mixin.Container]')->get(0);
        $config = $containerNode->getProperty('testEmailRecipients') ?? [];
        $result = [];

        if ($this->userService->getCurrentUser()) {
            foreach ($this->userService->getCurrentUser()->getElectronicAddresses() as $electronicAddress) {
                if ($electronicAddress->getType() == 'Email') {
                    $result[] = $electronicAddress->getIdentifier();
                }
            }
        }

        foreach ($config as $item) {
            $result[] = $item['email'];
        }

        return array_unique(array_map('strtolower', $result));

    }
}
