<?php

declare(strict_types=1);

namespace Garagist\Mautic\Controller;

use Neos\Flow\Annotations as Flow;
use Garagist\Mautic\Domain\Model\MauticEmail;
use Garagist\Mautic\Service\NodeService;
use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\MauticService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Error\Messages\Message;
use Neos\Flow\Mvc\Exception\NoSuchArgumentException;
use Neos\Flow\Mvc\FlashMessage\FlashMessageService;
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
     * @var NodeService
     */
    protected $nodeService;

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
     * @Flow\Inject
     * @var ApiService
     */
    protected $apiService;

    /**
     * @Flow\Inject
     * @var FlashMessageService
     */
    protected $flashMessageService;

    /**
     * @var array
     */
    protected $viewFormatToObjectNameMap = [
        'html' => FusionView::class,
    ];

    /**
     * Allow invisible nodes to be redirected to
     *
     * @return void
     * @throws NoSuchArgumentException
     */
    protected function initialize(): void
    {
        // use this constant only if available (became available with patch level releases in Neos 4.0 and up)
        if (defined(NodeConverter::class . '::INVISIBLE_CONTENT_SHOWN')) {
            $this->arguments->getArgument('node')->getPropertyMappingConfiguration()->setTypeConverterOption(NodeConverter::class, NodeConverter::INVISIBLE_CONTENT_SHOWN, true);
        }
    }

    /**
     * Render the overview of emails
     *
     * @return void
     */
    public function indexAction()
    {
        $ping = $this->apiService->ping();
        $nodes = $this->nodeService->getNodesByType('Garagist.Mautic:Mixin.Editor');
        $pages = [];
        foreach ($nodes as $node) {
            $title = $node->getProperty('title');
            $parentNode = $this->nodeService->getParentByType($node, 'Garagist.Mautic:Mixin.Subtitle');
            $subtitle = $parentNode ? $parentNode->getProperty('title') : null;
            $pages[] = [
                "count" => count(
                    $this->mauticService->getEmailsNodeIdentifier(
                        $node->getIdentifier()
                    )
                ),
                "node" => $node,
                "title" => $title,
                "subtitle" => $subtitle
            ];
        }

        $this->view->assignMultiple(['pages' => $pages, 'ping' => $ping]);
    }

    /**
     * Update the mautic email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @return void
     */
    public function updateAction(NodeInterface $node, MauticEmail $email): void
    {
        $identifier = $email->getEmailIdentifier();
        if ($email->getTask() == MauticEmail::Idle) {
            $this->mauticService->updateEmailEvent($email);
            $this->addFlashMessage(
                'email.feedback.identifier',
                'email.feedback.updated',
                Message::SEVERITY_OK,
                [$identifier]
            );
        } else {
            $this->addFlashMessage(
                'email.feedback.identifier',
                'email.feedback.updated.failed',
                Message::SEVERITY_ERROR,
                [$identifier]
            );
        }

        $this->redirect('email', null, null, ['node' => $node]);
    }

    /**
     * Publish the mautic email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @return void
     */
    public function publishAction(NodeInterface $node, MauticEmail $email): void
    {
        $identifier = $email->getEmailIdentifier();
        if ($email->getTask() == MauticEmail::Idle) {
            $this->mauticService->publishEmailEvent($email);
            $this->addFlashMessage(
                'email.feedback.identifier',
                'email.feedback.published',
                Message::SEVERITY_OK,
                [$identifier]
            );
        } else {
            $this->addFlashMessage(
                'email.feedback.identifier',
                'email.feedback.published.failed',
                Message::SEVERITY_ERROR,
                [$identifier]
            );
        }

        $this->redirect('email', null, null, ['node' => $node]);
    }

    /**
     * Unpublish the mautic email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @return void
     */
    public function unPublishAction(NodeInterface $node, MauticEmail $email): void
    {
        $identifier = $email->getEmailIdentifier();
        if ($email->getTask() == MauticEmail::Idle) {
            $this->mauticService->unPublishEmailEvent($email);
            $this->addFlashMessage(
                'email.feedback.identifier',
                'email.feedback.unpublished',
                Message::SEVERITY_OK,
                [$identifier]
            );
        } else {
            $this->addFlashMessage(
                'email.feedback.identifier',
                'email.feedback.unpublished.failed',
                Message::SEVERITY_ERROR,
                [$identifier]
            );
        }

        $this->redirect('email', null, null, ['node' => $node]);
    }

    /**
     * Send the mautic email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @return void
     */
    public function sendAction(NodeInterface $node, MauticEmail $email): void
    {
        $identifier = $email->getEmailIdentifier();
        if ($email->getTask() == MauticEmail::Idle) {
            $this->mauticService->sendEmailEvent($email);
            $this->addFlashMessage(
                'email.feedback.identifier',
                'email.feedback.sent',
                Message::SEVERITY_OK,
                [$identifier]
            );
        } else {
            $this->addFlashMessage(
                'email.feedback.identifier',
                'email.feedback.sent.failed',
                Message::SEVERITY_ERROR,
                [$identifier]
            );
        }

        $this->redirect('email', null, null, ['node' => $node]);
    }

    /**
     * Unlock the mautic email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @return void
     */
    public function unlockAction(NodeInterface $node, MauticEmail $email)
    {
        $identifier = $email->getEmailIdentifier();
        if ($email->getTask() == MauticEmail::TaskFailed) {
            $this->mauticService->setTask($email, MauticEmail::Idle);
            $this->addFlashMessage(
                'email.feedback.identifier',
                'email.feedback.unlocked',
                Message::SEVERITY_OK,
                [$identifier]
            );
        } else {
            $this->addFlashMessage(
                'email.feedback.identifier',
                'email.feedback.unlocked.failed',
                Message::SEVERITY_ERROR,
                [$identifier]
            );
        }

        $this->redirect('email', 'Backend', null, ['node' => $node]);
    }

    /**
     * Render the list of emails for a node
     *
     * @param NodeInterface $node
     * @return void
     */
    public function emailAction(NodeInterface $node): void
    {
        $emails = $this->mauticService->getEmailsNodeIdentifier($node->getIdentifier());
        $flashMessages = $this->flashMessageService->getFlashMessageContainerForRequest($this->request)->getMessagesAndFlush();
        $this->view->assignMultiple([
            'emails' => $emails,
            'node' => $node,
            'flashMessages' => $flashMessages
        ]);
    }

    /**
     * Render the information about a mautic email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @return void
     */
    public function infoAction(NodeInterface $node, MauticEmail $email): void
    {
        $mauticRecord = $this->apiService->findEmailByNeosIdentifier($email->getEmailIdentifier());
        $history = $this->mauticService->getAuditLog($email);
        $flashMessages = $this->flashMessageService->getFlashMessageContainerForRequest($this->request)->getMessagesAndFlush();


        $this->view->assignMultiple([
            'email' => $email,
            'node' => $node,
            'history' => $history,
            'mauticRecord' => $mauticRecord,
            'flashMessages' => $flashMessages,
        ]);
    }

    /**
     * Create a new mautic email
     *
     * @param NodeInterface $node
     * @return void
     */
    public function createAction(NodeInterface $node): void
    {
        $linkingService = $this->linkingService;
        $controllerContext = $this->controllerContext;
        $uri = $linkingService->createNodeUri($controllerContext, null, $node, 'html', true, ['maizzle' => true]);
        $this->mauticService->createEmailEvent($node->getIdentifier(), (string)$uri);

        $title = $node->getProperty('title');
        $this->addFlashMessage('', 'email.feedback.created', Message::SEVERITY_OK, [$title]);
        $this->redirect('email', null, null, ['node' => $node], 1);
    }
}
