<?php
/**
 * SumUp Terminal gateway
 * Handles the gateway for SumUp Terminal.
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal;

use WC_Payment_Gateway;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\CheckoutService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\ProfileService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\ReaderService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\WebhookService;

/**
 * Class SumUpTerminalGateway.
 */
class Gateway extends WC_Payment_Gateway {
	use Abstracts\SumUpErrorHandler; // Include the SumUp error handler trait.

	/**
	 * @var string The SumUp API Key.
	 */
	protected $api_key;

	/**
	 * @var ProfileService The profile service instance.
	 */
	private $profile_service;

	/**
	 * @var CheckoutService The checkout service instance.
	 */
	private $checkout_service;

	/**
	 * @var ReaderService The reader service instance.
	 */
	private $reader_service;

	/**
	 * @var WebhookService The webhook service instance.
	 */
	private $webhook_service;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'sumup_terminal_for_woocommerce';
		$this->method_title       = __( 'SumUp Terminal', 'sumup-terminal-for-woocommerce' );
		$this->method_description = __( 'Accept in-person payments using SumUp Terminal.', 'sumup-terminal-for-woocommerce' );

		// Load gateway settings.
		$this->init_settings();
		$this->api_key     = $this->get_option( 'api_key' );
		
		// Initialize services before form fields (needed for connection status check).
		$this->init_services();
		
