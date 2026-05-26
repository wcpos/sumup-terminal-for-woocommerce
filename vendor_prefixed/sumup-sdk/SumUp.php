<?php

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp;

use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConfigurationException;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\CurlClient;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\HttpClientInterface;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\RequestHeaders;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\RequestOptions;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\Response;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services\Checkouts;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services\Customers;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services\Members;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services\Memberships;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services\Merchants;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services\Payouts;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services\Readers;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services\Receipts;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services\Roles;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services\Transactions;

/**
 * Class SumUp
 *
 * @package SumUp
 *
 */
class SumUp
{
    /**
     * The access token for API authentication.
     *
     * @var string|null
     */
    protected ?string $accessToken = null;

    /**
     * @var HttpClientInterface
     */
    protected HttpClientInterface $client;

    /**
     * SumUp constructor.
     *
     * @param string|array<string, mixed>|null $configOrApiKey
     *
     * @throws SDKException
     */
    public function __construct(string|array|null $configOrApiKey = null)
    {
        $config = [];
        if (is_string($configOrApiKey) && $configOrApiKey !== '') {
            $config['api_key'] = $configOrApiKey;
        } elseif (is_array($configOrApiKey)) {
            $config = $configOrApiKey;
        }
        $customHttpClient = $config['client'] ?? null;
        if (array_key_exists('client', $config)) {
            unset($config['client']);
        }

        $config = $this->normalizeConfig($config);
        if ($customHttpClient instanceof HttpClientInterface) {
            $this->client = $customHttpClient;
        } else {
            $this->client = new CurlClient(
                $config['base_uri'],
                $config['custom_headers'],
                $config['ca_bundle_path']
            );
        }

        // Set access token from config (api_key or access_token)
        if (!empty($config['api_key'])) {
            $this->accessToken = $config['api_key'];
        } elseif (!empty($config['access_token'])) {
            $this->accessToken = $config['access_token'];
        }
    }

    /**
     * Returns the default access token.
     *
     * @return string|null
     */
    public function getDefaultAccessToken(): ?string
    {
        return $this->accessToken;
    }

    /**
     * Sets the default access token.
     *
     * @param string $accessToken
     *
     * @return void
     */
    public function setDefaultAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    /**
     * Send a raw request through the configured HTTP client.
     *
     * @param string $method
     * @param string $path
     * @param array<int|string, mixed> $body
     * @param RequestOptions|null $requestOptions
     *
     * @return Response
     */
    public function request(
        string $method,
        string $path,
        array $body = [],
        ?RequestOptions $requestOptions = null
    ): Response {
        $headers = RequestHeaders::build($this->accessToken, $requestOptions);

        return $this->client->send($method, $path, $body, $headers, $requestOptions);
    }

    /**
     * Resolve the access token that should be used for a service.
     *
     * @param string|null $accessToken
     *
     * @return string
     *
     * @throws ConfigurationException
     */
    protected function resolveAccessToken(?string $accessToken = null): string
    {
        if (!empty($accessToken)) {
            return $accessToken;
        }

        if (empty($this->accessToken)) {
            throw new ConfigurationException('No access token provided');
        }

        return $this->accessToken;
    }

    /**
     * Normalize configuration and apply defaults.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     *
     * @throws ConfigurationException
     */
    private function normalizeConfig(array $config): array
    {
        $config = array_merge([
            'api_key' => null,
            'access_token' => null,
            'base_uri' => 'https://api.sumup.com',
            'custom_headers' => [],
            'ca_bundle_path' => null,
        ], $config);

        if ($config['api_key'] === null) {
            $config['api_key'] = getenv('SUMUP_API_KEY') ?: null;
        }

        if ($config['access_token'] === null) {
            $config['access_token'] = getenv('SUMUP_ACCESS_TOKEN') ?: null;
        }

        $headers = is_array($config['custom_headers']) ? $config['custom_headers'] : [];
        $headers['Accept'] = 'application/problem+json, application/json';
        $headers['User-Agent'] = SdkInfo::getUserAgent();
        $config['custom_headers'] = $headers;

        return $config;
    }

    /**
     * Access the Checkouts API endpoints.
     *
     * @return Checkouts
     */
    public function checkouts(): Checkouts
    {
        return new Checkouts($this->client, $this->resolveAccessToken());
    }

    /**
     * Access the Customers API endpoints.
     *
     * @return Customers
     */
    public function customers(): Customers
    {
        return new Customers($this->client, $this->resolveAccessToken());
    }

    /**
     * Access the Members API endpoints.
     *
     * @return Members
     */
    public function members(): Members
    {
        return new Members($this->client, $this->resolveAccessToken());
    }

    /**
     * Access the Memberships API endpoints.
     *
     * @return Memberships
     */
    public function memberships(): Memberships
    {
        return new Memberships($this->client, $this->resolveAccessToken());
    }

    /**
     * Access the Merchants API endpoints.
     *
     * @return Merchants
     */
    public function merchants(): Merchants
    {
        return new Merchants($this->client, $this->resolveAccessToken());
    }

    /**
     * Access the Payouts API endpoints.
     *
     * @return Payouts
     */
    public function payouts(): Payouts
    {
        return new Payouts($this->client, $this->resolveAccessToken());
    }

    /**
     * Access the Readers API endpoints.
     *
     * @return Readers
     */
    public function readers(): Readers
    {
        return new Readers($this->client, $this->resolveAccessToken());
    }

    /**
     * Access the Receipts API endpoints.
     *
     * @return Receipts
     */
    public function receipts(): Receipts
    {
        return new Receipts($this->client, $this->resolveAccessToken());
    }

    /**
     * Access the Roles API endpoints.
     *
     * @return Roles
     */
    public function roles(): Roles
    {
        return new Roles($this->client, $this->resolveAccessToken());
    }

    /**
     * Access the Transactions API endpoints.
     *
     * @return Transactions
     */
    public function transactions(): Transactions
    {
        return new Transactions($this->client, $this->resolveAccessToken());
    }
}
