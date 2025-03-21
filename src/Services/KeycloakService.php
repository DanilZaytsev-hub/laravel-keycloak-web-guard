<?php

namespace Vizir\KeycloakWebGuard\Services;

use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Vizir\KeycloakWebGuard\Auth\KeycloakAccessToken;
use Vizir\KeycloakWebGuard\Auth\Guard\KeycloakWebGuard;

class KeycloakService
{
    /**
     * The Session key for token
     */
    const KEYCLOAK_SESSION = '_keycloak_token';

    /**
     * The Session key for state
     */
    const KEYCLOAK_SESSION_STATE = '_keycloak_state';

    /**
     * Keycloak URL
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Keycloak Realm
     *
     * @var string
     */
    protected $realm;

    /**
     * Keycloak Client ID
     *
     * @var string
     */
    protected $clientId;

    /**
     * Keycloak Client Secret
     *
     * @var string
     */
    protected $clientSecret;

    /**
     * Keycloak OpenId Configuration
     *
     * @var array
     */
    protected $openid;

    /**
     * Keycloak OpenId Cache Configuration
     *
     * @var array
     */
    protected $cacheOpenid;

    /**
     * CallbackUrl
     *
     * @var array
     */
    protected $callbackUrl;

    /**
     * RedirectLogout
     *
     * @var array
     */
    protected $redirectLogout;

    /**
     * The state for authorization request
     *
     * @var string
     */
    protected $state;

    /**
     * The HTTP Client
     *
     * @var ClientInterface
     */
    protected $httpClient;

    /**
     * The Constructor
     * You can extend this service setting protected variables before call
     * parent constructor to comunicate with Keycloak smoothly.
     *
     * @param ClientInterface $client
     * @return void
     */
    public function __construct(ClientInterface $client)
    {
        if (is_null($this->baseUrl)) {
            $this->baseUrl = trim(Config::get('keycloak-web.base_url'), '/');
        }

        if (is_null($this->realm)) {
            $this->realm = Config::get('keycloak-web.realm');
        }

        if (is_null($this->clientId)) {
            $this->clientId = Config::get('keycloak-web.client_id');
        }

        if (is_null($this->clientSecret)) {
            $this->clientSecret = Config::get('keycloak-web.client_secret');
        }

        if (is_null($this->cacheOpenid)) {
            $this->cacheOpenid = Config::get('keycloak-web.cache_openid', false);
        }

        if (is_null($this->callbackUrl)) {
            $this->callbackUrl = route('callback');
        }

        if (is_null($this->redirectLogout)) {
            $this->redirectLogout = Config::get('keycloak-web.redirect_logout', '/');
        }

        $this->state = $this->generateRandomState();
        $this->httpClient = $client;
    }

    /**
     * Return the login URL
     *
     * @link https://openid.net/specs/openid-connect-core-1_0.html#CodeFlowAuth
     *
     * @return string
     */
    public function getLoginUrl()
    {
        $url = $this->getOpenIdValue('authorization_endpoint');
        $params = [
            'scope' => 'openid',
            'response_type' => 'code',
            'client_id' => $this->getClientId(),
            'redirect_uri' => $this->callbackUrl,
            'state' => $this->getState(),
        ];

        return $this->buildUrl($url, $params);
    }

    /**
     * Return the logout URL
     *
     * @return string
     */
    public function getLogoutUrl()
    {
        $url = $this->getOpenIdValue('end_session_endpoint');

        $redirectLogout = url($this->redirectLogout);

        $token = $this->retrieveToken();
        if (empty($token) || empty($token['access_token'])) {
            return '';
        }

        $token = new KeycloakAccessToken($token);
        $idTokenHint = $token->getIdToken();

        $params = [
            'post_logout_redirect_uri' => $redirectLogout,
            'id_token_hint' => $idTokenHint
        ];

        return $this->buildUrl($url, $params);
    }

    /**
     * Return the register URL
     *
     * @link https://stackoverflow.com/questions/51514437/keycloak-direct-user-link-registration
     *
     * @return string
     */
    public function getRegisterUrl()
    {
        $url = $this->getLoginUrl();
        return str_replace('/auth?', '/registrations?', $url);
    }

