<?php

declare (strict_types=1);
namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Sales;

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services;

use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\HttpClientInterface;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\RequestHeaders;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\RequestOptions;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\RequestEncoder;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\ResponseDecoder;
/**
 * Query parameters for SalesListSalesV10Params.
 *
 * @package SumUp\Services
 */
class SalesListSalesV10Params
{
    /**
     * Pagination token for subsequent requests. Should only be set by clients if they have
     * received a token in the previous response, and this request is an immediate follow-up
     * request to that response to load the subsequent page. Clients should treat this as an
     * opaque token and should not attempt to parse it in any way.
     *
     * @var string|null
     */
    public ?string $paginationToken = null;
    /**
     * Maximum number of results per page.
     *
     * @var int|null
     */
    public ?int $paginationLimit = null;
}
/**
 * Class Sales
 *
 * Operations related to reading sales data.
 *
 * @package SumUp\Services
 */
class Sales implements SumUpService
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
     * Sales constructor.
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
     * Gets a sale
     *
     * @param string $merchantCode The merchant code.
     * @param string $saleId The sale ID.
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Sale
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function getSaleV10(string $merchantCode, string $saleId, ?RequestOptions $requestOptions = null): \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Sale
    {
        $path = sprintf('/v1.0/merchants/%s/sales/%s', rawurlencode((string) $merchantCode), rawurlencode((string) $saleId));
        $payload = [];
        $headers = RequestHeaders::build($this->accessToken, $requestOptions);
        $response = $this->client->send('GET', $path, $payload, $headers, $requestOptions);
        return ResponseDecoder::decodeOrThrow($response, \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Sale::class, ['401' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ProblemV10::class], '403' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ProblemV10::class], '404' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ProblemV10::class], '412' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ProblemV10::class]], 'GET', $path);
    }
    /**
     * Lists sales
     *
     * @param string $merchantCode The merchant code.
     * @param SalesListSalesV10Params|null $queryParams Optional query string parameters
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\SalePage
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function listSalesV10(string $merchantCode, ?SalesListSalesV10Params $queryParams = null, ?RequestOptions $requestOptions = null): \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\SalePage
    {
        $path = sprintf('/v1.0/merchants/%s/sales', rawurlencode((string) $merchantCode));
        if ($queryParams !== null) {
            $queryParamsData = [];
            if (isset($queryParams->paginationToken)) {
                $queryParamsData['pagination_token'] = $queryParams->paginationToken;
            }
            if (isset($queryParams->paginationLimit)) {
                $queryParamsData['pagination_limit'] = $queryParams->paginationLimit;
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
        return ResponseDecoder::decodeOrThrow($response, \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\SalePage::class, ['400' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ProblemV10::class], '401' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ProblemV10::class], '403' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ProblemV10::class], '412' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ProblemV10::class]], 'GET', $path);
    }
    /**
     * Searches sales
     *
     * @param string $merchantCode The merchant code.
     * @param \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\SaleSearchRequest|array<string, mixed> $body Required request payload
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\SaleSearchResponse
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function searchSalesV10(string $merchantCode, \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\SaleSearchRequest|array $body, ?RequestOptions $requestOptions = null): \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\SaleSearchResponse
    {
        $path = sprintf('/v1.0/merchants/%s/sales/search', rawurlencode((string) $merchantCode));
        $payload = [];
        $requestBody = $body;
        if (is_array($requestBody)) {
            $requestBody = \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\SaleSearchRequest::fromArray($requestBody);
        }
        $payload = RequestEncoder::encode($requestBody);
        $headers = RequestHeaders::build($this->accessToken, $requestOptions);
        $response = $this->client->send('POST', $path, $payload, $headers, $requestOptions);
        return ResponseDecoder::decodeOrThrow($response, \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\SaleSearchResponse::class, ['400' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ProblemV10::class], '401' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ProblemV10::class], '403' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ProblemV10::class], '412' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ProblemV10::class]], 'POST', $path);
    }
}
