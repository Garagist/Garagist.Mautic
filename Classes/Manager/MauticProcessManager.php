<?php

declare(strict_types=1);

namespace Garagist\Mautic\Manager;

use Neos\Flow\Annotations as Flow;
use Garagist\Mautic\Domain\Model\MauticEmail;
use Garagist\Mautic\Event\MauticEmailCreate;
use Garagist\Mautic\Event\MauticEmailPublish;
use Garagist\Mautic\Event\MauticEmailSent;
use Garagist\Mautic\Event\MauticEmailSync;
use Garagist\Mautic\Event\MauticEmailTaskFinished;
use Garagist\Mautic\Event\MauticEmailUnPublish;
use Garagist\Mautic\Event\MauticEmailUpdate;
use Garagist\Mautic\Service\MauticService;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\EventStore\StreamName;
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

    /**
     * @param MauticEmailCreate $event
     * @throws \Doctrine\ORM\ORMException
     */
    public function whenMauticEmailCreate(MauticEmailCreate $event): void
    {
        $this->mauticLogger->info(sprintf('Creating email with identifier:%s', $event->getEmailIdentifier()));
        try {
            $email = $this->mauticService->saveEmail(
                $event->getEmailIdentifier(),
                $event->getNodeIdentifier(),
                $event->getHtmlTemplateUrl(),
                $event->getPlaintextTemplateUrl(),
                $event->getSubject()
            );
            $this->mauticService->updateEmailEvent($email);
        } catch (Exception $e) {
            $this->mauticLogger->error(sprintf('Creating email with node identifier:%s failed! Reason:', $e->getMessage()));
        }
    }

    /**
     * @param MauticEmailUpdate $event
     * @throws \Doctrine\ORM\ORMException
     */
    public function whenMauticEmailUpdate(MauticEmailUpdate $event): void
    {
        $this->mauticLogger->info(sprintf('Updating email with identifier:%s', $event->getNodeIdentifier()));
        $email = $this->mauticService->getByEmailIdentifier($event->getEmailIdentifier());
        $this->mauticService->updateEmail($email);
    }

    /**
     * @param MauticEmailSend $event
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function whenMauticEmailSend(MauticEmailSend $event): void
    {
        $this->mauticLogger->info(sprintf('Sending email with identifier:%s started', $event->getEmailIdentifier()));

        $email = $this->mauticService->getByEmailIdentifier($event->getEmailIdentifier());
        $this->mauticService->sendEmail($email, $event->getMauticIdentifier());
    }

    /**
     * @param MauticEmailSent $event
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Neos\Flow\Persistence\Exception\UnknownObjectException
     */
    public function whenMauticEmailSent(MauticEmailSent $event): void
    {
        $this->mauticLogger->info(sprintf('Sent email with identifier:%s', $event->getEmailIdentifier()));
        $email = $this->mauticService->getByEmailIdentifier($event->getEmailIdentifier());
        $this->mauticService->finishTask($email);
    }

    /**
     * @param MauticEmailSync $event
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Neos\Flow\Persistence\Exception\UnknownObjectException
     */
    public function whenMauticEmailSync(MauticEmailSync $event): void
    {
        $this->mauticLogger->info(sprintf('Syncing email with identifier:%s started', $event->getEmailIdentifier()));
        $email = $this->mauticService->getByEmailIdentifier($event->getEmailIdentifier());
        $this->mauticService->syncEmail($email);
    }

    /**
     * @param MauticEmailSync $event
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Neos\Flow\Persistence\Exception\UnknownObjectException
     */
    public function whenMauticEmailPublish(MauticEmailPublish $event): void
    {
        $this->mauticLogger->info(sprintf('Publish email with identifier:%s started', $event->getEmailIdentifier()));
        $email = $this->mauticService->getByEmailIdentifier($event->getEmailIdentifier());
        $this->mauticService->publishEmail($email);
    }

    /**
     * @param MauticEmailSync $event
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Neos\Flow\Persistence\Exception\UnknownObjectException
     */
    public function whenMauticEmailUnPublish(MauticEmailUnPublish $event): void
    {
        $this->mauticLogger->info(sprintf('UnPublish email with identifier:%s started', $event->getEmailIdentifier()));
        $email = $this->mauticService->getByEmailIdentifier($event->getEmailIdentifier());
        $this->mauticService->unPublishEmail($email);
    }

    /**
     * @param MauticEmailSync $event
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Neos\Flow\Persistence\Exception\UnknownObjectException
     */
    public function whenMauticEmailTaskFinished(MauticEmailTaskFinished $event): void
    {
        if ($event->getError() === '') {
            $this->mauticLogger->info(sprintf('Task "%s" finished with email with identifier:%s', $event->getTask(), $event->getEmailIdentifier()));
        } else {
            $this->mauticLogger->error(sprintf('Task "%s" finished with an error! email identifier:%s - Reason: %s', $event->getTask(), $event->getEmailIdentifier(), $event->getError()));
        }
        $email = $this->mauticService->getByEmailIdentifier($event->getEmailIdentifier());
        $this->mauticService->finishTask($email, $event->getError() !== '');
    }
}
