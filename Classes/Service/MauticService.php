<?php

declare(strict_types=1);

namespace Garagist\Mautic\Service;

use Garagist\Mautic\Domain\Dto\HistoryItem;
use Garagist\Mautic\Domain\Model\MauticEmail;
use Garagist\Mautic\Domain\Repository\MauticEmailRepository;
use Garagist\Mautic\Event\MauticEmailSent;
use Garagist\Mautic\Event\MauticEmailTaskFinished;
use Garagist\Mautic\Provider\DataProviderInterface;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\EventStoreFactory;
use Neos\EventSourcing\EventStore\StreamName;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Exception;
use Neos\Flow\Http\Client\Browser;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;
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
     * @var TaskService
     */
    protected $taskService;

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

    /**
     * @Flow\Inject
     * @var TestEmailService
     */
    protected $testEmailService;

    protected function initializeObject(): void
    {
        $this->eventStore = $this->eventStoreFactory->create('Garagist.Mautic:EventStore');
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
     * @param string $nodeIdentifier
     * @return mixed
     */
    public function getEmailsNodeIdentifier(string $nodeIdentifier)
    {
        $result = [];
        $emails = $this->mauticEmailRepository->findByNodeIdentifier($nodeIdentifier);
        foreach ($emails as $email) {
            if (!$email->isDeleted()) {
                $result[] = $email;
            }
        }
        return $result;
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
     * Clean preview text for email
     *
     * @param string|null $text
     * @return string|null
     */
    public function cleanPreviewText(?string $text = null): string
    {
        return $this->cleanString($text);
    }

    /**
     * Get preview text for email
     *
     * @param NodeInterface $node
     * @return string|null
     */
    public function getPreviewTextPlaceholder(NodeInterface $node): string
    {
        $mauticPreviewText = $this->cleanString($node->getProperty('mauticPreviewText'));
        if ($mauticPreviewText) {
            return $mauticPreviewText;
        }
        return $this->cleanString($node->getProperty('metaDescription'));
    }

    /**
     * @param NodeInterface $node
     * @return array
     */
    public function getPrefilledSegments(NodeInterface $node): array
    {
        return $this->dataProvider->getPrefilledSegments($node);
    }

    /**
     * @param MauticEmail $email
     */
    public function updateEmail(MauticEmail $email): void
    {
        $emailIdentifier = $email->getEmailIdentifier();
        try {
            $allSegments = $this->apiService->getAllSegments();

            if (!is_array($allSegments) || count($allSegments) == 0) {
                throw new Exception(sprintf('Mautic has no segments: %s', json_encode($allSegments)), 1662631248);
            }
            $segmentsIds = $this->dataProvider->filterSegments($email, $allSegments);

            // Save recipients to properties
            $recipients = [];
            foreach ($segmentsIds as $id) {
                $recipients[] = $allSegments[$id]['name'];
            }
            $email->setProperty('recipients', implode(', ', $recipients));

            // Get data from provider
            $data = $this->dataProvider->getData($email, $segmentsIds);
            $this->apiService->alterEmail($emailIdentifier, $data);
            $email->setDateModified(new DateTime());
            $this->taskService->fireTaskFinishedEvent($email);
            $this->mauticLogger->info(sprintf('Update email with identifier %s', $emailIdentifier));
        } catch (Exception $e) {
            $this->taskService->fireTaskFinishedEvent($email, sprintf('Update email with identifier %s failed! Reason: %s', $emailIdentifier, $e->getMessage()));
        }
    }

    /**
     * @param MauticEmail $email
     * @param DateTime|null $datePublish
     * @param DateTime|null $dateUnpublish
     * @return bool
     * @throws Exception
     * @throws \Neos\ContentRepository\Exception\NodeException
     */
    public function publishEmail(MauticEmail $email, DateTime $datePublish = null, DateTime $dateUnpublish = null): bool
    {
        $emailIdentifier = $email->getEmailIdentifier();
        if ($this->apiService->isEmailPublished($emailIdentifier) && ($datePublish !== null || $dateUnpublish !== null)) {
            throw new Exception(sprintf("The email with identifier %s is already published and can therefore not be rescheduled for publishing. ", $emailIdentifier));
        } else {
            $data = $datePublish === null ? ['isPublished' => true] : ['publishUp' => $datePublish]; // publish right away or at a sustain date.
            $this->apiService->alterEmail($emailIdentifier, $data);

            $email->setPublished(true);
            $this->mauticEmailRepository->update($email);
            $this->taskService->fireTaskFinishedEvent($email);
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
    public function unpublishEmail(MauticEmail $email): bool
    {
        $data = ['isPublished' => false, 'publishUp' => null, 'publishDown' => null]; // remove all publishing settings
        $this->apiService->alterEmail($email->getEmailIdentifier(), $data);

        $email->setPublished(false);
        $this->mauticEmailRepository->update($email);
        $this->taskService->fireTaskFinishedEvent($email);

        return true;
    }

    /**
     * @param MauticEmail $email
     * @return void
     * @throws \Neos\ContentRepository\Exception\NodeException
     * @throws Exception
     */
    public function deleteEmail(MauticEmail $email): void
    {
        $emailIdentifier = $email->getEmailIdentifier();
        try {
            $this->apiService->deleteEmail($emailIdentifier);
            $email->setDeleted(true);
            $this->taskService->fireTaskFinishedEvent($email, '');
        } catch (Exception $e) {
            $this->taskService->fireTaskFinishedEvent($email, sprintf('Delete email with identifier %s failed! Reason: %s', $emailIdentifier, $e->getMessage()));
        }
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

            // Add sent date to email
            $sent = $email->getProperty('sent') || [];
            $sent[] = time();
            $email->setProperty('sent', $sent);

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
     * @param array $recipients
     * @return void
     */
    public function sendExampleEmail(MauticEmail $email, array $recipients = []): void
    {
        $emailIdentifier = $email->getEmailIdentifier();

        if (empty($recipients)) {
            $recipients = $this->testEmailService->getTestEmailRecipients();
        }

        try {
            $this->apiService->sendTestEmail($emailIdentifier, $recipients);
        } catch (Exception $e) {
            $this->mauticLogger->error(sprintf('Sending test email with identifier %s failed! Reason: %s', $emailIdentifier, $e->getMessage()));
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
        $emailRecord = $this->apiService->findMauticRecordByEmailIdentifier($emailIdentifier);
        if ($emailRecord != null) {
            $email->setPublished($emailRecord['isPublished']);
            $this->mauticEmailRepository->update($email);
            $this->mauticLogger->info(sprintf('Mautic record with email identifier %s has been synced.', $emailIdentifier));
        }
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
        return $this->dataProvider->filterSegments($email);
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

    /**
     * Remove double whitespaces and newlines from string
     *
     * @param string|null $string
     * @return string
     */
    protected function cleanString(?string $string = null): string
    {
        if (!$string) {
            return '';
        }
        $space = ' ';
        $string = (string) str_replace('&nbsp;', $space, $string);

        return trim(
            preg_replace(
                '/\s\s+/',
                $space,
                str_replace(
                    ' ',
                    $space,
                    $string
                )
            )
        );
    }
}
