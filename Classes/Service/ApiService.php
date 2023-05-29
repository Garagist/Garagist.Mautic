<?php

declare(strict_types=1);

namespace Garagist\Mautic\Service;

use Garagist\Mautic\Api\Emails;
use Mautic\Api\Campaigns;
use Mautic\Api\Contacts;
use Mautic\Api\Forms;
use Mautic\Api\Pages;
use Mautic\Api\Segments;
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
     * @var Segments
     */
    protected $segmentApi;

    /**
     * @var Campaigns
     */
    protected $campaignApi;

    /**
     * @var Pages
     */
    protected $pageApi;

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
        $this->emailApi = new Emails($auth, $url);
        $this->contactApi = $api->newApi("contacts", $auth, $url);
        $this->formApi = $api->newApi("forms", $auth, $url);
        $this->segmentApi = $api->newApi("segments", $auth, $url);
        $this->campaignApi = $api->newApi("campaigns", $auth, $url);
        $this->pageApi = $api->newApi("pages", $auth, $url);
    }

    /**
     * @param string $emailIdentifier
     * @return void
     *@throws NodeException|Exception
     */
    public function deleteEmail(string $emailIdentifier): void
    {
        $emailRecord = $this->findMauticRecordByEmailIdentifier($emailIdentifier);
        if ($emailRecord) {
            $response = $this->emailApi->delete($emailRecord['id']);
            $this->mauticLogger->info(sprintf('Delete mautic record with identifier %s', $emailIdentifier));
            $this->handleError($response);
        }
    }

    /**
     * @throws NodeException|Exception
     * @return array
     */
    public function alterEmail(string $emailIdentifier, array $data): array
    {

        $emailRecord = $this->findMauticRecordByEmailIdentifier($emailIdentifier);

        if ($emailRecord) { //match found -> update
            $response = $this->emailApi->edit($emailRecord['id'], $data);
            $this->mauticLogger->info(sprintf('Edit mautic record with identifier %s', $emailIdentifier));
        } else { // no match found -> create
            $response = $this->emailApi->create($data);
            $this->mauticLogger->info(sprintf('Create new mautic record with identifier %s', $emailIdentifier));
        }

        $this->handleError($response);

        return $response;
    }

    /**
     * @throws NodeException|Exception
     * @return void
     */
    protected function handleError($response): void
    {
        if (isset($response['error'])) {
            throw new Exception(json_encode($response['error']));
        }
        if (isset($response['errors'])) {
            throw new Exception(json_encode($response['errors']));
        }
    }

    /**
     * @param string $emailIdentifier
     * @return int|null
     */
    public function isEmailPublished(string $emailIdentifier)
    {
        $emailRecord = $this->findMauticRecordByEmailIdentifier($emailIdentifier);

        return $emailRecord['isPublished'] === true ? (int) $emailRecord['id'] : null;
    }

    /**
     * @param string $emailIdentifier
     * @return mixed|null
     */
    public function findMauticRecordByEmailIdentifier(string $emailIdentifier)
    {
        $match = $this->validateResponse($this->emailApi->getList($emailIdentifier));
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
     * @param string $emailIdentifier
     * @param array $recipients
     * @return array
     * @throws Exception
     */
    public function sendTestEmail(string $emailIdentifier, array $recipients): array
    {
        $emailRecord = $this->findMauticRecordByEmailIdentifier($emailIdentifier);

        if (!empty($emailRecord['id'])) {
            //array(3)
            // string "success" (7) => integer 1
            // string "recipients" (16) => integer 0

            $response = $this->validateResponse($this->emailApi->sendExample($emailRecord['id'], $recipients));

            return $response;
        }

        throw new Exception('TestEmail could not be send because it does not exist');
    }

    /**
     * @return array
     */
    public function getAllSegments(): array
    {
        return $this->validateResponse($this->contactApi->getSegments());
    }

    /**
     * Get a single form. Fails gracefully
     *
     * @param integer $id
     * @return array
     */
    public function getForm(int $id): array
    {
        $data = $this->validateResponse($this->formApi->get($id), null, false);
        if (isset($data['form']) && $data['form']['isPublished']) {
            return $data['form'];
        }

        return [];
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
        $hideFormIds = $this->settings['form']['hide'];

        if (is_int($hideFormIds)) {
            $hideFormIds = [$hideFormIds];
        }

        if (!is_array($hideFormIds)) {
            $hideFormIds = [];
        }

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

    public function getFormApi(): Forms
    {
        return $this->formApi;
    }

    public function getContactApi(): Contacts
    {
        return $this->contactApi;
    }

    public function getEmailApi(): Emails
    {
        return $this->emailApi;
    }

    public function getSegmentApi(): Segments
    {
        return $this->segmentApi;
    }

    public function getCampaignApi(): Campaigns
    {
        return $this->campaignApi;
    }

    public function getPageApi(): Pages
    {
        return $this->pageApi;
    }

    /**
     * Validate the response from mautic
     *
     * @param array $response
     * @param string|null $additionalText
     * @param bool $throwExeptions
     * @return array
     */
    protected function validateResponse(array $response, ?string $additionalText = null, bool $throwExeptions = true): array
    {
        if (!isset($additionalText)) {
            $additionalText = '';
        }

        if (isset($response['error'])) {
            $json = json_encode($response['error']);
            $this->mauticLogger->error($additionalText . $json);
            if ($throwExeptions) {
                throw new Exception($json);
            }
        }
        if (isset($response['errors'])) {
            $json = json_encode($response['errors']);
            $this->mauticLogger->error($additionalText . $json);
            if ($throwExeptions) {
                throw new Exception($json);
            }
        }

        return $response;
    }
}
