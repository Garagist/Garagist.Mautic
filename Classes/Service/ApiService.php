<?php

declare(strict_types=1);

namespace Garagist\Mautic\Service;

use Garagist\Mautic\Domain\Dto\Segment;
use Mautic\Api\Contacts;
use Mautic\Api\Emails;
use Mautic\Api\Forms;
use Mautic\Auth\ApiAuth;
use Mautic\Auth\AuthInterface;
use Mautic\Exception\ContextNotFoundException;
use Mautic\MauticApi;
use Neos\ContentRepository\Exception\NodeException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Exception;
use Psr\Log\LoggerInterface;
use Throwable;
use function array_pop;
use function in_array;
use function sprintf;

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
     * @var Forms
     */
    protected $formApi;

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
        $url = $this->settings['api']['baseUrl'] . '/api/';
        $this->emailApi = $api->newApi("emails", $auth, $url);
        $this->contactApi = $api->newApi("contacts", $auth, $url);
        $this->formApi = $api->newApi("forms", $auth, $url);
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
            $this->mauticLogger->info(sprintf('Edit mautic record with identifier %s', $nodeIdentifier));
        } else { // no match found -> create
            $response = $this->emailApi->create($data);
            $this->mauticLogger->info(sprintf('Create new mautic record with identifier %s', $nodeIdentifier));
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
        $match
            = $this->validateResponse($this->emailApi->getList($neosIdentifier));

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

            $response = $this->validateResponse($this->emailApi->send($mauticIdentifier));

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
        $segments = $this->validateResponse($this->contactApi->getSegments());
        foreach ($segments as $segment) {
            $data[] = new Segment($segment['id'], $segment['name'], $segment['alias']);
        }

        return $data;
    }

    /**
     * Get the list of all forms
     *
     * @return array
     */
    public function getForms(): array
    {
        $response = $this->validateResponse($this->formApi->getList('', 0, 0, 'id', 'ASC', true));

        if ($response['total'] === 0) {
            return [];
        }

        $data = [];
        $hideFormIds = $this->settings['forms']['hideIds'];

        foreach ($response['forms'] as $form) {
            $id = $form['id'];
            if (!in_array($id, $hideFormIds)) {
                $data[$id] = $form['name'];
            }
        }

        return $data;
    }

    /**
     * Ping the mautic service
     *
     * @return bool
     */
    public function ping(): bool
    {
        try {
            $this->validateResponse($this->emailApi->getList('', 0, 1));
            return true;
        } catch (Throwable $th) {
            return false;
        }
    }

    /**
     * Validate the response from mautic
     *
     * @param array $response
     * @param string|null $additionalText
     * @return array
     */
    protected function validateResponse(array $response, ?string $additionalText = null): array
    {
        if (!isset($additionalText)) {
            $additionalText = '';
        }

        if (isset($response['error'])) {
            $json = json_encode($response['error']);
            $this->mauticLogger->error($additionalText . $json);
            throw new Exception($response['error']);
        }
        if (isset($response['errors'])) {
            $json = json_encode($response['errors']);
            $this->mauticLogger->error($additionalText . $json);
            throw new Exception($response['errors']);
        }

        return $response;
    }
}
