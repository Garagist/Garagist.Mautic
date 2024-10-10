<?php

declare(strict_types=1);

namespace Garagist\Mautic\Service;

use Garagist\Mautic\Domain\Model\MauticEmail;
use Garagist\Mautic\Domain\Repository\MauticEmailRepository;
use Garagist\Mautic\Event\MauticEmailCreate;
use Garagist\Mautic\Event\MauticEmailDelete;
use Garagist\Mautic\Event\MauticEmailPublish;
use Garagist\Mautic\Event\MauticEmailSend;
use Garagist\Mautic\Event\MauticEmailSync;
use Garagist\Mautic\Event\MauticEmailTaskFinished;
use Garagist\Mautic\Event\MauticEmailUnpublish;
use Garagist\Mautic\Event\MauticEmailUpdate;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\EventStoreFactory;
use Neos\EventSourcing\EventStore\StreamName;
use Neos\Flow\Exception;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;
use Neos\Flow\Utility\Algorithms;
use ProxyManager\Exception\ExceptionInterface;
use Psr\Log\LoggerInterface;
use Neos\Flow\Annotations as Flow;

class TaskService
{
    #[Flow\Inject(name: 'Garagist.Mautic:MauticLogger')]
    protected LoggerInterface $mauticLogger;

    /**
     * @var EventStore
     */
    protected $eventStore;

    #[Flow\Inject]
    protected MauticService $mauticService;

    #[Flow\Inject]
    protected ApiService $apiService;

    #[Flow\Inject]
    protected MauticEmailRepository $mauticEmailRepository;

    #[Flow\Inject]
    protected PersistenceManager $persistenceManager;

    #[Flow\Inject]
    protected EventStoreFactory $eventStoreFactory;

    /**
     * @return void
     */
    protected function initializeObject(): void
    {
        $this->eventStore = $this->eventStoreFactory->create('Garagist.Mautic:EventStore');
    }

    /**
     * @param MauticEmail $email
     * @param string $task
     * @return void
     */
    public function setTask(MauticEmail $email, string $task): void
    {
        $email->setTask($task);
        $this->mauticEmailRepository->update($email);
        $this->persistenceManager->persistAll();

        $this->mauticLogger->info(sprintf('Set currently running task "%s" for email with identifier %s', $task, $email->getEmailIdentifier()));
    }

    /**
     * @param MauticEmail $email
     * @param boolean $failed
     * @return void
     */
    public function finishTask(MauticEmail $email, bool $failed = false): void
    {
        if (!$failed) {
            $this->mauticService->syncEmail($email);
        }
        $this->setTask($email, $failed ? MauticEmail::TASK_FAILED : MauticEmail::IDLE);
    }

    /**
     * @param string $nodeIdentifier
     * @param array $properties
     * @throws \Doctrine\ORM\ORMException
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     */
    public function fireCreateEmailEvent(string $nodeIdentifier, array $properties): void
    {
        $event = new MauticEmailCreate(Algorithms::generateUUID(), $nodeIdentifier, $properties);
        $streamName = StreamName::fromString('email-' . $event->getEmailIdentifier());

        $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
    }

    /**
     * @param MauticEmail $email
     * @return void
     */
    public function fireUpdateEmailEvent(MauticEmail $email): void
    {
        $this->setTask($email, MauticEmail::TASK_UPDATE);
        $emailIdentifier = $email->getEmailIdentifier();
        $event = new MauticEmailUpdate($emailIdentifier, $email->getNodeIdentifier(), $email->getProperties());
        $streamName = StreamName::fromString('email-' . $emailIdentifier);
        $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
    }

    /**
     * @param MauticEmail $email
     * @param string $error
     * @return void
     */
    public function fireTaskFinishedEvent(MauticEmail $email, string $error = ''): void
    {
        $event = new MauticEmailTaskFinished($email->getEmailIdentifier(), $email->getNodeIdentifier(), $email->getTask(), $error);
        $streamName = StreamName::fromString('email-' . $email->getEmailIdentifier());

        $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
    }

    /**
     * @param MauticEmail $email
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function fireSendEmailEvent(MauticEmail $email): void
    {
        $this->setTask($email, MauticEmail::TASK_SEND);
        $emailIdentifier = $email->getEmailIdentifier();
        $mauticIdentifier = $this->apiService->isEmailPublished($emailIdentifier);
        if ($mauticIdentifier) {
            $event = new MauticEmailSend($emailIdentifier, $mauticIdentifier);
            $streamName = StreamName::fromString('email-' . $emailIdentifier);

            $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
        } else {
            throw new Exception(sprintf("The email with identifier %s could not be send because it's not published.", $emailIdentifier));
        }
    }

    /**
     * @param MauticEmail $email
     * @return void
     */
    public function firePublishEmailEvent(MauticEmail $email): void
    {
        $this->setTask($email, MauticEmail::TASK_PUBLISH);
        $emailIdentifier = $email->getEmailIdentifier();
        $event = new MauticEmailPublish($emailIdentifier, $email->getNodeIdentifier());
        $streamName = StreamName::fromString('email-' . $emailIdentifier);
        $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
    }

    /**
     * @param MauticEmail $email
     * @return void
     */
    public function fireUnpublishEmailEvent(MauticEmail $email): void
    {
        $this->setTask($email, MauticEmail::TASK_UNPUBLISH);
        $emailIdentifier = $email->getEmailIdentifier();
        $event = new MauticEmailUnpublish($emailIdentifier, $email->getNodeIdentifier());
        $streamName = StreamName::fromString('email-' . $emailIdentifier);
        $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
    }

    /**
     * @param MauticEmail $email
     * @return void
     */
    public function fireDeleteEmailEvent(MauticEmail $email): void
    {
        $this->setTask($email, MauticEmail::TASK_DELETE);
        $emailIdentifier = $email->getEmailIdentifier();
        $event = new MauticEmailDelete($emailIdentifier);
        $streamName = StreamName::fromString('email-' . $emailIdentifier);
        $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
    }

    /**
     * @param MauticEmail $email
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function fireSyncEmailEvent(MauticEmail $email): void
    {
        $emailIdentifier = $email->getEmailIdentifier();
        $event = new MauticEmailSync($emailIdentifier, $email->getNodeIdentifier());
        $streamName = StreamName::fromString('email-' . $emailIdentifier);
        $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
    }
}
