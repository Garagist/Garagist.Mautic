<?php

declare(strict_types=1);

namespace Garagist\Mautic\Manager;

use Garagist\Mautic\Service\TaskService;
use Neos\Flow\Annotations as Flow;
use Garagist\Mautic\Event\MauticEmailCreate;
use Garagist\Mautic\Event\MauticEmailPublish;
use Garagist\Mautic\Event\MauticEmailSent;
use Garagist\Mautic\Event\MauticEmailSync;
use Garagist\Mautic\Event\MauticEmailDelete;
use Garagist\Mautic\Event\MauticEmailTaskFinished;
use Garagist\Mautic\Event\MauticEmailUnpublish;
use Garagist\Mautic\Event\MauticEmailUpdate;
use Garagist\Mautic\Service\MauticService;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\EventStoreFactory;
use Garagist\Mautic\Event\MauticEmailSend;
use Neos\EventSourcing\EventListener\EventListenerInterface;
use Neos\Flow\Exception;
use Psr\Log\LoggerInterface;

final class MauticProcessManager implements EventListenerInterface
{
    #[Flow\Inject]
    protected MauticService $mauticService;

    #[Flow\Inject]
    protected TaskService $taskService;

    #[Flow\Inject]
    protected EventStore $eventStore;

    #[Flow\Inject]
    protected EventStoreFactory $eventStoreFactory;

    #[Flow\Inject(name: 'Garagist.Mautic:MauticLogger')]
    protected LoggerInterface $mauticLogger;

    protected function initializeObject(): void
    {
        $this->eventStore = $this->eventStoreFactory->create('Garagist.Mautic:EventStore');
    }

    /**
     * @param MauticEmailCreate $event
     * @throws \Doctrine\ORM\ORMException
     */
    public function whenMauticEmailCreate(MauticEmailCreate $event): void
    {
        $emailIdentifier = $event->getEmailIdentifier();
        try {
            $email = $this->mauticService->saveEmail(
                $event->getEmailIdentifier(),
                $event->getNodeIdentifier(),
                $event->getProperties()
            );
            $this->taskService->fireUpdateEmailEvent($email);
            $this->mauticLogger->info(sprintf('Creating email with identifier %s', $emailIdentifier));
        } catch (Exception $e) {
            $this->mauticLogger->error(sprintf('Creating email with identifier %s failed! Reason: %s', $emailIdentifier, $e->getMessage()));
        }
    }

    /**
     * @param MauticEmailUpdate $event
     * @throws \Doctrine\ORM\ORMException
     */
    public function whenMauticEmailUpdate(MauticEmailUpdate $event): void
    {
        $emailIdentifier = $event->getEmailIdentifier();
        $this->mauticLogger->info(sprintf('Updating email with identifier %s', $emailIdentifier));
        $email = $this->mauticService->getByEmailIdentifier($emailIdentifier);
        $this->mauticService->updateEmail($email);
    }

    /**
     * @param MauticEmailSend $event
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function whenMauticEmailSend(MauticEmailSend $event): void
    {
        $emailIdentifier = $event->getEmailIdentifier();
        $this->mauticLogger->info(sprintf('Sending email with identifier %s started', $emailIdentifier));

        $email = $this->mauticService->getByEmailIdentifier($emailIdentifier);
        $this->mauticService->sendEmail($email, $event->getMauticIdentifier());
    }

    /**
     * @param MauticEmailSent $event
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Neos\Flow\Persistence\Exception\UnknownObjectException
     */
    public function whenMauticEmailSent(MauticEmailSent $event): void
    {
        $emailIdentifier = $event->getEmailIdentifier();
        $this->mauticLogger->info(sprintf('Sent email with identifier %s', $emailIdentifier));
        $email = $this->mauticService->getByEmailIdentifier($emailIdentifier);
        $this->taskService->finishTask($email);
    }

    /**
     * @param MauticEmailSync $event
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Neos\Flow\Persistence\Exception\UnknownObjectException
     */
    public function whenMauticEmailSync(MauticEmailSync $event): void
    {
        $emailIdentifier = $event->getEmailIdentifier();
        if ($emailIdentifier) {
            $this->mauticLogger->info(sprintf('Syncing email with identifier %s started', $emailIdentifier));
            $email = $this->mauticService->getByEmailIdentifier($emailIdentifier);
            $this->mauticService->syncEmail($email);
        }
    }

    /**
     * @param MauticEmailPublish $event
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Neos\Flow\Persistence\Exception\UnknownObjectException
     */
    public function whenMauticEmailPublish(MauticEmailPublish $event): void
    {
        $emailIdentifier = $event->getEmailIdentifier();
        $this->mauticLogger->info(sprintf('Publish email with identifier %s started', $emailIdentifier));
        $email = $this->mauticService->getByEmailIdentifier($emailIdentifier);
        $this->mauticService->publishEmail($email);
    }

    /**
     * @param MauticEmailUnpublish $event
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Neos\Flow\Persistence\Exception\UnknownObjectException
     */
    public function whenMauticEmailUnpublish(MauticEmailUnpublish $event): void
    {
        $emailIdentifier = $event->getEmailIdentifier();
        $this->mauticLogger->info(sprintf('Unpublish email with identifier %s started', $emailIdentifier));
        $email = $this->mauticService->getByEmailIdentifier($emailIdentifier);
        $this->mauticService->unpublishEmail($email);
    }

    /**
     * @param MauticEmailDelete $event
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Neos\Flow\Persistence\Exception\UnknownObjectException
     */
    public function whenMauticEmailDelete(MauticEmailDelete $event): void
    {
        $emailIdentifier = $event->getEmailIdentifier();
        $this->mauticLogger->info(sprintf('Delete email with identifier %s started', $emailIdentifier));
        $email = $this->mauticService->getByEmailIdentifier($emailIdentifier);
        $this->mauticService->deleteEmail($email);
    }

    /**
     * @param MauticEmailTaskFinished $event
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Neos\Flow\Persistence\Exception\UnknownObjectException
     */
    public function whenMauticEmailTaskFinished(MauticEmailTaskFinished $event): void
    {
        $emailIdentifier = $event->getEmailIdentifier();
        $task = $event->getTask();
        $error = $event->getError();
        if ($error === '') {
            $this->mauticLogger->info(sprintf('Task "%s" finished with email %s', $task, $emailIdentifier));
        } else {
            $this->mauticLogger->error(sprintf('Task "%s" finished with an error! Email %s - Reason: %s', $task, $emailIdentifier, $error));
        }
        $email = $this->mauticService->getByEmailIdentifier($emailIdentifier);
        $this->taskService->finishTask($email, $error !== '');
    }
}
