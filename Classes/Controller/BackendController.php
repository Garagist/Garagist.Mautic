<?php

declare(strict_types=1);

namespace Garagist\Mautic\Controller;

use Collator;
use Garagist\Mautic\Domain\Model\MauticEmail;
use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\MauticService;
use Garagist\Mautic\Service\NodeService;
use Garagist\Mautic\Service\TaskService;
use Garagist\Mautic\Service\TestEmailService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Error\Messages\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\EelHelper\TranslationHelper;
use Neos\Flow\Mvc\Exception\NoSuchArgumentException;
use Neos\Flow\Mvc\FlashMessage\FlashMessageService;
use Neos\Flow\Security\Context;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Neos\Service\LinkingService;
use Neos\Neos\Service\UserService;
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
     * @var TaskService
     */
    protected $taskService;

    /**
     * @Flow\Inject
     * @var TestEmailService
     */
    protected $testEmailService;

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
     * @Flow\Inject
     * @var TranslationHelper
     */
    protected $translationHelper;

    /**
     * @Flow\Inject
     * @var UserService
     */
    protected $userService;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="routeArgument", package="Garagist.Mautic")
     */
    protected $routeArgument;

    /**
     * @var string
     * @Flow\InjectConfiguration(path="mail.trackingPixel", package="Garagist.Mautic")
     */
    protected $trackingPixel;

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

    protected function localSort(array $array, ?string $key = null): array
    {
        $collator = new Collator($this->userService->getInterfaceLanguage());

        uasort($array, function ($a, $b) use ($collator, $key) {
            if (isset($key)) {
                return $collator->compare($a[$key], $b[$key]);
            }
            return $collator->compare($a, $b);
        });

        return $array;
    }

    /**
     * Render the overview of emails
     *
     * @return void
     */
    public function indexAction(): void
    {
        $ping = $this->ping();
        $flashMessages = $this->flashMessageService->getFlashMessageContainerForRequest($this->request)->getMessagesAndFlush();
        $nodes = $this->nodeService->getNodesByType('Garagist.Mautic:Mixin.Email');
        $pages = [];
        $categoryList = [];
        $hasCategories = false;
        foreach ($nodes as $node) {
            $categoryNode = $this->nodeService->getParentByType($node, 'Garagist.Mautic:Mixin.Category');
            $identifier = $node->getIdentifier();
            $categoryIdentifier = $categoryNode ? $categoryNode->getIdentifier() : null;
            $title = $node->getProperty('title');
            $categoryTitle = $categoryNode ? $categoryNode->getProperty('title') : null;
            $count = count($this->mauticService->getEmailsNodeIdentifier(
                $node->getIdentifier()
            ));
            if ($categoryNode) {
                $categoryList[$categoryIdentifier] = $categoryTitle;
                $hasCategories = true;
            }
            $pages[$identifier] = [
                "count" => $count,
                "node" => $node,
                "title" => $title,
                "categoryTitle" => $categoryTitle,
                "categoryIdentifier" => $categoryIdentifier,
            ];
        }

        $pages = $this->localSort($pages, 'title');

        if ($hasCategories) {
            $categories = [];
            $categoryList = $this->localSort($categoryList);
            foreach ($categoryList as $identifier => $title) {
                $items = [];
                foreach ($pages as $item) {
                    if ($identifier == $item['categoryIdentifier']) {
                        $items[] = $item;
                    }
                }
                $categories[$identifier] = [
                    'title' => $title,
                    'main' => $pages[$identifier] ?? null,
                    'pages' =>  $items
                ];
            }

            $noCategory = [];
            foreach ($pages as $identifier => $item) {
                if (!$item['categoryIdentifier'] && !array_key_exists($identifier, $categoryList)) {
                    $noCategory[] = $item;
                }
            }

            if (count($noCategory)) {
                $categories['noCategory'] = [
                    'title' => $this->translationHelper->translate(
                        'pages.noCategory',
                        'Pages without category',
                        [],
                        'Module',
                        'Garagist.Mautic'
                    ),
                    'pages' =>  $noCategory
                ];
            }
        }


        $this->view->assignMultiple(
            [
                'pages' => $pages,
                'categories' => $categories ?? null,
                'ping' => $ping,
                'flashMessages' => $flashMessages,
            ]
        );
    }

    /**
     * Update the mautic email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @param string|null $redirect
     * @return void
     */
    public function updateAction(
        NodeInterface $node,
        MauticEmail $email,
        ?string $redirect = null
    ): void {
        $identifier = $email->getEmailIdentifier();
        if ($email->getTask() == MauticEmail::IDLE) {
            $this->taskService->fireUpdateEmailEvent($email);
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

        $this->redirectCommand($node, $email, $redirect);
    }

    /**
     * Publish the mautic email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @param string|null $redirect
     * @return void
     */
    public function publishAction(
        NodeInterface $node,
        MauticEmail $email,
        ?string $redirect = null
    ): void {
        $identifier = $email->getEmailIdentifier();
        if ($email->getTask() == MauticEmail::IDLE) {
            $this->taskService->firePublishEmailEvent($email);
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

        $this->redirectCommand($node, $email, $redirect);
    }

    /**
     * Unpublish the mautic email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @param string|null $redirect
     * @return void
     */
    public function unpublishAction(
        NodeInterface $node,
        MauticEmail $email,
        ?string $redirect = null
    ): void {
        $identifier = $email->getEmailIdentifier();
        if ($email->getTask() == MauticEmail::IDLE) {
            $this->taskService->fireUnpublishEmailEvent($email);
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

        $this->redirectCommand($node, $email, $redirect);
    }

    /**
     * Send the mautic email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @param string|null $redirect
     * @return void
     */
    public function sendAction(
        NodeInterface $node,
        MauticEmail $email,
        string $date,
        ?string $redirect = null
    ): void {
        // TODO use $date, 'now' is send it immediately
        $identifier = $email->getEmailIdentifier();
        if ($email->getTask() == MauticEmail::IDLE) {
            $this->taskService->fireSendEmailEvent($email);
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

        $this->redirectCommand($node, $email, $redirect);
    }

    /**
     * Unlock the mautic email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @param string|null $redirect
     * @return void
     */
    public function unlockAction(
        NodeInterface $node,
        MauticEmail $email,
        ?string $redirect = null
    ): void {
        $identifier = $email->getEmailIdentifier();
        if ($email->getTask() == MauticEmail::TASK_FAILED) {
            $this->taskService->setTask($email, MauticEmail::IDLE);
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

        $this->redirectCommand($node, $email, $redirect);
    }

    /**
     * Render the list of emails for a node
     *
     * @param NodeInterface $node
     * @return void
     */
    public function nodeAction(NodeInterface $node): void
    {
        $ping = $this->ping();
        $categoryNode = $this->nodeService->getParentByType($node, 'Garagist.Mautic:Mixin.Category');
        $emails = $this->mauticService->getEmailsNodeIdentifier($node->getIdentifier());
        $flashMessages = $this->flashMessageService->getFlashMessageContainerForRequest($this->request)->getMessagesAndFlush();
        $preSelectedSegments = $this->mauticService->getPreSelectedSegments($node);
        $selectableSegments = $this->mauticService->getSelectableSegments($node);
        $allSegments = $this->apiService->getAllSegments();
        $testEmailRecipients = $this->testEmailService->getTestEmailRecipients();
        $this->view->assignMultiple([
            'emails' => $emails,
            'node' => $node,
            'categoryNode' => $categoryNode,
            'preSelectedSegments' => $preSelectedSegments,
            'selectableSegments' => $selectableSegments,
            'allSegments' => $allSegments,
            'flashMessages' => $flashMessages,
            'ping' => $ping,
            'testEmailRecipients' => $testEmailRecipients,
        ]);
    }

    /**
     * Render the details about a mautic email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @return void
     */
    public function detailAction(
        NodeInterface $node,
        MauticEmail $email
    ): void {
        $ping = $this->ping();
        $categoryNode = $this->nodeService->getParentByType($node, 'Garagist.Mautic:Mixin.Category');
        $mauticRecord = $this->apiService->findMauticRecordByEmailIdentifier($email->getEmailIdentifier());
        $history = $this->mauticService->getAuditLog($email);
        $flashMessages = $this->flashMessageService->getFlashMessageContainerForRequest($this->request)->getMessagesAndFlush();
        $testEmailRecipients = $this->testEmailService->getTestEmailRecipients();
        $preSelectedSegments = $this->mauticService->getPreSelectedSegments($node);
        $selectableSegments = $this->mauticService->getSelectableSegments($node);
        $allSegments = $this->apiService->getAllSegments();

        // Disable tracking pixel for the preview
        $mauticRecord['customHtml'] = str_replace($this->trackingPixel, '<!-- Tracking Pixel disabled for preview ' . $this->trackingPixel . '-->', $mauticRecord['customHtml']);

        $this->view->assignMultiple([
            'email' => $email,
            'node' => $node,
            'categoryNode' => $categoryNode,
            'history' => $history,
            'mauticRecord' => $mauticRecord,
            'allSegments' => $allSegments,
            'preSelectedSegments' => $preSelectedSegments,
            'selectableSegments' => $selectableSegments,
            'flashMessages' => $flashMessages,
            'ping' => $ping,
            'testEmailRecipients' => $testEmailRecipients,
        ]);
    }

    public function linkAction(NodeInterface $node): void
    {
        $linkingService = $this->linkingService;
        $controllerContext = $this->controllerContext;
        $uri = $linkingService->createNodeUri(
            $controllerContext,
            null,
            $node,
            'html',
            true,
            []
        );
        $this->redirectToUri($uri);
    }

    /**
     * Create a new mautic email
     *
     * @param NodeInterface $node
     * @param string|null $subject
     * @return void
     */
    public function createAction(
        NodeInterface $node,
        ?string $subject = null,
        ?array $segments = null,
        ?string $previewText = null
    ): void {
        $linkingService = $this->linkingService;
        $controllerContext = $this->controllerContext;
        $title = $node->getProperty('title');
        $convertedSegments = [];
        if (is_array($segments)) {
            foreach ($segments as $segment) {
                $convertedSegments[] = (int)$segment;
            }
        }

        if (!$subject) {
            $titleOverride = $node->getProperty('titleOverride');
            $subject = $titleOverride ? $titleOverride : $title;
        }

        $properties = [
            "sent" => [],
            "subject" => $subject,
            "previewText" => $this->mauticService->cleanPreviewText($previewText),
            "segments" => $convertedSegments,
            "htmlUrl" => $linkingService->createNodeUri(
                $controllerContext,
                null,
                $node,
                'html',
                true,
                [$this->routeArgument['htmlTemplate'] => true]
            ),
            "plaintextUrl" => $linkingService->createNodeUri(
                $controllerContext,
                null,
                $node,
                'html',
                true,
                [$this->routeArgument['plaintextTemplate'] => true]
            )
        ];

        $this->taskService->fireCreateEmailEvent($node->getIdentifier(), $properties);

        $this->addFlashMessage('', 'email.feedback.created', Message::SEVERITY_OK, [$title]);
        $this->redirect('node', null, null, ['node' => $node], 1);
    }

    /**
     * Edit a mautic email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @param string $subject
     * @param array|null $segments
     * @param string|null $redirect
     * @return void
     */
    public function editAction(
        NodeInterface $node,
        MauticEmail $email,
        string $subject,
        ?array $segments = null,
        ?string $previewText = null,
        ?string $redirect = null
    ): void {
        if ($subject) {
            $email->setProperty('subject', $subject);
        }
        $previewText = $this->mauticService->cleanPreviewText($previewText);
        $email->setProperty('previewText', $previewText);
        $hasSegments = is_array($segments) && count($segments);
        if ($hasSegments) {
            $convertedSegments = [];
            foreach ($segments as $value) {
                $convertedSegments[] = (int)$value;
            }
            $email->setProperty('segments', $convertedSegments);
        }
        $feedback = $hasSegments ? 'email.feedback.edited.withSegments' : 'email.feedback.edited';
        $this->taskService->fireUpdateEmailEvent($email);
        $this->addFlashMessage('', $feedback, Message::SEVERITY_OK, []);
        $this->redirectCommand($node, $email, $redirect);
    }

    /**
     * Send test email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @param array $recipients
     * @param string|null $redirect
     * @return void
     */
    public function testAction(
        NodeInterface $node,
        MauticEmail $email,
        array $recipients,
        ?string $redirect = null
    ): void {
        // TODO Implement testAction

        $this->mauticService->sendExampleEmail($email, $recipients);

        $lastRecipient = array_pop($recipients);
        $translationKey = 'email.sent.test.' . (count($recipients) ? 'multiple' : 'one');
        $this->addFlashMessage('', $translationKey, Message::SEVERITY_OK, [$lastRecipient, implode(', ', $recipients)]);

        $this->redirectCommand($node, $email, $redirect);
    }

    /**
     * Publish (if needed) and sends email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @param string|null $redirect
     * @return void
     */
    public function publishAndSendAction(
        NodeInterface $node,
        MauticEmail $email,
        string $date,
        ?string $redirect = null
    ): void {
        // TODO use $date, 'now' is send it immediately
        if (!$email->isPublished()) {
            $this->publishAction($node, $email, 'none');
        }
        $this->sendAction($node, $email, $redirect);
    }

    /**
     * Unpublish (if needed) and update email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @param string|null $redirect
     * @return void
     */
    public function unpublishAndUpdateAction(
        NodeInterface $node,
        MauticEmail $email,
        ?string $redirect = null
    ): void {
        if ($email->isPublished()) {
            $this->unpublishAction($node, $email, 'none');
        }
        $this->updateAction($node, $email, $redirect);
    }

    /**
     * Delete email
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @param string|null $redirect
     * @return void
     */
    public function deleteAction(
        NodeInterface $node,
        MauticEmail $email
    ): void {
        $title = $email->getProperty('subject') ?? $node->getProperty('title');
        $this->taskService->fireDeleteEmailEvent($email);

        $this->addFlashMessage('', 'email.feedback.deleted', Message::SEVERITY_OK, [$title]);
        $this->redirect('node', null, null, ['node' => $node]);
    }


    /**
     * Handles redirects
     *
     * @param NodeInterface $node
     * @param MauticEmail $email
     * @param string|null $redirect
     * @return void
     */
    private function redirectCommand(
        NodeInterface $node,
        MauticEmail $email,
        ?string $redirect = null
    ): void {
        if (!isset($redirect)) {
            $redirect = 'node';
        }
        if ($redirect === 'none') {
            return;
        }

        $this->redirect($redirect, null, null, ['node' => $node, 'email' => $email]);
    }

    /**
     * Checks if a connection is possible to mautic
     *
     * @return boolean
     */
    private function ping(): bool
    {
        $ping = $this->apiService->ping();
        if ($ping === false) {
            $this->addFlashMessage('', 'connection.failed', Message::SEVERITY_ERROR);
        }

        return $ping;
    }
}
