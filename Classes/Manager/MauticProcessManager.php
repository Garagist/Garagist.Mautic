<?php
declare(strict_types=1);
namespace Garagist\Mautic\Manager;

use Garagist\Mautic\Event\MauticEmailCreate;
use Garagist\Mautic\Event\MauticEmailSent;
use Garagist\Mautic\Service\MauticService;
use MongoDB\Driver\Exception\ExecutionTimeoutException;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\EventStore\StreamName;
use Neos\Flow\Annotations as Flow;
use Garagist\Mautic\Service\ApiService;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\EventStoreFactory;
use Garagist\Mautic\Event\MauticEmailSend;
use Neos\EventSourcing\EventListener\EventListenerInterface;
use Neos\Flow\Exception;
use Psr\Log\LoggerInterface;

final class MauticProcessManager implements EventListenerInterface
{
    /**
     * @Flow\Inject
     * @var ApiService
     */
    protected $apiService;

    /**
     * @Flow\Inject
     * @var MauticService
     */
    protected $mauticService;

    /**
     * @var EventStore
     */
    protected $eventStore;

    /**
     * @Flow\Inject
     * @var EventStoreFactory
     */
    protected $eventStoreFactory;

    /**
     * @Flow\Inject(name="Garagist.Mautic:MauticLogger")
     * @var LoggerInterface
     */
    protected $mauticLogger;

    protected function initializeObject(): void
    {
        $this->eventStore = $this->eventStoreFactory->create('Garagist.Mautic:EventStore');
    }

    public function whenMauticEmailCreate(MauticEmailCreate $event): void
    {
        $this->mauticLogger->info(sprintf('Creating email with identifier:%s', $event->getNodeIdentifier()));
        try {
            $this->mauticService->saveEmail($event->getNodeIdentifier(), $event->getTemplateUrl());
        } catch (Exception $e) {
            $this->mauticLogger->error(sprintf('Creating email with node identifier:%s failed! Reason:', $e->getMessage()));
        }
    }

    public function whenMauticEmailSend(MauticEmailSend $event): void
    {
        $this->mauticLogger->info(sprintf('Sending email with identifier:%s started', $event->getEmailIdentifier()));

        try {
            $stats = $this->apiService->sendEmail($event->getEmailIdentifier(), $event->getMauticIdentifier());
            $this->mauticLogger->info(sprintf('Sending email with identifier:%s was successful', $event->getEmailIdentifier()));

            $success = (int) $stats['success'];
            $sentCount = (int) $stats['sentCount'];
            $failedRecipients = (int) $stats['failedRecipients'];

            $eventSuccess = new MauticEmailSent($event->getEmailIdentifier(), $event->getMauticIdentifier(), $success, $sentCount, $failedRecipients);
            $streamName = StreamName::fromString('email-' . $event->getEmailIdentifier());

            $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($eventSuccess));

        } catch (Exception $e) {
            $this->mauticLogger->error(sprintf('Sending email with identifier:%s failed! Reason:', $e->getMessage()));
        }
    }
}