    /**
     * Get access token from Code
     *
     * @param  string $code
     * @return array
     */
    public function getAccessToken($code)
    {
        $url = $this->getOpenIdValue('token_endpoint');
        $params = [
            'code' => $code,
            'client_id' => $this->getClientId(),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->callbackUrl,
        ];

        if (!empty($this->clientSecret)) {
            $params['client_secret'] = $this->clientSecret;
        }

        $token = [];

        try {
            $response = $this->httpClient->request('POST', $url, ['form_params' => $params]);

            if ($response->getStatusCode() === 200) {
                $token = $response->getBody()->getContents();
                $token = json_decode($token, true);
            }
        } catch (GuzzleException $e) {
            $this->logException($e);
        }

        return $token;
    }

    public function getAccessTokenByClientCredentials()
    {
        $url = $this->getOpenIdValue('token_endpoint');
        $params = [
            'client_id' => $this->getClientId(),
            'grant_type' => 'client_credentials',
            'redirect_uri' => $this->callbackUrl,
            'client_secret' => $this->clientSecret
        ];

        $token = [];

        try {
            $response = $this->httpClient->request('POST', $url, ['form_params' => $params]);

            if ($response->getStatusCode() === 200) {
                $token = $response->getBody()->getContents();
                $token = json_decode($token, true);
            }
        } catch (GuzzleException $e) {
            $this->logException($e);
        }

        return $token;
    }

    public function getAccessTokenByPassword($username, $password)
    {
        $url = $this->getOpenIdValue('token_endpoint');
        $params = [
            'grant_type' => 'password',
            'client_id' => $this->getClientId(),
            'client_secret' => $this->clientSecret,
            'username' => $username,
            'password' => $password,
            'scope' => 'openid'
        ];

        $token = [];

        try {
            $response = $this->httpClient->request('POST', $url, ['form_params' => $params]);

            if ($response->getStatusCode() === 200) {
                $token = $response->getBody()->getContents();
                $token = json_decode($token, true);
            }
        } catch (GuzzleException $e) {
            $this->logException($e);
        }

        return $token;
    }

    /**
     * Refresh access token
     *
     * @param  string $refreshToken
     * @return array
     */
    public function refreshAccessToken($credentials)
    {
        if (empty($credentials['refresh_token'])) {
            return [];
        }

        $url = $this->getOpenIdValue('token_endpoint');
        $params = [
            'client_id' => $this->getClientId(),
            'grant_type' => 'refresh_token',
            'refresh_token' => $credentials['refresh_token'],
            'redirect_uri' => $this->callbackUrl,
        ];

        if (!empty($this->clientSecret)) {
            $params['client_secret'] = $this->clientSecret;
        }

        $token = [];

        try {
            $response = $this->httpClient->request('POST', $url, ['form_params' => $params]);

            if ($response->getStatusCode() === 200) {
                $token = $response->getBody()->getContents();
                $token = json_decode($token, true);
            }
        } catch (GuzzleException $e) {
            $this->logException($e);
        }

        return $token;
    }

    /**
     * Invalidate Refresh
     *
     * @param  string $refreshToken
     * @return array
     */
    public function invalidateRefreshToken($refreshToken)
    {
        $url = $this->getOpenIdValue('end_session_endpoint');
        $params = [
            'client_id' => $this->getClientId(),
            'refresh_token' => $refreshToken,
        ];

        if (!empty($this->clientSecret)) {
            $params['client_secret'] = $this->clientSecret;
        }

        try {
            $response = $this->httpClient->request('POST', $url, ['form_params' => $params]);
            return $response->getStatusCode() === 204;
        } catch (GuzzleException $e) {
            $this->logException($e);
        }

        return false;
    }

    /**
     * Get access token from Code
     * @param  array $credentials
     * @return array
     */
    public function getUserProfile($credentials)
    {
        $credentials = $this->refreshTokenIfNeeded($credentials);

        $user = [];
        try {
            // Validate JWT Token
            $token = new KeycloakAccessToken($credentials);

            if (empty($token->getAccessToken())) {
                throw new Exception('Access Token is invalid.');
            }

            $claims = array(
                'aud' => $this->getClientId(),
                'iss' => $this->getOpenIdValue('issuer'),
            );

            $token->validateIdToken($claims);

            // Get userinfo
            $url = $this->getOpenIdValue('userinfo_endpoint');
            $headers = [
                'Authorization' => 'Bearer ' . $token->getAccessToken(),
                'Accept' => 'application/json',
            ];

            $response = $this->httpClient->request('GET', $url, ['headers' => $headers]);

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Was not able to get userinfo (not 200)');
            }

            $user = $response->getBody()->getContents();
            $user = json_decode($user, true);

            // Validate retrieved user is owner of token
            $token->validateSub($user['sub'] ?? '');
        } catch (GuzzleException $e) {
            $this->logException($e);
        } catch (Exception $e) {
            Log::error('[Keycloak Service] ' . print_r($e->getMessage(), true));
        }

