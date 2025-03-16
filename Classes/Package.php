<?php

namespace Garagist\Mautic;

use Garagist\Mautic\Service\EmailService;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;

/**
 * The Node Templates Magic Package
 */
class Package extends BasePackage
{
    /**
     * @param Bootstrap $bootstrap The current bootstrap
     * @return void
     */
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect(Node::class, 'nodePropertyChanged', EmailService::class, 'nodePropertyChanged');
        $dispatcher->connect(Workspace::class, 'afterNodePublishing', EmailService::class, 'afterNodePublishing');
    }
}
