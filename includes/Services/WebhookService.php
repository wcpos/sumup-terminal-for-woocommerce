<?php
/**
 * SumUp Webhook Service
 * Handles webhook-related SumUp API operations.
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Services;

/**
 * Class WebhookService.
 */
class WebhookService extends HttpClient {
	/**
	 * Register a new webhook.
	 *
	 * @param array $data Webhook data.
	 *
	 * @return array|false Webhook data or false on failure.
	 */
	public function register( array $data ) {
		return parent::post( '/v0.1/webhooks', $data );
	}

	/**
	 * Get all registered webhooks.
	 *
	 * @return array|false Webhooks data or false on failure.
	 */
	public function get_all() {
		return parent::get( '/v0.1/webhooks' );
	}

	/**
	 * Delete a webhook.
	 *
	 * @param string $webhook_id Webhook ID.
	 *
	 * @return array|false Response data or false on failure.
	 */
	public function delete_webhook( $webhook_id ) {
		return parent::delete( "/v0.1/webhooks/{$webhook_id}" );
	}

	/**
	 * Get a specific webhook by ID.
	 *
	 * @param string $webhook_id Webhook ID.
	 *
	 * @return array|false Webhook data or false on failure.
	 */
	public function get_webhook( $webhook_id ) {
		return parent::get( "/v0.1/webhooks/{$webhook_id}" );
	}

	/**
	 * Update a webhook.
	 *
	 * @param string $webhook_id Webhook ID.
	 * @param array  $data       Updated webhook data.
	 *
	 * @return array|false Updated webhook data or false on failure.
	 */
	public function update( $webhook_id, array $data ) {
		return parent::put( "/v0.1/webhooks/{$webhook_id}", $data );
	}
}
