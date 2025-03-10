<?php

namespace Garagist\Mautic\DataSource;

use Garagist\Mautic\Service\EmailService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\DataSource\AbstractDataSource;

class UpdateDataSource extends AbstractDataSource
{
    /**
     * @var string
     */
    protected static $identifier = 'garagist-mautic-update';

    #[Flow\Inject]
    protected EmailService $emailService;

    /**
     * Get data
     *
     * @param NodeInterface $node The node that is currently edited (optional)
     * @param array $arguments Additional arguments (key / value)
     * @return array JSON serializable data
     */
    public function getData(NodeInterface $node = null, array $arguments = [])
    {
        $fQ = new FlowQuery([$node]);
        $node = $fQ->context(['workspaceName' => 'live'])->get(0);
        $this->emailService->call($arguments['domain'], $arguments['language'], $node);
    }
}
