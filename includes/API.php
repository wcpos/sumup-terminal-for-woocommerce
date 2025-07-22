<?php
/**
 * SumUp Terminal API
 * Handles the API for SumUp Terminal.
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class API
 * Handles the API for SumUp Terminal.
 */
class API extends WP_REST_Controller {
	/**
	 * SumUp API Key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Register the routes for the API.
	 */
	public function register_routes(): void {
		// Add the webhook route.
		register_rest_route(
			$this->namespace,
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle SumUp webhook events.
	 *
	 * @param WP_REST_Request $request The incoming webhook request.
	 *
	 * @return WP_Error|WP_REST_Response A success or error response.
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		$payload         = $request->get_body();

		Logger::log( 'Webhook received: ' . $payload );

		return new WP_REST_Response( array( 'message' => 'Webhook received' ), 200 );
	}
}
