<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Memberships;

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services;

use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\HttpClientInterface;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\RequestHeaders;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\HttpClient\RequestOptions;
use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\ResponseDecoder;

class MembershipsListResponse
{
    /**
     *
     * @var \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Membership[]
     */
    public array $items;

    /**
     *
     * @var int
     */
    public int $totalCount;

}

/**
 * Query parameters for MembershipsListParams.
 *
 * @package SumUp\Services
 */
class MembershipsListParams
{
    /**
     * Offset of the first member to return.
     *
     * @var int|null
     */
    public ?int $offset = null;

    /**
     * Maximum number of members to return.
     *
     * @var int|null
     */
    public ?int $limit = null;

    /**
     * Filter memberships by resource kind.
     *
     * @var string|null
     */
    public ?string $kind = null;

    /**
     * Filter the returned memberships by the membership status.
     *
     * @var string|null
     */
    public ?string $status = null;

    /**
     * Filter memberships by resource kind.
     *
     * @var string|null
     */
    public ?string $resourceType = null;

    /**
     * Filter memberships by the sandbox status of the resource the membership is in.
     *
     * @var bool|null
     */
    public ?bool $resourceAttributesSandbox = null;

    /**
     * Filter memberships by the name of the resource the membership is in.
     *
     * @var string|null
     */
    public ?string $resourceName = null;

    /**
     * Filter memberships by the parent of the resource the membership is in.
     * When filtering by parent both `resource.parent.id` and `resource.parent.type` must be present. Pass explicit null to filter for resources without a parent.
     *
     * @var string|null
     */
    public ?string $resourceParentId = null;

    /**
     * Filter memberships by the parent of the resource the membership is in.
     * When filtering by parent both `resource.parent.id` and `resource.parent.type` must be present. Pass explicit null to filter for resources without a parent.
     *
     * @var mixed|null
     */
    public mixed $resourceParentType = null;

    /**
     * Filter the returned memberships by role.
     *
     * @var string[]|null
     */
    public ?array $roles = null;

}

/**
 * Class Memberships
 *
 * Endpoints to manage user's memberships. Memberships are used to connect the user to merchant accounts and to grant them access to the merchant's resources via roles.
 *
 * @package SumUp\Services
 */
class Memberships implements SumUpService
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
     * Memberships constructor.
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
     * List memberships
     *
     * @param MembershipsListParams|null $queryParams Optional query string parameters
     * @param RequestOptions|null $requestOptions Optional typed request options
     *
     * @return \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services\MembershipsListResponse
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\UnexpectedApiException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\ConnectionException
     * @throws \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Exception\SDKException
     */
    public function list(?MembershipsListParams $queryParams = null, ?RequestOptions $requestOptions = null): \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services\MembershipsListResponse
    {
        $path = '/v0.1/memberships';
        if ($queryParams !== null) {
            $queryParamsData = [];
            if (isset($queryParams->offset)) {
                $queryParamsData['offset'] = $queryParams->offset;
            }
            if (isset($queryParams->limit)) {
                $queryParamsData['limit'] = $queryParams->limit;
            }
            if (isset($queryParams->kind)) {
                $queryParamsData['kind'] = $queryParams->kind;
            }
            if (isset($queryParams->status)) {
                $queryParamsData['status'] = $queryParams->status;
            }
            if (isset($queryParams->resourceType)) {
                $queryParamsData['resource.type'] = $queryParams->resourceType;
            }
            if (isset($queryParams->resourceAttributesSandbox)) {
                $queryParamsData['resource.attributes.sandbox'] = $queryParams->resourceAttributesSandbox;
            }
            if (isset($queryParams->resourceName)) {
                $queryParamsData['resource.name'] = $queryParams->resourceName;
            }
            if (isset($queryParams->resourceParentId)) {
                $queryParamsData['resource.parent.id'] = $queryParams->resourceParentId;
            }
            if (isset($queryParams->resourceParentType)) {
                $queryParamsData['resource.parent.type'] = $queryParams->resourceParentType;
            }
            if (isset($queryParams->roles)) {
                $queryParamsData['roles'] = $queryParams->roles;
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

        return ResponseDecoder::decodeOrThrow($response, \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Services\MembershipsListResponse::class, [
            '400' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Problem::class],
            '401' => ['type' => 'class', 'class' => \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\Problem::class],
        ], 'GET', $path);
    }
}
