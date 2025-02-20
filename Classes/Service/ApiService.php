<?php

declare(strict_types=1);

namespace Garagist\Mautic\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\RequestOptions;
use Neos\ContentRepository\Exception\NodeException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Exception;
use Psr\Log\LoggerInterface;
use Throwable;
use function array_pop;
use function in_array;
use function sprintf;

#[Flow\Scope('singleton')]
class ApiService
{
    const ENDPOINT_ASSETS = 'assets';
    const ENDPOINT_CAMPAIGNS = 'campaigns';
    const ENDPOINT_CATEGORIES = 'categories';
    const ENDPOINT_COMPANY_FIELDS = 'fields/company';
    const ENDPOINT_CONTACT_FIELDS = 'fields/contact';
    const ENDPOINT_CONTACTS = 'contacts';
    const ENDPOINT_EMAILS = 'emails';
    const ENDPOINT_FORMS = 'forms';
    const ENDPOINT_NOTIFICATIONS = 'notifications';
    const ENDPOINT_PAGES = 'pages';
    const ENDPOINT_POINTS = 'points';
    const ENDPOINT_POINTS_GROUPS = 'points/groups';
    const ENDPOINT_REPORTS = 'reports';
    const ENDPOINT_SEGMENTS = 'segments';
    const ENDPOINT_SMSES = 'smses';

    #[Flow\InjectConfiguration]
    protected array $settings;

    #[Flow\Inject('Garagist.Mautic:MauticLogger', false)]
    protected LoggerInterface $mauticLogger;