        return $user;
    }

    /**
     * Get users
     * @param  array $searchParams
     * @return array
     */
    public function getUsers($searchParams = [])
    {
        $url = $this->baseUrl . '/admin/realms/' . $this->realm;
        $url = $url . '/users';

        $token = $this->retrieveToken();
        if (empty($token) || empty($token['access_token'])) {
            return [];
        }

        $token = new KeycloakAccessToken($token);
        $accessToken = $token->getAccessToken();

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ];

        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => $searchParams,
                'headers' => $headers
            ]);

            if ($response->getStatusCode() === 200) {
                $users = $response->getBody()->getContents();
                $users = json_decode($users, true);
                return $users;
            }
        } catch (GuzzleException $e) {
            $this->logException($e);

            throw new Exception('[Keycloak Error] It was not possible to load users: ' . $e->getMessage());
        }
    }

    public function getClients($queryParams = [])
    {
        $url = $this->baseUrl . '/admin/realms/' . $this->realm;
        $url = $url . '/clients';

        $token = $this->retrieveToken();
        if (empty($token) || empty($token['access_token'])) {
            return [];
        }

        $token = new KeycloakAccessToken($token);
        $accessToken = $token->getAccessToken();

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ];

        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => $queryParams,
                'headers' => $headers
            ]);

            if ($response->getStatusCode() === 200) {
                $clients = $response->getBody()->getContents();
                $clients = json_decode($clients, true);
                return $clients;
            }
        } catch (GuzzleException $e) {
            $this->logException($e);

            throw new Exception('[Keycloak Error] It was not possible to load clients: ' . $e->getMessage());
        }
    }

    public function getClientRoles($id, $queryParams = [])
    {
        $url = $this->baseUrl . '/admin/realms/' . $this->realm;
        $url = $url . '/clients/' . $id . '/roles';

        $token = $this->retrieveToken();
        if (empty($token) || empty($token['access_token'])) {
            return [];
        }

        $token = new KeycloakAccessToken($token);
        $accessToken = $token->getAccessToken();

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ];

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'query' => $queryParams
            ]);

            if ($response->getStatusCode() === 200) {
                $clients = $response->getBody()->getContents();
                $clients = json_decode($clients, true);
                return $clients;
            }
        } catch (GuzzleException $e) {
            $this->logException($e);

            throw new Exception('[Keycloak Error] It was not possible to load roles: ' . $e->getMessage());
        }
    }

    public function getClientResources($id)
    {
        $url = $this->baseUrl . '/admin/realms/' . $this->realm;
        $url = $url . '/clients/' . $id . '/authz/resource-server/resource';
        $token = $this->retrieveToken();
        if (empty($token) || empty($token['access_token'])) {
            return [];
        }

        $token = new KeycloakAccessToken($token);
        $accessToken = $token->getAccessToken();

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ];

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers
            ]);

            if ($response->getStatusCode() === 200) {
                $resources = $response->getBody()->getContents();
                $resources = json_decode($resources, true);
                return $resources;
            }
        } catch (GuzzleException $e) {
            $this->logException($e);

            throw new Exception('[Keycloak Error] It was not possible to load client resources: ' . $e->getMessage());
        }
    }

    public function getUserClientRoles($userId, $clientId = null)
    {
        if (!$clientId) {
            $clientId = $this->clientId;
        }
        $url = $this->baseUrl . '/admin/realms/' . $this->realm;
        $url = $url . '/users/' . $userId . '/role-mappings/clients/' . $clientId;

        $token = $this->retrieveToken();
        if (empty($token) || empty($token['access_token'])) {
            return [];
        }

        $token = new KeycloakAccessToken($token);
        $accessToken = $token->getAccessToken();

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ];

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers
            ]);

            if ($response->getStatusCode() === 200) {
                $roles = $response->getBody()->getContents();
                $roles = json_decode($roles, true);
                return $roles;
            }
        } catch (GuzzleException $e) {
            $this->logException($e);

            throw new Exception('[Keycloak Error] It was not possible to load user client roles: ' . $e->getMessage());
        }
    }

    public function addClientRolesToUser($userId, $roles, $clientId = null)
    {
        if (!$clientId) {
            $clientId = $this->clientId;
        }
        $url = $this->baseUrl . '/admin/realms/' . $this->realm;
        $url = $url . '/users/' . $userId . '/role-mappings/clients/' . $clientId;

        $token = $this->retrieveToken();
        if (empty($token) || empty($token['access_token'])) {
            return [];
        }

        $token = new KeycloakAccessToken($token);
        $accessToken = $token->getAccessToken();

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'json' => $roles
            ]);

            if ($response->getStatusCode() === 200) {
                $roles = $response->getBody()->getContents();
                $roles = json_decode($roles, true);
                return $roles;
            }
        } catch (GuzzleException $e) {
            $this->logException($e);

            throw new Exception('[Keycloak Error] It was not possible to add client roles to user: ' . $e->getMessage());
        }
    }

    public function removeClientRolesfromUser($userId, $roles, $clientId = null)
    {
        if (!$clientId) {
            $clientId = $this->clientId;
        }
        $url = $this->baseUrl . '/admin/realms/' . $this->realm;
        $url = $url . '/users/' . $userId . '/role-mappings/clients/' . $clientId;

        $token = $this->retrieveToken();
        if (empty($token) || empty($token['access_token'])) {
            return [];
        }

        $token = new KeycloakAccessToken($token);
        $accessToken = $token->getAccessToken();

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];

        try {
            $response = $this->httpClient->request("DELETE", $url, [
                'headers' => $headers,
                'json' => $roles
            ]);

            if ($response->getStatusCode() === 200) {
                $roles = $response->getBody()->getContents();
                $roles = json_decode($roles, true);
                return $roles;
            }
        } catch (GuzzleException $e) {
            $this->logException($e);

            throw new Exception('[Keycloak Error] It was not possible to remove client roles from user: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve Token from Session
     *
     * @return array|null
     */
    public function retrieveToken()
    {
        return session()->get(self::KEYCLOAK_SESSION);
    }

    /**
     * Save Token to Session
     *
     * @return void
     */
    public function saveToken($credentials)
    {
        session()->put(self::KEYCLOAK_SESSION, $credentials);
        session()->save();
    }

    /**
     * Remove Token from Session
     *
     * @return void
     */
    public function forgetToken()
    {
        session()->forget(self::KEYCLOAK_SESSION);
        session()->save();
    }

    /**
     * Validate State from Session
     *
     * @return void
     */
    public function validateState($state)
    {
        $challenge = session()->get(self::KEYCLOAK_SESSION_STATE);
        return (!empty($state) && !empty($challenge) && $challenge === $state);
    }

    /**
     * Save State to Session
     *
     * @return void
     */
    public function saveState()
    {
        session()->put(self::KEYCLOAK_SESSION_STATE, $this->state);
        session()->save();
    }

    /**
     * Remove State from Session
     *
     * @return void
     */
    public function forgetState()
    {
        session()->forget(self::KEYCLOAK_SESSION_STATE);
        session()->save();
    }

    /**
     * Build a URL with params
     *
     * @param  string $url
     * @param  array $params
     * @return string
     */
    public function buildUrl($url, $params)
    {
        $parsedUrl = parse_url($url);
        if (empty($parsedUrl['host'])) {
            return trim($url, '?') . '?' . Arr::query($params);
        }

        if (!empty($parsedUrl['port'])) {
            $parsedUrl['host'] .= ':' . $parsedUrl['port'];
        }

        $parsedUrl['scheme'] = (empty($parsedUrl['scheme'])) ? 'https' : $parsedUrl['scheme'];
        $parsedUrl['path'] = (empty($parsedUrl['path'])) ? '' : $parsedUrl['path'];

        $url = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path'];
        $query = [];

        if (!empty($parsedUrl['query'])) {
            $parsedUrl['query'] = explode('&', $parsedUrl['query']);

            foreach ($parsedUrl['query'] as $value) {
                $value = explode('=', $value);

                if (count($value) < 2) {
                    continue;
                }

                $key = array_shift($value);
                $value = implode('=', $value);

                $query[$key] = urldecode($value);
            }
        }

        $query = array_merge($query, $params);

        return $url . '?' . Arr::query($query);
    }

    /**
     * Return the client id for requests
     *
     * @return string
     */
    protected function getClientId()
    {
        return $this->clientId;
    }

    /**
     * Return the state for requests
     *
     * @return string
     */
    protected function getState()
    {
        return $this->state;
    }

    /**
     * Return a value from the Open ID Configuration
     *
     * @param  string $key
     * @return string
     */
    protected function getOpenIdValue($key)
    {
        if (!$this->openid) {
            $this->openid = $this->getOpenIdConfiguration();
        }

        return Arr::get($this->openid, $key);
    }

    /**
     * Retrieve OpenId Endpoints
     *
     * @return array
     */
    protected function getOpenIdConfiguration()
    {
        $cacheKey = 'keycloak_web_guard_openid-' . $this->realm . '-' . md5($this->baseUrl);

        // From cache?
        if ($this->cacheOpenid) {
            $configuration = Cache::get($cacheKey, []);

            if (!empty($configuration)) {
                return $configuration;
            }
        }

        // Request if cache empty or not using
        $url = $this->baseUrl . '/realms/' . $this->realm;
        $url = $url . '/.well-known/openid-configuration';

        $configuration = [];

        try {
            $response = $this->httpClient->request('GET', $url);

            if ($response->getStatusCode() === 200) {
                $configuration = $response->getBody()->getContents();
                $configuration = json_decode($configuration, true);
            }
        } catch (GuzzleException $e) {
            $this->logException($e);

            throw new Exception('[Keycloak Error] It was not possible to load OpenId configuration: ' . $e->getMessage());
        }

        // Save cache
        if ($this->cacheOpenid) {
            Cache::put($cacheKey, $configuration);
        }

        return $configuration;
    }

    /**
     * Check user permissions for resource
     * 
     * @param string $permissions 
     * @return bool 
     */
    public function obtainPermissions($permissions, $clientId = null)
    {
        $url = $this->getOpenIdValue('token_endpoint');

        $token = $this->retrieveToken();
        if (empty($token) || empty($token['access_token'])) {
            return false;
        }

        $token = new KeycloakAccessToken($token);
        $accessToken = $token->getAccessToken();

        $params = [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:uma-ticket',
            'audience' => $clientId ?? $this->clientId,
            'permission' => $permissions,
            'response_mode' => 'decision',
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
        ];

        try {
            $response = $this->httpClient->request('POST', $url, ['form_params' => $params, 'headers' => $headers]);
            if ($response->getStatusCode() === 200) {
                return true;
            }
        } catch (GuzzleException $e) {
            // if ($e->getCode() === 403) {
            //     return false;
            // }
            $this->logException($e);

            // throw new Exception('[Keycloak Error] It was not possible to obtain permissions: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check we need to refresh token and refresh if needed
     *
     * @param  array $credentials
     * @return array
     */
    protected function refreshTokenIfNeeded($credentials)
    {
        if (!is_array($credentials) || empty($credentials['access_token']) || empty($credentials['refresh_token'])) {
            return $credentials;
        }

        $token = new KeycloakAccessToken($credentials);
        if (!$token->hasExpired()) {
            return $credentials;
        }

        $credentials = $this->refreshAccessToken($credentials);

        if (empty($credentials['access_token'])) {
            $this->forgetToken();
            return [];
        }

        $this->saveToken($credentials);
        return $credentials;
    }

    /**
     * Log a GuzzleException
     *
     * @param  GuzzleException $e
     * @return void
     */
    protected function logException(GuzzleException $e)
    {
        // Guzzle 7
        if (!method_exists($e, 'getResponse') || empty($e->getResponse())) {
            Log::error('[Keycloak Service] ' . $e->getMessage());
            return;
        }

        $error = [
            'request' => method_exists($e, 'getRequest') ? $e->getRequest() : '',
            'response' => $e->getResponse()->getBody()->getContents(),
        ];

        Log::error('[Keycloak Service] ' . print_r($error, true));
    }

    /**
     * Return a random state parameter for authorization
     *
     * @return string
     */
    protected function generateRandomState()
    {
        return bin2hex(random_bytes(16));
    }
}
