<?php

declare(strict_types=1);

namespace Garagist\Mautic\Service;

use Garagist\Mautic\Domain\Dto\HistoryItem;
use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\Flow\Annotations as Flow;
use Garagist\Mautic\Event\MauticEmailCreate;
use Garagist\Mautic\Event\MauticEmailPublish;
use Garagist\Mautic\Event\MauticEmailSent;
use Garagist\Mautic\Event\MauticEmailSync;
use Garagist\Mautic\Event\MauticEmailTaskFinished;
use Garagist\Mautic\Event\MauticEmailUnpublish;
use Garagist\Mautic\Event\MauticEmailUpdate;
use Garagist\Mautic\Provider\DataProviderInterface;
use Garagist\Mautic\Domain\Model\MauticEmail;
use Garagist\Mautic\Domain\Repository\MauticEmailRepository;
use Garagist\Mautic\Event\MauticEmailSend;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\EventStoreFactory;
use Neos\EventSourcing\EventStore\StreamName;
use Neos\Flow\Exception;
use Neos\Flow\Http\Client\Browser;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;
use Neos\Flow\Utility\Algorithms;
use ProxyManager\Exception\ExceptionInterface;
use Psr\Log\LoggerInterface;
use DateTime;

/**
 * @Flow\Scope("singleton")
 */
class MauticService
{
    /**
     * @Flow\Inject
     * @var ApiService
     */
    protected $apiService;

    /**
     * @Flow\Inject
     * @var MauticEmailRepository
     */
    protected $mauticEmailRepository;


    /**
     * @Flow\Inject
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var EventStoreFactory
     */
    protected $eventStoreFactory;

    /**
     * @var EventStore
     */
    protected $eventStore;

    /**
     * @Flow\Inject(name="Garagist.Mautic:MauticLogger")
     * @var LoggerInterface
     */
    protected $mauticLogger;

    /**
     * @Flow\Inject
     * @var DataProviderInterface
     */
    protected $dataProvider;

