<?php

namespace Garagist\Mautic\Service;

use Carbon\Newsletter\Service\NodeService;
use Garagist\Mautic\Service\SettingsService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\RequestOptions;
use Neos\ContentRepository\Domain\Model\NodeInterface;
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

    #[Flow\InjectConfiguration('api.ignoreHttpsErrors')]
    protected bool $ignoreHttpsErrors;

    #[Flow\Inject]
    protected NodeService $nodeService;

    #[Flow\Inject]
    protected SettingsService $settingsService;

    /**
     * @Flow\Inject(name="Garagist.Mautic:MauticLogger")
     * @var LoggerInterface
     */
    protected $mauticLogger;

    /**
     * Ping the mautic service
     *
     * @param array|null $apiSettings api settings
     * @return bool
     */
    public function ping(?array $apiSettings = null): bool
    {
        if (empty($apiSettings)) {
            return false;
        }
        try {
            $this->getList(
                $apiSettings,
                self::ENDPOINT_EMAILS,
                limit: 1,
                throwExeptions: true,
                ray: false
            );
            return true;
        } catch (Throwable $th) {
            return false;
        }
    }

    /**
     * Get a list of items
     *
     * @param array $apiSettings
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
     * @param NodeInterface|null $node
     * @param array|null $apiSettings api settings
     * @return array
     */
    public function getList(
        array $apiSettings,
        string $endpoint,
        string $search = '',
        int $start = 0,
        int $limit = 0,
        string $orderBy = '',
        string $orderByDir = 'ASC',
        bool $publishedOnly = false,
        bool $minimal = false,
        bool $throwExeptions = true,
        bool $ray = true,
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
        return $this->makeCall(
            $apiSettings,
            $endpoint,
            $parameters,
            throwExeptions: $throwExeptions,
            ray: $ray,
        );
    }

    /**
     * Create a new item
     *
     * @param array $apiSettings
     * @param string $endpoint
     * @param array $parameters
     * @return array
     */
    public function create(
        array $apiSettings,
        string $endpoint,
        ?array $parameters = null
    ): array {
        return $this->makeCall($apiSettings, [$endpoint, 'new'], $parameters, 'POST');
    }

    /**
     * Delete an item.
     *
     * @param string $endpoint
     * @param string|int $id
     * @param array|null $apiSettings api settings
     * @return array
     */
    public function delete(
        array $apiSettings,
        string $endpoint,
        string|int $id,
    ): array {
        return $this->makeCall($apiSettings, [$endpoint, $id, 'delete'], method: 'DELETE');
    }

    /**
     * Delete a batch of items.
     *
     * @param string $endpoint
     * @param array $ids
     * @param NodeInterface|null $node
     * @param array|null $apiSettings api settings
     * @return array
     */
    public function deleteBatch(
        string $endpoint,
        array $ids,
        ?NodeInterface $node = null,
        ?array $apiSettings = null
    ): array {
        return $this->makeCall(
            $apiSettings,
            [$endpoint, 'batch/delete'],
            parameters: ['ids' => $ids],
            method: 'DELETE',
            node: $node,
        );
    }

    /**
     * Edit an item with option to create if it doesn't exist.
     *
     * @param string $endpoint
     * @param int|string|null  $id
     * @param array $parameters
     * @param NodeInterface|null $node
     * @param array|null $apiSettings api settings
     * @param bool $createIfNotExists = false
     *
     * @return array
     */
    public function edit(
        array $apiSettings,
        string $endpoint,
        mixed $id = null,
        ?array $parameters = null,
        bool $createIfNotExists = false,
    ): array {
        $method = $createIfNotExists ? 'PUT' : 'PATCH';
        return $this->makeCall(
            $apiSettings,
            [$endpoint, $id, 'edit'],
            parameters: $parameters,
            method: $method,
        );
    }

    /**
     * Make a call to the mautic api
     *
     * @param array $apiSettings
     * @param array|string $endpoint
     * @param array|null $parameters key value pairs.
     * @param string $method GET, POST, DELETE, PATCH, or PUT
     * @param bool $throwExeptions
     * @param bool $ray
     * @return array
     * @throws Exception
     */
    public function makeCall(
        $apiSettings,
        array|string $endpoint,
        ?array $parameters = null,
        string $method = 'GET',
        bool $throwExeptions = true,
        bool $ray = true,
    ): ?array {
        if (is_array($endpoint)) {
            $endpoint = implode('/', array_filter($endpoint));
        }

        $method = strtoupper($method);
        $endpoint = sprintf('%s/api/%s', rtrim($apiSettings['url'], '/'), ltrim($endpoint, '/'));
        $ignoreHttpsErrors = $this->ignoreHttpsErrors;

        $client = new Client(['verify' => !$ignoreHttpsErrors]);
        $options = [
            RequestOptions::HEADERS => [
                'Accepts' => 'application/json',
            ],
            RequestOptions::AUTH => [$apiSettings['username'], $apiSettings['password']],
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
