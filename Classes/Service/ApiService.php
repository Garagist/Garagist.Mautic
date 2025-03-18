<?php

namespace Garagist\Mautic\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\RequestOptions;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Exception;
use Psr\Log\LoggerInterface;
use Throwable;
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
    const ENDPOINT_THEMES = 'themes';

    #[Flow\InjectConfiguration('api')]
    protected array $apiSettings;

    /**
     * @Flow\Inject(name="Garagist.Mautic:MauticLogger")
     * @var LoggerInterface
     */
    protected $mauticLogger;

    /**
     * @throws Exception
     */
    protected function initializeObject(): void
    {
        if (
            !isset($this->apiSettings['baseUrl']) ||
            !isset($this->apiSettings['userName']) ||
            !isset($this->apiSettings['password'])
        ) {
            throw new Exception('Mautic API settings are not correct');
        }
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
        foreach ($response['forms'] as $form) {
            $id = $form['id'];
            $data[$id] = $form['name'];
        }

        return $data;
    }

    public function check(mixed $endpoint): bool
    {
        try {
            $this->makeCall($endpoint);
            return true;
        } catch (Throwable $th) {
            return false;
        }
    }

    /**
     * Ping the mautic service
     *
     * @return bool
     */
    public function ping(): bool
    {
        try {
            $this->getList(self::ENDPOINT_EMAILS, limit: 1, throwExeptions: true, ray: false);
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
     * @param bool $ray,
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
        bool $throwExeptions = true,
        bool $ray = true
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
        return $this->makeCall($endpoint, $parameters, throwExeptions: $throwExeptions, ray: $ray);
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
     * Delete an item.
     *
     * @param string $endpoint
     * @param array $ids
     * @return array
     */
    public function deleteBatch(string $endpoint, array $ids): array
    {
        return $this->makeCall([$endpoint, 'batch/delete'], parameters: ['ids' => $ids], method: 'DELETE');
    }

    /**
     * Edit an item with option to create if it doesn't exist.
     *
     * @param string $endpoint
     * @param int|string|null  $id
     * @param array $parameters
     * @param bool $createIfNotExists = false
     *
     * @return array
     */
    public function edit(
        string $endpoint,
        mixed $id = null,
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
        bool $throwExeptions = true,
        bool $ray = true
    ): ?array {
        if (is_array($endpoint)) {
            $endpoint = implode('/', array_filter($endpoint));
        }

        $method = strtoupper($method);
        $endpoint = sprintf('%s/api/%s', rtrim($this->apiSettings['baseUrl'], '/'), ltrim($endpoint, '/'));
        $userName = $this->apiSettings['userName'];
        $password = $this->apiSettings['password'];
        $ignoreHttpsErrors = $this->apiSettings['ignoreHttpsErrors'];

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
            $this->errorHandling('ClientException', $message, $exception, $throwExeptions, $ray);
            $this->mauticLogger->error($message);
        } catch (ServerException $exception) {
            $message = $exception->getResponse()->getBody()->getContents();
            $this->errorHandling('ServerException', $message, $exception, $throwExeptions, $ray);
        }

        return $this->errorCheck($json, $throwExeptions, $ray);
    }

    private function errorHandling(
        ?string $type = null,
        mixed $data = null,
        $exception = null,
        bool $die = true,
        bool $ray = true
    ): void {
        if (function_exists('ray') && $ray) {
            $type = $type ? $type : 'Error';
            ray()
                ->newScreen(sprintf('%s %s', $type, date('H:i:s')))
                ->red();
            if ($exception && $die) {
                ray()->exception($exception)->hide();
            }

            if (is_string($data)) {
                ray()->json($data)->label('Message');
            } elseif ($data) {
                ray()->toJson($data)->label('Message');
            }

            ray()->showApp();
            ray()->die(sprintf('%s, see ray app for more information', $type));
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

    private function errorCheck(
        array $array,
        bool $throwExeptions = true,
        string $title = 'Error',
        bool $ray = true
    ): ?array {
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

        $this->errorHandling($title, $array, null, $throwExeptions, $ray);
        return null;
    }
}