    /**
     * @throws Exception
     */
    protected function initializeObject(): void
    {
        if (
            !isset($this->settings['api']['baseUrl']) ||
            !isset($this->settings['api']['userName']) ||
            !isset($this->settings['api']['password'])
        ) {
            throw new Exception('Mautic API settings are not correct');
        }
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
            $response = $this->delete(self::ENDPOINT_EMAILS, $emailRecord['id']);
            $this->mauticLogger->info(sprintf('Delete mautic record with identifier %s', $emailIdentifier));
            $this->errorCheck($response);
        }
    }

    /**
     * @throws NodeException|Exception
     * @return array
     */
    public function alterEmail(string $emailIdentifier, array $data): array
    {
        $emailRecord = $this->findMauticRecordByEmailIdentifier($emailIdentifier);

        if ($emailRecord) {
            //match found -> update
            $response = $this->edit(self::ENDPOINT_EMAILS, $emailRecord['id'], $data);
            $this->mauticLogger->info(sprintf('Edit mautic record with identifier %s', $emailIdentifier));
        } else {
            // no match found -> create
            $response = $this->create(self::ENDPOINT_EMAILS, $data);
            $this->mauticLogger->info(sprintf('Create new mautic record with identifier %s', $emailIdentifier));
        }

        $this->errorCheck($response, title: 'Error while altering email');

        return $response;
    }

    /**
     * @param string $emailIdentifier
     * @return int|null
     */
    public function isEmailPublished(string $emailIdentifier): ?int
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
        $match = $this->getList(self::ENDPOINT_EMAILS, search: $emailIdentifier);
        if ($match['total'] === 1) {
            //match found
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

            return $this->makeCall([self::ENDPOINT_EMAILS, $mauticIdentifier, 'send'], [], 'POST');
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

            return $this->makeCall(
                [self::ENDPOINT_EMAILS, $emailRecord['id'], 'example'],
                ['recipients' => $recipients],
                'POST'
            );
        }

        throw new Exception('TestEmail could not be send because it does not exist');
    }

    /**
     * @return array
     */
    public function getAllSegments(): array
    {
        return $this->makeCall([self::ENDPOINT_CONTACTS, 'list/segments']);
    }

    /**
     * Get a single form. Fails gracefully
     *
     * @param integer $id
     * @return array
     */
    public function getForm(int $id): array
    {
        $data = $this->makeCall([self::ENDPOINT_FORMS, $id], throwExeptions: false);
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
        $response = $this->getList(self::ENDPOINT_FORMS, orderBy: 'id', publishedOnly: true, throwExeptions: false);

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
            $this->getList(self::ENDPOINT_EMAILS, limit: 1);
            return true;
        } catch (Throwable $th) {
            return false;
        }
    }

    /**
     * Get a list of items
     *
     * @param string $endpoint
     * @param string $search
     * @param int $start
     * @param int $limit
     * @param string $orderBy
     * @param string $orderByDir
     * @param bool $publishedOnly
     * @param bool $minimal
     * @param bool $throwExeptions,
     * @return array
     */
    public function getList(
        string $endpoint,
        string $search = '',
        int $start = 0,
        int $limit = 0,
        string $orderBy = '',
        string $orderByDir = 'ASC',
        bool $publishedOnly = false,
        bool $minimal = false,
        bool $throwExeptions = true
    ): array {
        $parameters = [
            'search' => $search,
            'start' => $start,
            'limit' => $limit,
            'orderBy' => $orderBy,
            'orderByDir' => $orderByDir,
            'publishedOnly' => $publishedOnly,
            'minimal' => $minimal,
        ];

        $parameters = array_filter($parameters);
        return $this->makeCall($endpoint, $parameters, throwExeptions: $throwExeptions);
    }

    /**
     * Create a new item
     *
     * @param string $endpoint
     * @param array $parameters
     * @return array
     */
    public function create(string $endpoint, ?array $parameters = null): array
    {
        return $this->makeCall([$endpoint, 'new'], $parameters, 'POST');
    }

    /**
     * Delete an item.
     *
     * @param string $endpoint
     * @param string|int $id
     * @return array
     */
    public function delete(string $endpoint, string|int $id): array
    {
        return $this->makeCall([$endpoint, $id, 'delete'], method: 'DELETE');
    }

    /**
     * Edit an item with option to create if it doesn't exist.
     *
     * @param string $endpoint
     * @param int|string  $id
     * @param array $parameters
     * @param bool $createIfNotExists = false
     *
     * @return array
     */
    public function edit(
        string $endpoint,
        int|string $id,
        ?array $parameters = null,
        bool $createIfNotExists = false
    ): array {
        $method = $createIfNotExists ? 'PUT' : 'PATCH';
        return $this->makeCall([$endpoint, $id, 'edit'], parameters: $parameters, method: $method);
    }

    /**
     * Make a call to the mautic api
     *
     * @param array|string $endpoint
     * @param array|null $parameters key value pairs.
     * @param string $method GET, POST, DELETE, PATCH, or PUT
     * @param bool $throwExeptions
     * @return array
     * @throws Exception
     */
    public function makeCall(
        array|string $endpoint,
        ?array $parameters = null,
        string $method = 'GET',
        bool $throwExeptions = true
    ): ?array {
        if (is_array($endpoint)) {
            $endpoint = implode('/', $endpoint);
        }

        $method = strtoupper($method);
        $endpoint = sprintf('%s/api/%s', rtrim($this->settings['api']['baseUrl'], '/'), ltrim($endpoint, '/'));
        $userName = $this->settings['api']['userName'];
        $password = $this->settings['api']['password'];
        $ignoreHttpsErrors = $this->settings['api']['ignoreHttpsErrors'];

        $client = new Client(['verify' => !$ignoreHttpsErrors]);
        $options = [
            RequestOptions::HEADERS => [
                'Accepts' => 'application/json',
            ],
            RequestOptions::AUTH => [$userName, $password],
        ];

        if (isset($parameters)) {
            // Call is a post request
            if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
                // We don't send files in the backend, the forms are handled by the frontend
                $options[RequestOptions::HEADERS]['Content-Type'] = 'application/json';
                $options[RequestOptions::JSON] = $parameters;
            } else {
                $options[RequestOptions::QUERY] = $parameters;
            }
        }
        $json = [];
        try {
            $response = $client->request($method, $endpoint, $options);
            $contents = $response->getBody()->getContents();
            $json = json_decode($contents, true);
        } catch (ClientException $exception) {
            $message = $exception->getResponse()->getBody()->getContents();
            $this->errorHandling('ClientException', $message, $exception, $throwExeptions);
            $this->mauticLogger->error($message);
        } catch (ServerException $exception) {
            $message = $exception->getResponse()->getBody()->getContents();
            $this->errorHandling('ServerException', $message, $exception, $throwExeptions);
        }

        return $this->errorCheck($json, $throwExeptions);
    }

    private function errorHandling(?string $type = null, mixed $data = null, $exception = null, bool $die = true): void
    {
        if (function_exists('ray')) {
            $type = $type ? $type : 'Error';
            ray()
                ->newScreen(sprintf('%s %s', $type, date('H:i:s')))
                ->red();
            if ($exception) {
                ray()->exception($exception)->hide();
            }

            if (is_string($data)) {
                ray()->json($data)->label('Message');
            } elseif ($data) {
                ray()->toJson($data)->label('Message');
            }

            ray()->showApp();
            if ($die) {
                ray()->die(sprintf('%s, see ray app for more information', $type));
            }
            return;
        }

        if (is_array($data)) {
            $data = json_encode($data);
        }
        if (!is_string($data)) {
            $data = (string) $data;
        }
        $this->mauticLogger->error($data);

        if ($die) {
            throw new Exception($data, 1739916383);
        }
    }

    private function errorCheck(array $array, bool $throwExeptions = true, string $title = 'Error'): ?array
    {
        $error = isset($array['error']) ? $array['error'] : null;
        $errors = isset($array['errors']) ? $array['errors'] : null;

        if ($error === null && $errors === null) {
            return $array;
        }

        if ($error && $errors) {
            $error = [
                'error' => $error,
                'errors' => $errors,
            ];
        } elseif ($errors) {
            $error = $errors;
        }

        $this->errorHandling($title, $array, null, $throwExeptions);
        return null;
    }
}
