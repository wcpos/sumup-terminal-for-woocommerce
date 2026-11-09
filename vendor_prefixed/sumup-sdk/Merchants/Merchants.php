<?php

declare (strict_types=1);
namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Merchants;

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services;

use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\HttpClientInterface;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\RequestHeaders;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\RequestOptions;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\ResponseDecoder;
/**
 * Class Merchants
 *
 * A Merchant represents a single business which can use SumUp products like payment processing.
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
     * Get Merchant
     *
     * @param string $merchantCode Short unique identifier for the merchant.
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Merchant
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function get(string $merchantCode, ?RequestOptions $requestOptions = null): \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Merchant
    {
        $path = sprintf('/v1/merchants/%s', rawurlencode((string) $merchantCode));
        $payload = [];
        $headers = RequestHeaders::build($this->accessToken, $requestOptions);
        $response = $this->client->send('GET', $path, $payload, $headers, $requestOptions);
        return ResponseDecoder::decodeOrThrow($response, \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Merchant::class, ['404' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Problem::class]], 'GET', $path);
    }
    /**
     * Get Person
     *
     * @param string $merchantCode Short unique identifier for the merchant.
     * @param string $personId Person ID
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Person
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function getPerson(string $merchantCode, string $personId, ?RequestOptions $requestOptions = null): \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Person
    {
        $path = sprintf('/v1/merchants/%s/persons/%s', rawurlencode((string) $merchantCode), rawurlencode((string) $personId));
        $payload = [];
        $headers = RequestHeaders::build($this->accessToken, $requestOptions);
        $response = $this->client->send('GET', $path, $payload, $headers, $requestOptions);
        return ResponseDecoder::decodeOrThrow($response, \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Person::class, ['404' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Problem::class]], 'GET', $path);
    }
    /**
     * List Persons
     *
     * @param string $merchantCode Short unique identifier for the merchant.
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ListPersonsResponseBody
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function listPersons(string $merchantCode, ?RequestOptions $requestOptions = null): \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ListPersonsResponseBody
    {
        $path = sprintf('/v1/merchants/%s/persons', rawurlencode((string) $merchantCode));
        $payload = [];
        $headers = RequestHeaders::build($this->accessToken, $requestOptions);
        $response = $this->client->send('GET', $path, $payload, $headers, $requestOptions);
        return ResponseDecoder::decodeOrThrow($response, \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ListPersonsResponseBody::class, ['404' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Problem::class]], 'GET', $path);
    }
}
