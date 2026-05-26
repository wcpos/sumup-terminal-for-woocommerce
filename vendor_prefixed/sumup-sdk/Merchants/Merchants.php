<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Merchants;

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services;

use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\HttpClientInterface;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\RequestHeaders;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\RequestOptions;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\ResponseDecoder;

/**
 * Query parameters for MerchantsGetParams.
 *
 * @package SumUp\Services
 */
class MerchantsGetParams
{
    /**
     * The version of the resource. At the moment, the only supported value is `latest`. When provided and the requested resource's `change_status` is pending, the resource will be returned with all pending changes applied. When no changes are pending the resource is returned as is. The `change_status` in the response body will reflect the current state of the resource.
     *
     * @var string|null
     */
    public ?string $version = null;

}

/**
 * Query parameters for MerchantsGetPersonParams.
 *
 * @package SumUp\Services
 */
class MerchantsGetPersonParams
{
    /**
     * The version of the resource. At the moment, the only supported value is `latest`. When provided and the requested resource's `change_status` is pending, the resource will be returned with all pending changes applied. When no changes are pending the resource is returned as is. The `change_status` in the response body will reflect the current state of the resource.
     *
     * @var string|null
     */
    public ?string $version = null;

}

/**
 * Query parameters for MerchantsListPersonsParams.
 *
 * @package SumUp\Services
 */
class MerchantsListPersonsParams
{
    /**
     * The version of the resource. At the moment, the only supported value is `latest`. When provided and the requested resource's `change_status` is pending, the resource will be returned with all pending changes applied. When no changes are pending the resource is returned as is. The `change_status` in the response body will reflect the current state of the resource.
     *
     * @var string|null
     */
    public ?string $version = null;

}

/**
 * Class Merchants
 *
 * Merchant account represents a single business entity at SumUp.
 *
 * @package SumUp\Services
 */
class Merchants implements SumUpService
{
    /**
     * The client for the http communication.
     *
     * @var HttpClientInterface
     */
    protected HttpClientInterface $client;

    /**
     * The access token needed for authentication for the services.
     *
     * @var string
     */
    protected string $accessToken;

    /**
     * Merchants constructor.
     *
     * @param HttpClientInterface $client
     * @param string $accessToken
     */
    public function __construct(HttpClientInterface $client, string $accessToken)
    {
        $this->client = $client;
        $this->accessToken = $accessToken;
    }

    /**
     * Retrieve a Merchant
     *
     * @param string $merchantCode Short unique identifier for the merchant.
     * @param MerchantsGetParams|null $queryParams Optional query string parameters
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Merchant
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function get(string $merchantCode, ?MerchantsGetParams $queryParams = null, ?RequestOptions $requestOptions = null): \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Merchant
    {
        $path = sprintf('/v1/merchants/%s', rawurlencode((string) $merchantCode));
        if ($queryParams !== null) {
            $queryParamsData = [];
            if (isset($queryParams->version)) {
                $queryParamsData['version'] = $queryParams->version;
            }
            if (!empty($queryParamsData)) {
                $queryString = http_build_query($queryParamsData);
                if (!empty($queryString)) {
                    $path .= '?' . $queryString;
                }
            }
        }
        $payload = [];
        $headers = RequestHeaders::build($this->accessToken, $requestOptions);

        $response = $this->client->send('GET', $path, $payload, $headers, $requestOptions);

        return ResponseDecoder::decodeOrThrow($response, \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Merchant::class, [
            '404' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Problem::class],
        ], 'GET', $path);
    }

    /**
     * Retrieve a Person
     *
     * @param string $merchantCode Short unique identifier for the merchant.
     * @param string $personId Person ID
     * @param MerchantsGetPersonParams|null $queryParams Optional query string parameters
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Person
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function getPerson(string $merchantCode, string $personId, ?MerchantsGetPersonParams $queryParams = null, ?RequestOptions $requestOptions = null): \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Person
    {
        $path = sprintf('/v1/merchants/%s/persons/%s', rawurlencode((string) $merchantCode), rawurlencode((string) $personId));
        if ($queryParams !== null) {
            $queryParamsData = [];
            if (isset($queryParams->version)) {
                $queryParamsData['version'] = $queryParams->version;
            }
            if (!empty($queryParamsData)) {
                $queryString = http_build_query($queryParamsData);
                if (!empty($queryString)) {
                    $path .= '?' . $queryString;
                }
            }
        }
        $payload = [];
        $headers = RequestHeaders::build($this->accessToken, $requestOptions);

        $response = $this->client->send('GET', $path, $payload, $headers, $requestOptions);

        return ResponseDecoder::decodeOrThrow($response, \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Person::class, [
            '404' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Problem::class],
        ], 'GET', $path);
    }

    /**
     * List Persons
     *
     * @param string $merchantCode Short unique identifier for the merchant.
     * @param MerchantsListPersonsParams|null $queryParams Optional query string parameters
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ListPersonsResponseBody
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function listPersons(string $merchantCode, ?MerchantsListPersonsParams $queryParams = null, ?RequestOptions $requestOptions = null): \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ListPersonsResponseBody
    {
        $path = sprintf('/v1/merchants/%s/persons', rawurlencode((string) $merchantCode));
        if ($queryParams !== null) {
            $queryParamsData = [];
            if (isset($queryParams->version)) {
                $queryParamsData['version'] = $queryParams->version;
            }
            if (!empty($queryParamsData)) {
                $queryString = http_build_query($queryParamsData);
                if (!empty($queryString)) {
                    $path .= '?' . $queryString;
                }
            }
        }
        $payload = [];
        $headers = RequestHeaders::build($this->accessToken, $requestOptions);

        $response = $this->client->send('GET', $path, $payload, $headers, $requestOptions);

        return ResponseDecoder::decodeOrThrow($response, \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ListPersonsResponseBody::class, [
            '404' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Problem::class],
        ], 'GET', $path);
    }
}
