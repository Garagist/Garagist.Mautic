<?php
declare(strict_types=1);

namespace Garagist\Mautic\Service;

use Garagist\Mautic\Event\MauticEmailCreate;
use Neos\Flow\Annotations as Flow;
use Garagist\Mautic\Domain\Model\MauticEmail;
use Garagist\Mautic\Domain\Repository\MauticEmailRepository;
use Garagist\Mautic\Events\MauticEmailSend;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\EventStoreFactory;
use Neos\EventSourcing\EventStore\StreamName;
use Neos\Flow\Exception;
use Neos\Flow\Http\Client\Browser;
use Neos\Flow\Http\Client\CurlEngine;
use ProxyManager\Exception\ExceptionInterface;
use Psr\Log\LoggerInterface;

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
    public function createEmail(string $nodeIdentifier, string $templateUrl) {
        $event = new MauticEmailCreate($nodeIdentifier,$templateUrl);
        $streamName = StreamName::fromString('email-create-' . $nodeIdentifier);

        $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
    }

    public function saveEmail(string $nodeIdentifier, string $templateUrl) {
        $email = new MauticEmail();
        $email->setTemplateUrl($templateUrl);
        $email->setNodeIdentifier($nodeIdentifier);

        $this->mauticEmailRepository->add($email);
    }

    /**
     * @param string $emailIdentifier
     * @return MauticEmail|null
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function getEmail(string $emailIdentifier) {
        return $this->mauticEmailRepository->findByIdentifier($emailIdentifier);
    }

    /**
     * @param string $nodeIdentifier
     * @return mixed
     */
    public function getEmails(string $nodeIdentifier) {
        return $this->mauticEmailRepository->findByNodeIdentifier($nodeIdentifier);
    }

    /**
     * @param string $emailIdentifier
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function sendEmail(string $emailIdentifier): void
    {
        $mauticIdentifier = $this->apiService->isEmailPublished($emailIdentifier);
        if($mauticIdentifier) {
            $event = new \Garagist\Mautic\Event\MauticEmailSend($emailIdentifier,$mauticIdentifier);
            $streamName = StreamName::fromString('email-' . $emailIdentifier);

            $this->eventStore->commit($streamName, DomainEvents::withSingleEvent($event));
        } else {
            throw new Exception(sprintf("The Email with node identifier %s could not be send because it's not published. ", $emailIdentifier));
        }
    }

    /**
     * @param string $emailIdentifier
     * @param array $data
     * @return bool
     */
    public function updateEmail(string $emailIdentifier, array $data) {

        $this->mauticLogger->info(sprintf('Update email with identifier:%s', $emailIdentifier));

        try {
            $this->apiService->alterEmail($emailIdentifier, $data);
        } catch (Exception $e) {
            $this->mauticLogger->error(sprintf('Update email with identifier:%s failed! Reason: %s', $emailIdentifier, $e->getMessage()));
        }

        return true;
    }

    /**
     * @throws \Neos\ContentRepository\Exception\NodeException
     * @throws Exception
     *
     * @return bool
     */
    public function publishEmail(string $emailIdentifier, \DateTime $datePublish = null, \DateTime $dateUnPublish = null) {

        if($this->apiService->isEmailPublished($emailIdentifier) && ($datePublish !== null || $dateUnPublish !== null)) {
            throw new Exception(sprintf("The Email with node identifier %s is already published and can therefore not be rescheduled for publising. ", $emailIdentifier));
        } else {
            $data = $datePublish === null ? ['isPublished' => true] : ['publishUp' => $datePublish]; // publish right away or at a sustain date.
            $this->apiService->alterEmail($emailIdentifier, $data);
            $this->mauticLogger->info(sprintf('Published email with identifier:%s', $emailIdentifier));
        }

        return true;
    }

    /**
     * @param string $emailIdentifier
     * @return bool
     *@throws \Neos\ContentRepository\Exception\NodeException
     *
     * @throws Exception
     */
    public function unPublishEamil(string $emailIdentifier) {
        $data = ['isPublished' => false, 'publishUp' => null, 'publishDown' => null]; // remove all publishing settings
        $this->apiService->alterEmail($emailIdentifier, $data);

        return true;
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
}