    protected function initializeObject(): void
    {
        $this->eventStore = $this->eventStoreFactory->create('Garagist.Mautic:EventStore');
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

    /**
     * @param string $emailIdentifier
     * @param string $nodeIdentifier
     * @param array $properties
     * @return MauticEmail
     * @throws \Doctrine\ORM\ORMException
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     */
    public function saveEmail(
        string $emailIdentifier,
        string $nodeIdentifier,
        array $properties
    ): MauticEmail {

        $email = new MauticEmail();
        foreach ($properties as $property => $value) {
            $email->setProperty($property, $value);
        }
        $email->setEmailIdentifier($emailIdentifier);
        $email->setNodeIdentifier($nodeIdentifier);
        $email->setDateCreated(new DateTime());
        $email->setPublished(false);
        $email->setTask(MauticEmail::IDLE);

        $this->mauticEmailRepository->add($email);
        $this->persistenceManager->persistAll();

        return $email;
    }

    /**
     * @param MauticEmail $email
     * @param boolean $failed
     * @return void
     */
    public function finishTask(MauticEmail $email, bool $failed = false): void
    {
        if (!$failed) {
            $this->syncEmail($email);
        }
        $this->setTask($email, $failed ? MauticEmail::TASK_FAILED : MauticEmail::IDLE);
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
     * @param string $nodeIdentifier
     * @return mixed
     */
    public function getEmailsNodeIdentifier(string $nodeIdentifier)
    {
        return $this->mauticEmailRepository->findByNodeIdentifier($nodeIdentifier);
    }

    /**
     * @param string $emailIdentifier
     * @return MauticEmail
     */
    public function getByEmailIdentifier(string $emailIdentifier): MauticEmail
    {
        return $this->mauticEmailRepository->findOneByEmailIdentifier($emailIdentifier);
    }

    /**
     * @param MauticEmail $email
     */
    public function updateEmail(MauticEmail $email): void
    {
        $emailIdentifier = $email->getEmailIdentifier();
        try {
            $segments = $this->dataProvider->getSegmentsForSendOut($email);
            $data = $this->dataProvider->getDataForSegmentSendOut($email, $segments);
            $this->apiService->alterEmail($emailIdentifier, $data);
            $email->setDateModified(new DateTime());
            $this->fireTaskFinishedEvent($email);
            $this->mauticLogger->info(sprintf('Update email with identifier %s', $emailIdentifier));
        } catch (Exception $e) {
            $this->fireTaskFinishedEvent($email, sprintf('Update email with identifier %s failed! Reason: %s', $emailIdentifier, $e->getMessage()));
        }
    }

    /**
     * @param MauticEmail $email
     * @param DateTime|null $datePublish
     * @param DateTime|null $dateUnPublish
     * @return bool
     * @throws Exception
     * @throws \Neos\ContentRepository\Exception\NodeException
     */
    public function publishEmail(MauticEmail $email, DateTime $datePublish = null, DateTime $dateUnPublish = null): bool
    {
        $emailIdentifier = $email->getEmailIdentifier();
        if ($this->apiService->isEmailPublished($emailIdentifier) && ($datePublish !== null || $dateUnPublish !== null)) {
            throw new Exception(sprintf("The email with identifier %s is already published and can therefore not be rescheduled for publishing. ", $emailIdentifier));
        } else {
            $data = $datePublish === null ? ['isPublished' => true] : ['publishUp' => $datePublish]; // publish right away or at a sustain date.
            $this->apiService->alterEmail($emailIdentifier, $data);

            $email->setPublished(true);
            $this->mauticEmailRepository->update($email);

            $this->fireTaskFinishedEvent($email);
            $this->mauticLogger->info(sprintf('Published email with identifier %s', $emailIdentifier));
        }

        return true;
    }

    /**
     * @param MauticEmail $email
     * @return bool
     * @throws \Neos\ContentRepository\Exception\NodeException
     * @throws Exception
     */
    public function unPublishEmail(MauticEmail $email): bool
    {
        $data = ['isPublished' => false, 'publishUp' => null, 'publishDown' => null]; // remove all publishing settings
        $this->apiService->alterEmail($email->getEmailIdentifier(), $data);

        $email->setPublished(false);
        $this->mauticEmailRepository->update($email);
        $this->fireTaskFinishedEvent($email);

        return true;
    }

    /**
     * @param MauticEmail $email
     * @param $mauticIdentifier
     * @return void
     * @throws \Neos\ContentRepository\Exception\NodeException
     * @throws Exception
     */
    public function sendEmail(MauticEmail $email, $mauticIdentifier): void
    {
        $emailIdentifier = $email->getEmailIdentifier();
        try {
            $stats = $this->apiService->sendEmail($emailIdentifier, $mauticIdentifier);

            $success = (int) $stats['success'];
            $sentCount = (int) $stats['sentCount'];
            $failedRecipients = (int) $stats['failedRecipients'];

            $email->setDateSent(new DateTime());
            $this->mauticEmailRepository->update($email);

            $eventSuccess = new MauticEmailSent($emailIdentifier, $mauticIdentifier, $success, $sentCount, $failedRecipients);
            $streamName = StreamName::fromString('email-' . $emailIdentifier);

            $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($eventSuccess));
            $this->mauticLogger->info(sprintf('Sending email with identifier %s was successful', $emailIdentifier));
        } catch (Exception $e) {
            $this->mauticLogger->error(sprintf('Sending email with identifier %s failed! Reason: %s', $emailIdentifier, $e->getMessage()));
        }
    }

    /**
     * @param MauticEmail $email
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Neos\Flow\Persistence\Exception\UnknownObjectException
     */
    public function syncEmail(MauticEmail $email): void
    {
        $emailIdentifier = $email->getEmailIdentifier();
        $emailRecord = $this->apiService->findEmailByNeosIdentifier($emailIdentifier);
        if ($emailRecord == null) {
            throw new Exception(sprintf('Mautic record with email identifier %s does not exist and can therefore not be sync.', $emailIdentifier));
        }

        $email->setPublished($emailRecord['isPublished']);

        $this->mauticEmailRepository->update($email);
        $this->mauticLogger->info(sprintf('Mautic record with email identifier %s has been synced.', $emailIdentifier));
    }

    /**
     * @param string $url
     * @return string
     * @throws \Neos\Flow\Http\Client\InfiniteRedirectionException
     */
    public function getNewsletterTemplate(string $url): string
    {
        $browser = new Browser();
        $browser->setRequestEngine(new CurlEngine());

        return $browser->request($url)->getBody()->getContents();
    }

    public function getSegmentsForEmail(MauticEmail $email)
    {
        return $this->dataProvider->getSegmentsForSendOut($email);
    }

    /**
     * @param MauticEmail $email
     * @return array
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getAuditLog(MauticEmail $email): array
    {
        $stream = $this->eventStore->load(StreamName::fromString('email-' . $email->getEmailIdentifier()));

        $history = [];
        while ($stream->valid()) {
            $rawEvent = $stream->current()->getRawEvent();
            $message = '';
            $error = false;
            $type = $stream->current()->getRawEvent()->getType();
            $date = new DateTime();
            $date->setTimestamp($rawEvent->getRecordedAt()->getTimestamp());

            switch (true) {
                case $type === 'Garagist.Mautic:MauticEmailTaskFinished':
                    /** @var MauticEmailTaskFinished $domainEvent */
                    $domainEvent = $stream->current()->getDomainEvent();
                    $message = $domainEvent->getError() ?? '';
                    $error = $domainEvent->getError() !== '';
                    break;

                case $type === 'Garagist.Mautic:MauticEmailSent':
                    /** @var MauticEmailSent $domainEvent */
                    $domainEvent = $stream->current()->getDomainEvent();
                    $message = sprintf('Send: %s | Success: %s | Failed: %s', $domainEvent->getSentCount(), $domainEvent->getSuccess(), $domainEvent->getFailedRecipients());
                    break;
                default:
                    /** @var DomainEventInterface $event */
                    $domainEvent = $stream->current()->getDomainEvent();
            }

            $history[] = new HistoryItem($type, $date, $message, $error);

            $stream->next();
        }

        return $history;
    }
}
