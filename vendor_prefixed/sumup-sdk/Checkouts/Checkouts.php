<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Checkouts;

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services;

use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\HttpClientInterface;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\RequestHeaders;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\RequestOptions;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\RequestEncoder;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\ResponseDecoder;

class CheckoutsCreateApplePaySessionRequest
{
    /**
     * the context to create this apple pay session.
     *
     * @var string
     */
    public string $context;

    /**
     * The target url to create this apple pay session.
     *
     * @var string
     */
    public string $target;

    /**
     * Create request DTO.
     *
     * @param string $context
     * @param string $target
     */
    public function __construct(
        string $context,
        string $target
    ) {
        \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Hydrator::hydrate([
            'context' => $context,
            'target' => $target,
        ], self::class, $this);
    }

    /**
     * Create request DTO from an associative array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        self::assertRequiredFields($data, [
            'context' => 'context',
            'target' => 'target',
        ]);

        $request = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Hydrator::hydrate($data, self::class, $request);

        return $request;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $requiredFields
     */
    private static function assertRequiredFields(array $data, array $requiredFields): void
    {
        foreach ($requiredFields as $serializedName => $propertyName) {
            if (!array_key_exists($serializedName, $data) && !array_key_exists($propertyName, $data)) {
                throw new \InvalidArgumentException(sprintf('Missing required field "%s".', $serializedName));
            }
        }
    }

}

class CheckoutsListAvailablePaymentMethodsResponse
{
    /**
     *
     * @var CheckoutsListAvailablePaymentMethodsResponseItem[]|null
     */
    public ?array $availablePaymentMethods = null;

}

class CheckoutsListAvailablePaymentMethodsResponseItem
{
    /**
     * The ID of the payment method.
     *
     * @var string
     */
    public string $id;

}

/**
 * Query parameters for CheckoutsListParams.
 *
 * @package SumUp\Services
 */
class CheckoutsListParams
{
    /**
     * Filters the list of checkout resources by the unique ID of the checkout.
     *
     * @var string|null
     */
    public ?string $checkoutReference = null;

}

/**
 * Query parameters for CheckoutsListAvailablePaymentMethodsParams.
 *
 * @package SumUp\Services
 */
class CheckoutsListAvailablePaymentMethodsParams
{
    /**
     * The amount for which the payment methods should be eligible, in major units.
     *
     * @var float|null
     */
    public ?float $amount = null;

    /**
     * The currency for which the payment methods should be eligible.
     *
     * @var string|null
     */
    public ?string $currency = null;

}

/**
 * Class Checkouts
 *
 * Checkouts represent online payment sessions that you create before attempting to charge a payer. A checkout captures the payment intent, such as the amount, currency, merchant, and optional customer or redirect settings, and then moves through its lifecycle as you process it.
 *
 * Use this tag to:
 * - create a checkout before collecting or confirming payment details
 * - process the checkout with a card, saved card, wallet, or supported alternative payment method
 * - retrieve or list checkouts to inspect their current state and associated payment attempts
 * - deactivate a checkout that should no longer be used
 *
 * Typical workflow:
 * - create a checkout with the order amount, currency, and merchant information
 * - process the checkout through SumUp client tools such as the [Payment Widget and Swift Checkout SDK](https://developer.sumup.com/online-payments/checkouts)
 * - retrieve the checkout or use the Transactions endpoints to inspect the resulting payment record
 *
 * Checkouts are used to initiate and orchestrate online payments. Transactions remain the authoritative record of the resulting payment outcome.
 *
 * @package SumUp\Services
 */