		$this->init_form_fields();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		// Save settings hook.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Enqueue admin scripts
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		} else {
			// Enqueue frontend payment scripts
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_payment_scripts' ) );
		}
	}

	/**
	 * Get the profile service.
	 *
	 * @return ProfileService
	 */
	public function get_profile_service() {
		return $this->profile_service;
	}

	/**
	 * Get the checkout service.
	 *
	 * @return CheckoutService
	 */
	public function get_checkout_service() {
		return $this->checkout_service;
	}

	/**
	 * Get the reader service.
	 *
	 * @return ReaderService
	 */
	public function get_reader_service() {
		return $this->reader_service;
	}

	/**
	 * Get the webhook service.
	 *
	 * @return WebhookService
	 */
	public function get_webhook_service() {
		return $this->webhook_service;
	}

	/**
	 * Initialize gateway form fields.
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'title' => array(
				'title'       => __( 'Title', 'sumup-terminal-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The title displayed to customers during checkout.', 'sumup-terminal-for-woocommerce' ),
				'default'     => __( 'SumUp Terminal', 'sumup-terminal-for-woocommerce' ),
			),
			'description' => array(
				'title'       => __( 'Description', 'sumup-terminal-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'The description displayed to customers during checkout.', 'sumup-terminal-for-woocommerce' ),
				'default'     => __( 'Pay in person using SumUp Terminal.', 'sumup-terminal-for-woocommerce' ),
			),
			'api_key' => array(
				'title'             => __( 'SumUp API Key', 'sumup-terminal-for-woocommerce' ),
				'type'              => 'password',
				'description'       => __( 'Your SumUp API Key from the Developer Portal.', 'sumup-terminal-for-woocommerce' ),
				'default'           => '',
				'custom_attributes' => array(
					'id' => 'api_key',
				),
			),

		);
	}

	/**
	 * Register the gateway with WooCommerce.
	 *
	 * @param array $methods Existing payment methods.
	 *
	 * @return array Updated payment methods.
	 */
	public static function register_gateway( $methods ) {
		$methods[] = __CLASS__;

		return $methods;
	}

	/**
	 * Output the settings in the admin area.
	 */
	public function admin_options(): void {
		parent::admin_options();

		// Add Connection Status section outside of form fields
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label><?php esc_html_e( 'Connection Status', 'sumup-terminal-for-woocommerce' ); ?></label>
				</th>
				<td class="forminp">
					<?php echo $this->get_connection_status_html(); ?>
				</td>
			</tr>
		</table>

		<!-- Add SumUp API Key Setup information section. -->
		<table class="form-table">
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label><?php esc_html_e( 'SumUp API Setup', 'sumup-terminal-for-woocommerce' ); ?></label>
				</th>
				<td class="forminp">
					<p><?php esc_html_e( 'To connect your SumUp account, follow these simple steps:', 'sumup-terminal-for-woocommerce' ); ?></p>
					
					<h4><?php esc_html_e( 'Step 1: Get Your API Key', 'sumup-terminal-for-woocommerce' ); ?></h4>
					<ol style="margin-left: 20px;">
						<li><?php esc_html_e( 'Go to the SumUp Developer Portal', 'sumup-terminal-for-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Navigate to Account → API Keys', 'sumup-terminal-for-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Generate a new API key', 'sumup-terminal-for-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Copy and paste your API key into the field above', 'sumup-terminal-for-woocommerce' ); ?></li>
					</ol>
					
					<p>
						<a href="https://developer.sumup.com" target="_blank" class="button">
							<?php esc_html_e( 'SumUp Developer Portal', 'sumup-terminal-for-woocommerce' ); ?>
						</a>
					</p>
					
					<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin-top: 10px;">
						<p><strong><?php esc_html_e( 'Testing vs Live:', 'sumup-terminal-for-woocommerce' ); ?></strong> 
							<?php esc_html_e( 'SumUp uses separate accounts for testing and live payments. Create a test account in the "Test Profiles" section for testing, then switch to your live account API key for production.', 'sumup-terminal-for-woocommerce' ); ?>
						</p>
					</div>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Output the descriptions for the gateway settings.
	 *
	 * @param array $data Data for the description.
	 *
	 * @return string
	 */
	public function get_description_html( $data ) {
		// Check if custom_attributes are set.
		if ( isset( $data['custom_attributes'] ) && isset( $data['custom_attributes']['id'] ) ) {
			switch ( $data['custom_attributes']['id'] ) {
				case 'api_key':
					return '<p class="description">' . $this->check_api_key_status() . '</p>';
			}
		}

		return parent::get_description_html( $data );
	}

	/**
	 * Process the payment.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array Payment result or void on failure.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// Check if a transaction ID is recorded.
		$transaction_id = $order->get_transaction_id();
		if ( empty( $transaction_id ) ) {
			wc_add_notice( __( 'Payment error: No transaction ID recorded.', 'sumup-terminal-for-woocommerce' ), 'error' );

			return;
		}

		// Check if the order is already paid.
		if ( ! $order->is_paid() ) {
			$order->payment_complete();
		}

		// Return thank-you page URL.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Payment fields displayed during checkout or order-pay page.
	 */
	public function payment_fields(): void {
		global $wp;

		// Description for the payment method.
		echo '<p>' . esc_html( $this->get_option( 'description' ) ) . '</p>';

		// Debug: Check what's happening with the configuration
		Logger::log( 'Payment Fields Debug: API Key present? ' . ( ! empty( $this->api_key ) ? 'YES' : 'NO' ) );
		Logger::log( 'Payment Fields Debug: Reader service exists? ' . ( isset( $this->reader_service ) ? 'YES' : 'NO' ) );
		
		if ( isset( $this->reader_service ) ) {
			$merchant_id = $this->reader_service->get_merchant_id();
			Logger::log( 'Payment Fields Debug: Merchant ID: ' . ( $merchant_id ?: 'EMPTY' ) );
		}

		// Check if merchant_id is available
		if ( ! $this->reader_service || ! $this->reader_service->get_merchant_id() ) {
			// Try to reinitialize services if they're missing
			if ( ! empty( $this->api_key ) ) {
				Logger::log( 'Payment Fields Debug: Attempting to reinitialize services...' );
				$this->init_services();
			}
			
			// Check again after potential reinitialization
			if ( ! $this->reader_service || ! $this->reader_service->get_merchant_id() ) {
				echo '<div class="woocommerce-error">';
				echo '<p>' . esc_html__( 'SumUp Terminal is not properly configured. Please contact the store administrator.', 'sumup-terminal-for-woocommerce' ) . '</p>';
				echo '</div>';

				return;
			}
		}

		// Get available readers
		$readers = $this->reader_service->get_all();

		if ( ! $readers || empty( $readers ) ) {
			echo '<div class="woocommerce-error">';
			echo '<p>' . esc_html__( 'No SumUp Terminal readers are available. Please contact the store administrator.', 'sumup-terminal-for-woocommerce' ) . '</p>';
			echo '</div>';

			return;
		}

		// Check if we're on the order-pay page.
		if ( is_checkout_pay_page() ) {
			// Extract the order ID from the URL.
			$order_id = isset( $wp->query_vars['order-pay'] ) ? absint( $wp->query_vars['order-pay'] ) : 0;
		} else {
			// Default behavior for the main checkout page.
			$order_id = null;
		}

		// Display reader selection and controls
		echo '<div id="sumup-terminal-payment-interface">';
		echo '<h4>' . esc_html__( 'Available SumUp Terminal Readers', 'sumup-terminal-for-woocommerce' ) . '</h4>';

		foreach ( $readers as $reader ) {
			// Skip readers without an ID
			if ( empty( $reader['id'] ) ) {
				continue;
			}

			$reader_id     = esc_attr( $reader['id'] );
			$reader_name   = esc_html( $reader['name'] ?? __( 'Unnamed Reader', 'sumup-terminal-for-woocommerce' ) );
			$reader_status = esc_html( ucfirst( $reader['status'] ?? __( 'Unknown', 'sumup-terminal-for-woocommerce' ) ) );

			echo '<div class="sumup-reader-card" style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 4px; background: #f9f9f9;">';
			echo '<div class="reader-info">';
			echo '<strong>' . $reader_name . '</strong>';
			echo '<br><small>' . esc_html__( 'Status:', 'sumup-terminal-for-woocommerce' ) . ' ' . $reader_status . '</small>';
			echo '<br><small>' . esc_html__( 'Model:', 'sumup-terminal-for-woocommerce' ) . ' ' . esc_html( $reader['device']['model'] ?? $reader['model'] ?? __( 'Unknown', 'sumup-terminal-for-woocommerce' ) ) . '</small>';
			echo '</div>';
			
			echo '<div class="reader-controls" style="margin-top: 10px;">';
			echo '<button type="button" class="button button-primary sumup-checkout-btn" data-reader-id="' . $reader_id . '" data-order-id="' . esc_attr( $order_id ) . '">';
			echo esc_html__( 'Start Payment', 'sumup-terminal-for-woocommerce' );
			echo '</button>';
			
			echo ' <button type="button" class="button button-secondary sumup-cancel-btn" data-reader-id="' . $reader_id . '" style="display: none;">';
			echo esc_html__( 'Cancel Payment', 'sumup-terminal-for-woocommerce' );
			echo '</button>';
			echo '</div>';
			
			echo '<div class="payment-status" id="payment-status-' . $reader_id . '" style="margin-top: 10px;"></div>';
			echo '</div>';
		}

		echo '</div>';

		// Fallback message for users without JavaScript enabled.
		echo '<noscript>' . esc_html__( 'Please enable JavaScript to use the SumUp Terminal integration.', 'sumup-terminal-for-woocommerce' ) . '</noscript>';
	}

	/**
	 * Create a SumUp terminal checkout for the given order.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 *
	 * @return array|false Checkout data or false on failure.
	 */
	public function create_sumup_checkout( $order ) {
		if ( empty( $this->api_key ) ) {
			return false;
		}

		try {
			// Create webhook URL for this transaction
			$webhook_url = home_url( '/wp-json/sumup-terminal/v1/webhook' );

			// Create terminal checkout with webhook URL
			$response = $this->reader_service->create_checkout_for_order( $order, $webhook_url );

			if ( $response && isset( $response['checkout_id'] ) ) {
				return array(
					'checkout_id' => $response['checkout_id'],
					'amount'      => \floatval( $order->get_total() ),
					'currency'    => $order->get_currency(),
				);
			}

			return false;
		} catch ( \Exception $e ) {
			Logger::log( 'SumUp terminal checkout creation failed: ' . $e->getMessage() );

			return false;
		}
	}

	/**
	 * Process admin options (override to reinitialize services when API key changes).
	 *
	 * @return bool
	 */
	public function process_admin_options() {
		$result = parent::process_admin_options();
		
		// Reload the API key and reinitialize services.
		$this->api_key = $this->get_option( 'api_key' );
		$this->init_services();

		return $result;
	}

	/**
	 * Enqueue admin scripts for the gateway settings page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ): void {
		// Only load on WooCommerce settings pages
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// Only load on payment gateways tab and our specific gateway
		if ( ! isset( $_GET['tab'] ) || 'checkout' !== $_GET['tab'] ) {
			return;
		}

		if ( ! isset( $_GET['section'] ) || $_GET['section'] !== $this->id ) {
			return;
		}

		// Get the plugin directory URL
		$plugin_url = plugin_dir_url( \dirname( __FILE__ ) );

		// Enqueue the admin script
		wp_enqueue_script(
			'sumup-terminal-admin',
			$plugin_url . 'assets/js/admin.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		// Localize script with data needed by JavaScript
		wp_localize_script(
			'sumup-terminal-admin',
			'sumupAdminData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sumup_admin_actions' ),
				'strings' => array(
					'confirmUnpair'   => __( 'Are you sure you want to unpair this reader?', 'sumup-terminal-for-woocommerce' ),
					'confirmWebhook'  => __( 'This will register a webhook with SumUp for payment notifications. Continue?', 'sumup-terminal-for-woocommerce' ),
					'unpairSuccess'   => __( 'Reader unpaired successfully!', 'sumup-terminal-for-woocommerce' ),
					'unpairFailed'    => __( 'Failed to unpair reader:', 'sumup-terminal-for-woocommerce' ),
					'webhookSuccess'  => __( 'Webhook configured successfully!', 'sumup-terminal-for-woocommerce' ),
					'webhookFailed'   => __( 'Failed to configure webhook:', 'sumup-terminal-for-woocommerce' ),
					'unknownError'    => __( 'Unknown error', 'sumup-terminal-for-woocommerce' ),
					'networkError'    => __( 'Network error occurred', 'sumup-terminal-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Enqueue payment scripts for the checkout interface.
	 */
	public function enqueue_payment_scripts(): void {
		// Only load on checkout pages or when our gateway is selected
		if ( ! is_checkout() && ! is_checkout_pay_page() ) {
			return;
		}

		global $wp;

		// Get the plugin directory URL
		$plugin_url = plugin_dir_url( \dirname( __FILE__ ) );

		// Enqueue the payment CSS
		wp_enqueue_style(
			'sumup-terminal-payment',
			$plugin_url . 'assets/css/payment.css',
			array(),
			'1.0.0'
		);

		// Enqueue the payment script
		wp_enqueue_script(
			'sumup-terminal-payment',
			$plugin_url . 'assets/js/payment.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		// Check if we're on the order-pay page to get order ID
		$order_id = null;
		if ( is_checkout_pay_page() ) {
			$order_id = isset( $wp->query_vars['order-pay'] ) ? absint( $wp->query_vars['order-pay'] ) : 0;
		}

		// Localize script data for payment interface
		wp_localize_script(
			'sumup-terminal-payment',
			'sumupPaymentData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sumup_payment_actions' ),
				'orderId' => $order_id,
				'strings' => array(
					'startingPayment'    => __( 'Starting payment...', 'sumup-terminal-for-woocommerce' ),
					'paymentInProgress'  => __( 'Payment in progress...', 'sumup-terminal-for-woocommerce' ),
					'paymentCancelled'   => __( 'Payment cancelled.', 'sumup-terminal-for-woocommerce' ),
					'paymentSuccess'     => __( 'Payment successful!', 'sumup-terminal-for-woocommerce' ),
					'paymentFailed'      => __( 'Payment failed:', 'sumup-terminal-for-woocommerce' ),
					'networkError'       => __( 'Network error occurred', 'sumup-terminal-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Initialize the SumUp services.
	 */
	private function init_services(): void {
		Logger::log( 'Init Services Debug: Starting initialization with API key: ' . ( ! empty( $this->api_key ) ? 'PRESENT' : 'MISSING' ) );
		
		$this->profile_service  = new ProfileService( $this->api_key );
		$this->checkout_service = new CheckoutService( $this->api_key );
		$this->reader_service   = new ReaderService( $this->api_key );
		$this->webhook_service  = new WebhookService( $this->api_key );

		// Set merchant ID if available and API key is set.
		if ( ! empty( $this->api_key ) ) {
			Logger::log( 'Init Services Debug: Attempting to get merchant code...' );
			$merchant_code = $this->profile_service->get_merchant_code();
			Logger::log( 'Init Services Debug: Merchant code retrieved: ' . ( $merchant_code ?: 'EMPTY' ) );
			
			if ( $merchant_code ) {
				$this->checkout_service->set_merchant_id( $merchant_code );
				$this->reader_service->set_merchant_id( $merchant_code );
				$this->webhook_service->set_merchant_id( $merchant_code );
				Logger::log( 'Init Services Debug: Merchant ID set on all services: ' . $merchant_code );
			} else {
				Logger::log( 'Init Services Debug: Failed to get merchant code - services initialized without merchant ID' );
			}
		} else {
			Logger::log( 'Init Services Debug: No API key available - services initialized without merchant ID' );
		}
	}



	/**
	 * Get the connection status HTML for display.
	 *
	 * @return string HTML for connection status.
	 */
	private function get_connection_status_html() {
		if ( empty( $this->api_key ) ) {
			return $this->render_status_card( 'error', __( 'API Key Required', 'sumup-terminal-for-woocommerce' ), __( 'Enter your SumUp API Key above to connect to SumUp.', 'sumup-terminal-for-woocommerce' ) );
		}

		// Test the API key.
		$api_key_valid = $this->test_api_key();
		
		if ( ! $api_key_valid ) {
			return $this->render_status_card( 'error', __( 'Connection Failed', 'sumup-terminal-for-woocommerce' ), __( 'Unable to connect to SumUp. Please check your API key.', 'sumup-terminal-for-woocommerce' ) );
		}

		// API key is valid, get detailed status.
		$html = '';

		// 1. API Connection Status.
		$html .= $this->render_status_card( 'success', __( 'Connected to SumUp', 'sumup-terminal-for-woocommerce' ), __( 'API key is valid and ready to process payments.', 'sumup-terminal-for-woocommerce' ) );

		// 2. Merchant Information.
		try {
			$html .= $this->get_merchant_status_html();
		} catch ( \Exception $e ) {
			// Silently continue if merchant info fails
		}

		// 3. Reader Status.
		try {
			$html .= $this->get_reader_status_html();
		} catch ( \Exception $e ) {
			// Silently continue if reader status fails
		}

		return $html;
	}

	/**
	 * Render a status card.
	 *
	 * @param string $type    Card type: 'success', 'error', 'warning', 'info'.
	 * @param string $title   Card title.
	 * @param string $message Card message.
	 * @param string $actions Optional actions HTML.
	 *
	 * @return string HTML for status card.
	 */
	private function render_status_card( $type, $title, $message, $actions = '' ) {
		$colors = array(
			'success' => array( 'bg' => '#edfaef', 'border' => '#00a32a', 'icon' => '✓', 'color' => '#00a32a' ),
			'error'   => array( 'bg' => '#fcf0f1', 'border' => '#d63638', 'icon' => '✗', 'color' => '#d63638' ),
			'warning' => array( 'bg' => '#fff3cd', 'border' => '#ffeaa7', 'icon' => '⚠', 'color' => '#856404' ),
			'info'    => array( 'bg' => '#e7f3ff', 'border' => '#0073aa', 'icon' => 'ℹ', 'color' => '#0073aa' ),
		);

		$style = $colors[ $type ] ?? $colors['info'];

		$html = '<div style="background: ' . $style['bg'] . '; border: 1px solid ' . $style['border'] . '; padding: 10px; border-radius: 4px; margin-top: 10px;">';
		$html .= '<p><strong><span style="color: ' . $style['color'] . ';">' . $style['icon'] . '</span> ' . esc_html( $title ) . '</strong></p>';
		$html .= '<p>' . esc_html( $message ) . '</p>';

		if ( $actions ) {
			$html .= $actions;
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Get merchant status HTML.
	 *
	 * @return string HTML for merchant status.
	 */
	private function get_merchant_status_html() {
		$profile = $this->profile_service->get_profile();

		if ( ! $profile ) {
			return $this->render_status_card( 'error', __( 'Merchant Profile Error', 'sumup-terminal-for-woocommerce' ), __( 'Unable to retrieve merchant profile information.', 'sumup-terminal-for-woocommerce' ) );
		}

		// Get business name from multiple possible locations
		$business_name = '';
		if ( isset( $profile['merchant_profile']['doing_business_as']['business_name'] ) ) {
			$business_name = $profile['merchant_profile']['doing_business_as']['business_name'];
		} elseif ( isset( $profile['merchant_profile']['company_name'] ) ) {
			$business_name = $profile['merchant_profile']['company_name'];
		} else {
			// Fallback to personal name
			$first_name    = $profile['personal_profile']['first_name'] ?? '';
			$last_name     = $profile['personal_profile']['last_name']  ?? '';
			$business_name = trim( $first_name . ' ' . $last_name ) ?: __( 'Unknown', 'sumup-terminal-for-woocommerce' );
		}

		// Get merchant code and country from the correct locations
		$merchant_code = $profile['merchant_profile']['merchant_code'] ?? __( 'N/A', 'sumup-terminal-for-woocommerce' );
		$country       = $profile['merchant_profile']['country']       ?? $profile['personal_profile']['address']['country'] ?? __( 'N/A', 'sumup-terminal-for-woocommerce' );

		$merchant_info = '<strong>' . __( 'Merchant:', 'sumup-terminal-for-woocommerce' ) . '</strong> ' . esc_html( $business_name );
		$merchant_info .= '<br><strong>' . __( 'Merchant Code:', 'sumup-terminal-for-woocommerce' ) . '</strong> ' . esc_html( $merchant_code );
		$merchant_info .= '<br><strong>' . __( 'Country:', 'sumup-terminal-for-woocommerce' ) . '</strong> ' . esc_html( $country );

		return $this->render_status_card( 'info', __( 'Merchant Information', 'sumup-terminal-for-woocommerce' ), '', $merchant_info );
	}

	/**
	 * Get reader status HTML.
	 *
	 * @return string HTML for reader status.
	 */
	private function get_reader_status_html() {
		try {
			$readers = $this->reader_service->get_all();

			if ( ! $readers || empty( $readers ) ) {
				$actions = '<div id="sumup-pair-reader-form" style="margin-top: 10px;">';
				$actions .= '<h4>' . __( 'Pair a New Reader', 'sumup-terminal-for-woocommerce' ) . '</h4>';
				$actions .= '<p>' . __( 'To pair your Solo reader:', 'sumup-terminal-for-woocommerce' ) . '</p>';
				$actions .= '<ol>';
				$actions .= '<li>' . __( 'Turn on the reader (do not log in)', 'sumup-terminal-for-woocommerce' ) . '</li>';
				$actions .= '<li>' . __( 'Open menu → Connections → API → Connect', 'sumup-terminal-for-woocommerce' ) . '</li>';
				$actions .= '<li>' . __( 'Enter the pairing code below:', 'sumup-terminal-for-woocommerce' ) . '</li>';
				$actions .= '</ol>';
				$actions .= '<p>';
				$actions .= '<input type="text" id="sumup-pairing-code" placeholder="' . __( 'Enter pairing code', 'sumup-terminal-for-woocommerce' ) . '" maxlength="8" style="text-transform: uppercase; width: 120px; margin-right: 10px;" />';
				$actions .= '<button type="button" class="button-primary sumup-btn" data-action="pair-reader">' . __( 'Pair Reader', 'sumup-terminal-for-woocommerce' ) . '</button>';
				$actions .= '</p>';
				$actions .= '<div id="sumup-pair-result"></div>';
				$actions .= '</div>';

				return $this->render_status_card( 'warning', __( 'No Readers Configured', 'sumup-terminal-for-woocommerce' ), __( 'You need to pair a Solo reader to accept in-person payments.', 'sumup-terminal-for-woocommerce' ), $actions );
			}

			// Display reader information.
			$actions = '<div style="margin-top: 10px;">';

			foreach ( $readers as $reader ) {
				// Skip readers without an ID
				if ( empty( $reader['id'] ) ) {
					continue;
				}

				$reader_name       = $reader['name']                 ?? __( 'Unnamed Reader', 'sumup-terminal-for-woocommerce' );
				$reader_model      = $reader['device']['model']      ?? $reader['model'] ?? __( 'Unknown', 'sumup-terminal-for-woocommerce' );
				$reader_status     = $reader['status']               ?? __( 'Unknown', 'sumup-terminal-for-woocommerce' );
				$reader_identifier = $reader['device']['identifier'] ?? $reader['identifier'] ?? __( 'N/A', 'sumup-terminal-for-woocommerce' );
				$created_at        = isset( $reader['created_at'] ) ? date( 'Y-m-d H:i', strtotime( $reader['created_at'] ) ) : __( 'N/A', 'sumup-terminal-for-woocommerce' );

				$actions .= '<div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 4px; margin-bottom: 15px;">';
				$actions .= '<div style="display: flex; justify-content: space-between; align-items: flex-start;">';
				
				// Reader info section
				$actions .= '<div>';
				$actions .= '<h4 style="margin: 0 0 8px 0; font-size: 14px;">' . esc_html( $reader_name ) . '</h4>';
				$actions .= '<p style="margin: 0 0 5px 0; font-size: 12px; color: #666;">';
				$actions .= '<strong>' . __( 'Status:', 'sumup-terminal-for-woocommerce' ) . '</strong> ';
				$actions .= '<span style="color: ' . ( 'paired' === $reader_status ? '#00a32a' : '#d63638' ) . ';">' . esc_html( ucfirst( $reader_status ) ) . '</span>';
				$actions .= '</p>';
				$actions .= '<p style="margin: 0 0 5px 0; font-size: 12px; color: #666;"><strong>' . __( 'Model:', 'sumup-terminal-for-woocommerce' ) . '</strong> ' . esc_html( ucfirst( $reader_model ) ) . '</p>';
				$actions .= '<p style="margin: 0 0 5px 0; font-size: 12px; color: #666;"><strong>' . __( 'Serial:', 'sumup-terminal-for-woocommerce' ) . '</strong> ' . esc_html( $reader_identifier ) . '</p>';
				$actions .= '<p style="margin: 0 0 5px 0; font-size: 12px; color: #666;"><strong>' . __( 'Paired:', 'sumup-terminal-for-woocommerce' ) . '</strong> ' . esc_html( $created_at ) . '</p>';
				$actions .= '<p style="margin: 0; font-size: 11px; color: #999;"><strong>' . __( 'ID:', 'sumup-terminal-for-woocommerce' ) . '</strong> ' . esc_html( $reader['id'] ) . '</p>';
				$actions .= '</div>';
				
				// Actions section
				$actions .= '<div>';
				$actions .= '<button type="button" class="button-secondary sumup-btn" data-action="unpair-reader" data-reader-id="' . esc_attr( $reader['id'] ) . '">' . __( 'Unpair Reader', 'sumup-terminal-for-woocommerce' ) . '</button>';
				$actions .= '</div>';
				
				$actions .= '</div>';
				$actions .= '</div>';
			}

			// Always show the pairing form to allow pairing additional readers
			$actions .= '<div id="sumup-pair-reader-form-additional" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
			$actions .= '<h4>' . __( 'Pair Additional Reader', 'sumup-terminal-for-woocommerce' ) . '</h4>';
			$actions .= '<p>' . __( 'To pair another Solo reader:', 'sumup-terminal-for-woocommerce' ) . '</p>';
			$actions .= '<ol>';
			$actions .= '<li>' . __( 'Turn on the reader (do not log in)', 'sumup-terminal-for-woocommerce' ) . '</li>';
			$actions .= '<li>' . __( 'Open menu → Connections → API → Connect', 'sumup-terminal-for-woocommerce' ) . '</li>';
			$actions .= '<li>' . __( 'Enter the pairing code below:', 'sumup-terminal-for-woocommerce' ) . '</li>';
			$actions .= '</ol>';
			$actions .= '<p>';
			$actions .= '<input type="text" id="sumup-pairing-code" placeholder="' . __( 'Enter pairing code', 'sumup-terminal-for-woocommerce' ) . '" maxlength="8" style="text-transform: uppercase; width: 120px; margin-right: 10px;" />';
			$actions .= '<button type="button" class="button-primary sumup-btn" data-action="pair-reader">' . __( 'Pair Reader', 'sumup-terminal-for-woocommerce' ) . '</button>';
			$actions .= '</p>';
			$actions .= '<div id="sumup-pair-result"></div>';
			$actions .= '</div>';

			$actions .= '</div>';

			return $this->render_status_card( 'success', __( 'Readers Configured', 'sumup-terminal-for-woocommerce' ), \sprintf( _n( '%d reader is configured and ready.', '%d readers are configured and ready.', \count( $readers ), 'sumup-terminal-for-woocommerce' ), \count( $readers ) ), $actions );
		} catch ( \Exception $e ) {
			// Still show pairing form even if there's an error
			$actions = '<div id="sumup-pair-reader-form" style="margin-top: 10px;">';
			$actions .= '<h4>' . __( 'Pair a Reader', 'sumup-terminal-for-woocommerce' ) . '</h4>';
			$actions .= '<p>' . __( 'To pair your Solo reader:', 'sumup-terminal-for-woocommerce' ) . '</p>';
			$actions .= '<ol>';
			$actions .= '<li>' . __( 'Turn on the reader (do not log in)', 'sumup-terminal-for-woocommerce' ) . '</li>';
			$actions .= '<li>' . __( 'Open menu → Connections → API → Connect', 'sumup-terminal-for-woocommerce' ) . '</li>';
			$actions .= '<li>' . __( 'Enter the pairing code below:', 'sumup-terminal-for-woocommerce' ) . '</li>';
			$actions .= '</ol>';
			$actions .= '<p>';
			$actions .= '<input type="text" id="sumup-pairing-code" placeholder="' . __( 'Enter pairing code', 'sumup-terminal-for-woocommerce' ) . '" maxlength="8" style="text-transform: uppercase; width: 120px; margin-right: 10px;" />';
			$actions .= '<button type="button" class="button-primary sumup-btn" data-action="pair-reader">' . __( 'Pair Reader', 'sumup-terminal-for-woocommerce' ) . '</button>';
			$actions .= '</p>';
			$actions .= '<div id="sumup-pair-result"></div>';
			$actions .= '</div>';

			return $this->render_status_card( 'error', __( 'Reader Status Error', 'sumup-terminal-for-woocommerce' ), __( 'Unable to check reader status. You can still try pairing a reader.', 'sumup-terminal-for-woocommerce' ), $actions );
		}
	}

	/**
	 * Test if the API key is valid.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	private function test_api_key() {
		if ( ! $this->profile_service ) {
			return false;
		}
		
		return $this->profile_service->test_api_key();
	}

	/**
	 * Check API key status.
	 *
	 * @return string Status message.
	 */
	private function check_api_key_status() {
		if ( empty( $this->api_key ) ) {
			return __( 'Enter your SumUp API Key from the Developer Portal.', 'sumup-terminal-for-woocommerce' );
		}

		$is_valid = $this->test_api_key();

		if ( $is_valid ) {
			return '<span style="color: #00a32a;">✓ ' . __( 'API key is valid and connected.', 'sumup-terminal-for-woocommerce' ) . '</span>';
		}

		return '<span style="color: #d63638;">✗ ' . __( 'API key is invalid. Please check your key.', 'sumup-terminal-for-woocommerce' ) . '</span>';
	}
}
