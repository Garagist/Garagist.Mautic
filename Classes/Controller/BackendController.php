<?php

declare(strict_types=1);

namespace Garagist\Mautic\Controller;

use Garagist\Mautic\Service\MauticService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Exception\NoSuchArgumentException;
use Neos\Flow\Mvc\View\ViewInterface;
use Neos\Flow\Security\Context;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Neos\Service\LinkingService;
use Neos\Neos\TypeConverter\NodeConverter;


/**
 * @Flow\Scope("singleton")
 */
class BackendController extends AbstractModuleController
{
    /**
     * @var FusionView
     */
    protected $view;

    /**
     * @var string
     */
    protected $defaultViewObjectName = FusionView::class;

    /**
     * @Flow\Inject
     * @var Context
     */
    protected $securityContext;

    /**
     * @Flow\Inject
     * @var LinkingService
     */
    protected $linkingService;

    /**
     * @Flow\Inject
     * @var MauticService
     */
    protected $mauticService;

    /**
     * @var array
     */
    protected $viewFormatToObjectNameMap = [
        'html' => FusionView::class,
    ];

    /**
     * Sets the Fusion path pattern on the view to avoid conflicts with the frontend fusion
     *
     * This is not needed if your package does not register itself to `Neos.Neos.fusion.autoInclude.*`
     */
    protected function initializeView(ViewInterface $view)
    {
        parent::initializeView($view);
        $view->setFusionPathPattern('resource://Garagist.Mautic/Private/Fusion/Backend');
    }

    /**
     * Allow invisible nodes to be redirected to
     *
     * @return void
     * @throws NoSuchArgumentException
     */
    protected function initializeEmailAction(): void
    {
        // use this constant only if available (became available with patch level releases in Neos 4.0 and up)
        if (defined(NodeConverter::class . '::INVISIBLE_CONTENT_SHOWN')) {
            $this->arguments->getArgument('node')->getPropertyMappingConfiguration()->setTypeConverterOption(NodeConverter::class, NodeConverter::INVISIBLE_CONTENT_SHOWN, true);
        }
    }

    /**
     * Renders the view
     */
    public function indexAction(): void
    {
    }

    public function emailAction(NodeInterface $node): void
    {

    }

    /**
     * Renders the view
     */
    public function createAction(NodeInterface $node): void
    {
        $uriBuilder = $this->controllerContext->getUriBuilder();
        $uriBuilder->setRequest($this->controllerContext->getRequest()->getMainRequest());
        $uri = $uriBuilder->reset()
            ->setFormat('html')
            ->setCreateAbsoluteUri(true)
            ->setArguments(['maizzle'=>true])
            ->uriFor('show', ['node' => $node], 'Frontend\Node', 'Neos.Neos');


        $templateUrl = preg_replace('/^(.*)(@user.*)(.maizzle)$/', '$1$3', $uri);

        $this->mauticService->createEmail($node->getIdentifier(), $templateUrl);

//        die('test');
//        $this->redirect('email', null, null, ['node' => $node]);
    }
}