class Checkouts implements SumUpService
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
     * Checkouts constructor.
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
     * Create a checkout
     *
     * @param \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\CheckoutCreateRequest|array<string, mixed> $body Required request payload
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Checkout
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function create(\WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\CheckoutCreateRequest|array $body, ?RequestOptions $requestOptions = null): \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Checkout
    {
        $path = '/v0.1/checkouts';
        $payload = [];
        $requestBody = $body;
        if (is_array($requestBody)) {
            $requestBody = \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\CheckoutCreateRequest::fromArray($requestBody);
        }
        $payload = RequestEncoder::encode($requestBody);
        $headers = RequestHeaders::build($this->accessToken, $requestOptions);

        $response = $this->client->send('POST', $path, $payload, $headers, $requestOptions);

        return ResponseDecoder::decodeOrThrow($response, [
            '201' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Checkout::class],
        ], [
            '400' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ErrorExtended::class],
            '401' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Problem::class],
            '403' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ErrorForbidden::class],
            '409' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Error::class],
        ], 'POST', $path);
    }

    /**
     * Create an Apple Pay session
     *
     * @param string $id Unique ID of the checkout resource.
     * @param CheckoutsCreateApplePaySessionRequest|array<string, mixed>|null $body Optional request payload
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return array<string, mixed>
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function createApplePaySession(string $id, CheckoutsCreateApplePaySessionRequest|array|null $body = null, ?RequestOptions $requestOptions = null): array
    {
        $path = sprintf('/v0.2/checkouts/%s/apple-pay-session', rawurlencode((string) $id));
        $payload = [];
        if ($body !== null) {
            $requestBody = $body;
            if (is_array($requestBody)) {
                $requestBody = CheckoutsCreateApplePaySessionRequest::fromArray($requestBody);
            }
            $payload = RequestEncoder::encode($requestBody);
        }
        $headers = RequestHeaders::build($this->accessToken, $requestOptions);

        $response = $this->client->send('PUT', $path, $payload, $headers, $requestOptions);

        return ResponseDecoder::decodeOrThrow($response, [
            '200' => ['type' => 'object'],
        ], [
            '400' => ['type' => 'mixed'],
            '404' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Error::class],
        ], 'PUT', $path);
    }

    /**
     * Deactivate a checkout
     *
     * @param string $id Unique ID of the checkout resource.
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Checkout
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function deactivate(string $id, ?RequestOptions $requestOptions = null): \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Checkout
    {
        $path = sprintf('/v0.1/checkouts/%s', rawurlencode((string) $id));
        $payload = [];
        $headers = RequestHeaders::build($this->accessToken, $requestOptions);

        $response = $this->client->send('DELETE', $path, $payload, $headers, $requestOptions);

        return ResponseDecoder::decodeOrThrow($response, \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Checkout::class, [
            '401' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Problem::class],
            '404' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Error::class],
            '409' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Error::class],
        ], 'DELETE', $path);
    }

    /**
     * Retrieve a checkout
     *
     * @param string $id Unique ID of the checkout resource.
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\CheckoutSuccess
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function get(string $id, ?RequestOptions $requestOptions = null): \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\CheckoutSuccess
    {
        $path = sprintf('/v0.1/checkouts/%s', rawurlencode((string) $id));
        $payload = [];
        $headers = RequestHeaders::build($this->accessToken, $requestOptions);

        $response = $this->client->send('GET', $path, $payload, $headers, $requestOptions);

        return ResponseDecoder::decodeOrThrow($response, \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\CheckoutSuccess::class, [
            '401' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Problem::class],
            '404' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Error::class],
        ], 'GET', $path);
    }

    /**
     * List checkouts
     *
     * @param CheckoutsListParams|null $queryParams Optional query string parameters
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\CheckoutSuccess[]
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function list(?CheckoutsListParams $queryParams = null, ?RequestOptions $requestOptions = null): array
    {
        $path = '/v0.1/checkouts';
        if ($queryParams !== null) {
            $queryParamsData = [];
            if (isset($queryParams->checkoutReference)) {
                $queryParamsData['checkout_reference'] = $queryParams->checkoutReference;
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

        return ResponseDecoder::decodeOrThrow($response, [
            '200' => ['type' => 'array', 'items' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\CheckoutSuccess::class]],
        ], [
            '401' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Problem::class],
        ], 'GET', $path);
    }

    /**
     * Get available payment methods
     *
     * @param string $merchantCode The SumUp merchant code.
     * @param CheckoutsListAvailablePaymentMethodsParams|null $queryParams Optional query string parameters
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services\CheckoutsListAvailablePaymentMethodsResponse
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function listAvailablePaymentMethods(string $merchantCode, ?CheckoutsListAvailablePaymentMethodsParams $queryParams = null, ?RequestOptions $requestOptions = null): \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services\CheckoutsListAvailablePaymentMethodsResponse
    {
        $path = sprintf('/v0.1/merchants/%s/payment-methods', rawurlencode((string) $merchantCode));
        if ($queryParams !== null) {
            $queryParamsData = [];
            if (isset($queryParams->amount)) {
                $queryParamsData['amount'] = $queryParams->amount;
            }
            if (isset($queryParams->currency)) {
                $queryParamsData['currency'] = $queryParams->currency;
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

        return ResponseDecoder::decodeOrThrow($response, \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services\CheckoutsListAvailablePaymentMethodsResponse::class, [
            '400' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\DetailsError::class],
        ], 'GET', $path);
    }

    /**
     * Process a checkout
     *
     * @param string $id Unique ID of the checkout resource.
     * @param \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ProcessCheckout|array<string, mixed> $body Required request payload
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\CheckoutSuccess|\WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\CheckoutAccepted
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function process(string $id, \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ProcessCheckout|array $body, ?RequestOptions $requestOptions = null): \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\CheckoutSuccess|\WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\CheckoutAccepted
    {
        $path = sprintf('/v0.1/checkouts/%s', rawurlencode((string) $id));
        $payload = [];
        $requestBody = $body;
        if (is_array($requestBody)) {
            $requestBody = \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\ProcessCheckout::fromArray($requestBody);
        }
        $payload = RequestEncoder::encode($requestBody);
        $headers = RequestHeaders::build($this->accessToken, $requestOptions);

        $response = $this->client->send('PUT', $path, $payload, $headers, $requestOptions);

        return ResponseDecoder::decodeOrThrow($response, [
            '200' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\CheckoutSuccess::class],
            '202' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\CheckoutAccepted::class],
        ], [
            '400' => ['type' => 'mixed'],
            '401' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Problem::class],
            '404' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Error::class],
            '409' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Error::class],
        ], 'PUT', $path);
    }
}
