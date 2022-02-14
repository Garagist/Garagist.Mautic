<?php

declare(strict_types=1);

namespace Garagist\Mautic\Service;

use Garagist\Mautic\Domain\Dto\HistoryItem;
use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\EventSourcing\EventStore\EventEnvelope;
use Neos\Flow\Annotations as Flow;
use Garagist\Mautic\Event\MauticEmailCreate;
use Garagist\Mautic\Event\MauticEmailPublish;
use Garagist\Mautic\Event\MauticEmailSent;
use Garagist\Mautic\Event\MauticEmailSync;
use Garagist\Mautic\Event\MauticEmailTaskFinished;
use Garagist\Mautic\Event\MauticEmailUnPublish;
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
     * @param string $templateUrl
     * @throws \Doctrine\ORM\ORMException
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     */
    public function createEmailEvent(string $nodeIdentifier, string $templateUrl)
    {
        $event = new MauticEmailCreate(Algorithms::generateUUID(), $nodeIdentifier, $templateUrl);
        $streamName = StreamName::fromString('email-' . $event->getEmailIdentifier());

        $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
    }

    public function taskFinishedEvent(MauticEmail $email, string $error = '')
    {
        $event = new MauticEmailTaskFinished($email->getEmailIdentifier(), $email->getNodeIdentifier(), $email->getTask(), $error);
        $streamName = StreamName::fromString('email-' . $email->getEmailIdentifier());

        $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
    }

    /**
     * @param string $emailIdentifier
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function sendEmailEvent(MauticEmail $email): void
    {
        $this->setTask($email, MauticEmail::TASK_SEND);
        $mauticIdentifier = $this->apiService->isEmailPublished($email->getEmailIdentifier());
        if ($mauticIdentifier) {
            $event = new MauticEmailSend($email->getEmailIdentifier(), $mauticIdentifier);
            $streamName = StreamName::fromString('email-' . $email->getEmailIdentifier());

            $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
        } else {
            throw new Exception(sprintf("The Email with node identifier %s could not be send because it's not published. ", $email->getEmailIdentifier()));
        }
    }

    public function updateEmailEvent(MauticEmail $email)
    {
        $this->setTask($email, MauticEmail::TASK_UPDATE);
        $event = new MauticEmailUpdate($email->getEmailIdentifier(), $email->getNodeIdentifier());
        $streamName = StreamName::fromString('email-' . $email->getEmailIdentifier());
        $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
    }

    public function publishEmailEvent(MauticEmail $email)
    {
        $this->setTask($email, MauticEmail::TASK_PUBLISH);
        $event = new MauticEmailPublish($email->getEmailIdentifier(), $email->getNodeIdentifier());
        $streamName = StreamName::fromString('email-' . $email->getEmailIdentifier());
        $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
    }

    public function unPublishEmailEvent(MauticEmail $email)
    {
        $this->setTask($email, MauticEmail::TASK_UN_PUBLISH);
        $event = new MauticEmailUnPublish($email->getEmailIdentifier(), $email->getNodeIdentifier());
        $streamName = StreamName::fromString('email-' . $email->getEmailIdentifier());
        $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
    }

    /**
     * @param MauticEmail $email
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function syncEmailEvent(MauticEmail $email): void
    {
        $event = new MauticEmailSync($email->getEmailIdentifier(), $email->getNodeIdentifier());
        $streamName = StreamName::fromString('email-' . $email->getEmailIdentifier());
        $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
    }

    /**
     * @param string $emailIdentifier
     * @param string $nodeIdentifier
     * @param string $htmlTemplateUrl
     * @param string $plaintextTemplateUrl
     * @return MauticEmail
     * @throws \Doctrine\ORM\ORMException
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     */
    public function saveEmail(string $emailIdentifier, string $nodeIdentifier, string $htmlTemplateUrl, string $plaintextTemplateUrl): MauticEmail
    {

        $email = new MauticEmail();
        $email->setEmailIdentifier($emailIdentifier);
        $email->setHtmlTemplateUrl($htmlTemplateUrl);
        $email->setPlaintextTemplateUrl($plaintextTemplateUrl);
        $email->setNodeIdentifier($nodeIdentifier);
        $email->setDateCreated(new DateTime());
        $email->setPublished(false);
        $email->setTask(MauticEmail::IDLE);

        $this->mauticEmailRepository->add($email);
        $this->persistenceManager->persistAll();

        return $email;
    }

    public function finishTask(MauticEmail $email, bool $failed = false)
    {
        if (!$failed) {
            $this->syncEmail($email);
        }
        $this->setTask($email, $failed ? MauticEmail::TASK_FAILED : MauticEmail::IDLE);
    }

    public function setTask(MauticEmail $email, string $task): void
    {
        $email->setTask($task);
        $this->mauticEmailRepository->update($email);
        $this->persistenceManager->persistAll();

        $this->mauticLogger->info(sprintf('Set currently running task "%s" for email with identifier:%s', $task, $email->getEmailIdentifier()));
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
    public function getByEmailIdentifier(string $emailIdentifier)
    {
        return $this->mauticEmailRepository->findOneByEmailIdentifier($emailIdentifier);
    }

    /**
     * @param MauticEmail $email
     */
    public function updateEmail(MauticEmail $email): void
    {

        $this->mauticLogger->info(sprintf('Update email with identifier:%s', $email->getEmailIdentifier()));

        try {
            $segments = $this->dataProvider->getSegmentsForSendOut($email);
            $data = $this->dataProvider->getDataForSegmentSendOut($email, $segments);
            $this->apiService->alterEmail($email->getEmailIdentifier(), $data);
            $email->setDateModified(new DateTime());
            $this->taskFinishedEvent($email);
        } catch (Exception $e) {
            $this->taskFinishedEvent($email, sprintf('Update email with identifier:%s failed! Reason: %s', $email->getEmailIdentifier(), $e->getMessage()));
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
    public function publishEmail(MauticEmail $email, DateTime $datePublish = null, DateTime $dateUnPublish = null)
    {
        if ($this->apiService->isEmailPublished($email->getEmailIdentifier()) && ($datePublish !== null || $dateUnPublish !== null)) {
            throw new Exception(sprintf("The email with node identifier %s is already published and can therefore not be rescheduled for publishing. ", $email->getEmailIdentifier()));
        } else {
            $data = $datePublish === null ? ['isPublished' => true] : ['publishUp' => $datePublish]; // publish right away or at a sustain date.
            $this->apiService->alterEmail($email->getEmailIdentifier(), $data);

            $email->setPublished(true);
            $this->mauticEmailRepository->update($email);

            $this->mauticLogger->info(sprintf('Published email with identifier:%s', $email->getEmailIdentifier()));
            $this->taskFinishedEvent($email);
        }

        return true;
    }

    /**
     * @param MauticEmail $email
     * @return bool
     * @throws \Neos\ContentRepository\Exception\NodeException
     * @throws Exception
     */
    public function unPublishEmail(MauticEmail $email)
    {
        $data = ['isPublished' => false, 'publishUp' => null, 'publishDown' => null]; // remove all publishing settings
        $this->apiService->alterEmail($email->getEmailIdentifier(), $data);

        $email->setPublished(false);
        $this->mauticEmailRepository->update($email);
        $this->taskFinishedEvent($email);

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
        try {
            $stats = $this->apiService->sendEmail($email->getEmailIdentifier(), $mauticIdentifier);
            $this->mauticLogger->info(sprintf('Sending email with identifier:%s was successful', $email->getEmailIdentifier()));

            $success = (int) $stats['success'];
            $sentCount = (int) $stats['sentCount'];
            $failedRecipients = (int) $stats['failedRecipients'];

            $email->setDateSent(new DateTime());
            $this->mauticEmailRepository->update($email);

            $eventSuccess = new MauticEmailSent($email->getEmailIdentifier(), $mauticIdentifier, $success, $sentCount, $failedRecipients);
            $streamName = StreamName::fromString('email-' . $email->getEmailIdentifier());

            $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($eventSuccess));
        } catch (Exception $e) {
            $this->mauticLogger->error(sprintf('Sending email with identifier:%s failed! Reason:', $e->getMessage()));
        }
    }

    /**
     * @param MauticEmail $email
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Neos\Flow\Persistence\Exception\UnknownObjectException
     */
    public function syncEmail(MauticEmail $email): void
    {
        $emailRecord = $this->apiService->findEmailByNeosIdentifier($email->getEmailIdentifier());
        if ($emailRecord == null) {
            throw new Exception(sprintf('Mautic record with email identifier: %s does not exist and can therefore not be sync.', $email->getEmailIdentifier()));
        }

        $email->setPublished($emailRecord['isPublished']);

        $this->mauticEmailRepository->update($email);
        $this->mauticLogger->info(sprintf('Mautic record with email identifier: %s has been synced.', $email->getEmailIdentifier()));
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
    public function getAuditLog(MauticEmail $email)
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
