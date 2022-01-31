<?php

declare(strict_types=1);

namespace Garagist\Mautic\Service;

use Garagist\Mautic\Domain\Dto\Segment;
use Mautic\Api\Contacts;
use Neos\Flow\Annotations as Flow;
use Mautic\Api\Emails;
use Mautic\Exception\ContextNotFoundException;
use Neos\ContentRepository\Exception\NodeException;
use Mautic\Auth\AuthInterface;
use Mautic\MauticApi;
use Mautic\Auth\ApiAuth;
use Neos\Flow\Exception;
use Psr\Log\LoggerInterface;

/**
 * @Flow\Scope("singleton")
 */
class ApiService
{
    /**
     * @Flow\InjectConfiguration
     * @var array
     */
    protected $settings = [];

    /**
     * @var AuthInterface
     */
    protected $auth;

    /**
     * @var MauticApi
     */
    protected $api;

    /**
     * @var Emails
     */
    protected $emailApi;

    /**
     * @var Contacts
     */
    protected $contactApi;

    /**
     * @Flow\Inject(name="Garagist.Mautic:MauticLogger")
     * @var LoggerInterface
     */
    protected $mauticLogger;

    /**
     * @throws Exception
     * @throws ContextNotFoundException
     */
    protected function initializeObject(): void
    {
        if (
            !isset($this->settings['api']['baseUrl']) ||
            !isset($this->settings['api']['userName']) ||
            !isset($this->settings['api']['password'])
        ) {
            throw new Exception('Mautic api settings are not correct');
        }

        $initAuth = new ApiAuth();
        $auth = $initAuth->newAuth($this->settings['api'], 'BasicAuth');

        $api = new MauticApi();
        $this->emailApi = $api->newApi("emails", $auth, $this->settings['api']['baseUrl'] . '/api/');
        $this->contactApi = $api->newApi("contacts", $auth, $this->settings['api']['baseUrl'] . '/api/');
    }

    /**
     * @throws NodeException|Exception
     * @return array
     */
    public function alterEmail(string $nodeIdentifier, array $data)
    {

        $emailRecord = $this->findEmailByNeosIdentifier($nodeIdentifier);

        if ($emailRecord) { //match found -> update
            $response = $this->emailApi->edit($emailRecord['id'], $data);
            $this->mauticLogger->info(sprintf('Edit mautic record with identifier:%s', $nodeIdentifier));
        } else { // no match found -> create
            $response = $this->emailApi->create($data);
            $this->mauticLogger->info(sprintf('Create new mautic record with identifier:%s', $nodeIdentifier));
        }

        if (isset($response['error'])) {
            throw new Exception($response['error']['message']);
        }

        return $response;
    }

    /**
     * @param string $nodeIdentifier
     * @return int|null
     */
    public function isEmailPublished(string $nodeIdentifier)
    {
        $emailRecord = $this->findEmailByNeosIdentifier($nodeIdentifier);

        return $emailRecord['isPublished'] === true ? (int) $emailRecord['id'] : null;
    }

    /**
     * @param string $neosIdentifier
     * @return mixed|null
     */
    public function findEmailByNeosIdentifier(string $neosIdentifier)
    {
        $match = $this->emailApi->getList($neosIdentifier);

        if ($match['total'] === 1) { //match found
            return array_pop($match['emails']);
        }

        return null;
    }

    /**
     * @param string $emailIdentifier
     * @return array
     * @throws Exception
     */
    public function sendEmail(string $emailIdentifier, $mauticIdentifier): array
    {
        $mauticIdentifier = $this->isEmailPublished($emailIdentifier);

        if ($mauticIdentifier) {
            //TODO: new contacts, that are in the same list, will be added as pending contacts at any point in time. Therefore it's hard to say when a send out is done
            //array(3)
            // string "success" (7) => integer 1
            // string "sentCount" (9) => integer 0
            // string "failedRecipients" (16) => integer 0

            $response = $this->emailApi->send($mauticIdentifier);

            if (isset($response['error'])) {
                throw new Exception($response['error']['message']);
            }

            return $response;
        }

        throw new Exception('Email could not be send because it does not exist or ist not published');
    }

    /**
     * @return Segment[]
     */
    public function getAllSegments(): array
    {
        $data = [];
        $segments = $this->contactApi->getSegments();
        foreach ($segments as $segment) {
            $data[] = new Segment($segment['id'], $segment['name'], $segment['alias']);
        }

        return $data;
    }

    public function ping()
    {
        $response = $this->emailApi->getList('', 0, 1);

        if (isset($response['error'])) {
            $this->mauticLogger->alert('Could not ping mauitc api. Reason:' . json_encode($response['error']));
            return false;
        }

        return true;
    }
}
