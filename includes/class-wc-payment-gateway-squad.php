<?php

/**
 * Squad Payments Gateway.
 *
 * Provides Seamless Payments with Debit/Credit Cards.
 *
 * @class       WC_Gateway_Squad
 * @extends     WC_Payment_Gateway
 * @version     1.0.1
 * @package     WooCommerce/Classes/Payment
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class WC_Gateway_Squad extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		// Setup general properties.
		$this->setup_properties();

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Get setting values (from my created input values)
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );
		$this->testmode    = $this->get_option( 'testmode' ) === 'yes' ? true : false;
		$this->autocomplete_order = $this->get_option( 'autocomplete_order' ) === 'yes' ? true : false;
		
		$this->test_public_key = $this->get_option( 'test_public_key' );
		$this->test_secret_key = $this->get_option( 'test_secret_key' );

		$this->live_public_key = $this->get_option( 'live_public_key' );
		$this->live_secret_key = $this->get_option( 'live_secret_key' );

		$this->custom_metadata = $this->get_option( 'custom_metadata' ) === 'yes' ? true : false;

		$this->public_key = $this->testmode ? $this->test_public_key : $this->live_public_key;
		$this->secret_key = $this->testmode ? $this->test_secret_key : $this->live_secret_key;


		$this->webhook_url = $this->get_option( 'webhook_url' );
		$this->payment_options = $this->get_option( 'payment_options' );
		$this->instructions = $this->get_option( 'instructions' );
		 
		// Hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'load_payment_scripts' ) );
		// $this->load_payment_scripts();
      	add_action( 'woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

		// Payment(Webhook) listener/API hook 
		/**
		 * format=> $woocommerce_api_[:class name in lowercase]
		 * eg: woocommerce_api_wc_gateway_squad
		 */
		// add_action( 'woocommerce_api_wc_gateway_squad', array( $this, 'squad_verify_transaction' ) );
		add_action( 'woocommerce_api_'.strtolower(get_class($this)), array(&$this, 'squad_verify_transaction'));
		add_action( 'woocommerce_api_wc_squad_webhook', array( $this, 'process_webhooks' ) );
      	add_action( 'woocommerce_thankyou_'. $this->id, array( $this, 'thankyou_page'));

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties() {
		$this->id                 = 'squad';
		$this->icon               = apply_filters( 'woocommerce_squad_icon', plugins_url('../assets/icon.png', __FILE__ ) );
		$this->method_title       = __( 'Squad', 'squad-payments-woo' );
		$this->method_description = sprintf( __( 'Squad provide merchants with the tools and services needed to accept online payments from local and international customers using 
		Mastercard, Visa, Verve Cards and Bank Accounts. <a href="%1$s" target="_blank">Sign up</a> for a Squad account, and 
		<a href="%2$s" target="_blank">get your API keys</a>.', 'squad-payments-woo' ), 'https://squadco.com', 'https://dashboard.squadco.com/settings' );
		$this->has_fields         = false;


		$this->subaccount_list = [];
		$this->product_id_list = [];
		$this->subtotal_charge = [];
		$this->transaction_charge = [];
		$this->subaccount_ratio = [];
		$this->base_url = 'https://sqaudco.com';
		$this->icon = plugins_url('assets/img/rave.png', FLW_WC_PLUGIN_FILE);
		
		// declare support for Woocommerce subscription
		$this->supports = array(
			'products',
			'tokenization', 
			'subscriptions',
			'subscription_cancellation', 
			'subscription_suspension', 
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
		);
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'                          => array(
				'title'       => __( 'Enable/Disable', 'squad-payments-woo' ),
				'label'       => __( 'Enable Squad Payment Gateway', 'squad-payments-woo' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable Squad Payment Gateway as a payment option on the checkout page.', 'squad-payments-woo' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'title'              => array(
				'title'       => __( 'Title', 'squad-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'This controls the payment method title which the user sees during checkout.', 'squad-payments-woo' ),
				'default'     => __( 'Squad Payment Gateway', 'squad-payments-woo' ),
				'desc_tip'    => true,
			),

			'description'        => array(
				'title'       => __( 'Description', 'squad-payments-woo' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the payment method description which the user sees during checkout.', 'squad-payments-woo' ),
				'default'     => __( 'Make payment using your debit and credit cards', 'squad-payments-woo' ),
				'desc_tip'    => true,
			),
			'testmode'                         => array(
				'title'       => __( 'Test mode', 'squad-payments-woo' ),
				'label'       => __( 'Enable Test Mode', 'squad-payments-woo' ),
				'type'        => 'checkbox',
				'description' => __( 'Test mode enables you to test payments before going live. <br />Once the LIVE MODE is enabled on your Squad account uncheck this.', 'squad-payments-woo' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_secret_key'                  => array(
				'title'       => __( 'Test Secret Key', 'squad-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Enter your Test Secret Key here', 'squad-payments-woo' ),
				'default'     => '',
			),
			'test_public_key'                  => array(
				'title'       => __( 'Test Public Key', 'squad-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Enter your Test Public Key here.', 'squad-payments-woo' ),
				'default'     => '',
			),
			'live_secret_key'                  => array(
				'title'       => __( 'Live Secret Key', 'squad-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Enter your Live Secret Key here.', 'squad-payments-woo' ),
				'default'     => '',
			),
			'live_public_key'                  => array(
				'title'       => __( 'Live Public Key', 'squad-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Enter your Live Public Key here.', 'squad-payments-woo' ),
				'default'     => '',
			),
			'autocomplete_order'               => array(
				'title'       => __( 'Autocomplete Order After Payment', 'squad-payments-woo' ),
				'label'       => __( 'Autocomplete Order', 'squad-payments-woo' ),
				'type'        => 'checkbox',
				'class'       => 'wc-squad-autocomplete-order',
				'description' => __( 'If enabled, the order will be marked as complete after successful payment', 'squad-payments-woo' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'custom_metadata'                  => array(
				'title'       => __( 'Custom Metadata', 'squad-payments-woo' ),
				'label'       => __( 'Enable Custom Metadata', 'squad-payments-woo' ),
				'type'        => 'checkbox',
				'class'       => 'wc-squad-metadata',
				'description' => __( 'If enabled, you will be able to send customer information about the order to Squad.', 'squad-payments-woo' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'payment_options' => array(
				'title'             => __( 'Payment Options', 'squad-payments-woo' ),
				'type'              => 'multiselect',
				'class'             => 'wc-enhanced-select',
				'default'           => ['card'],
				'description'       => __( 'Choice of payment method to use. Card, Transfer etc.', 'squad-payments-woo' ),
				'options'           => array(
					'card' => __("Card", "squad-payments-woo"),
					'transfer' => __("Transfer", "squad-payments-woo"),
					'ussd' => __("USSD", "squad-payments-woo"),
					'bank' => __("Bank", "squad-payments-woo"),
				),
				'custom_attributes' => array(
					'data-placeholder' => __( 'Select payment options', 'squad-payments-woo' ),
				),
			),
			'instructions'       => array(
				'title'       => __( 'Instructions', 'squad-payments-woo' ),
				'type'        => 'textarea',
				'description' => __( 'Message before delivery. eg Your order will be delivered soon.', 'squad-payments-woo' ),
				'default'     => __( 'Your order will be delivered soon.', 'squad-payments-woo' ),
				'desc_tip'    => true,
			),
			'webhook_url'                  => array(
				'title'       => __( 'Webhook url', 'squad-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Enter a url to submit(post) your order details to after purchase', 'squad-payments-woo' ),
				'default'     => '',
				'desc_tip'    => true,
				'custom_attributes' => array(
					'data-placeholder' => __( 'eg. https://example.com', 'squad-payments-woo' ),
				),
			),
		);
	}


	/** 
	 * Outputs scripts used for squad payment.
	 */
	public function load_payment_scripts() {

		if ( ! is_checkout_pay_page() ) {
			return;
		}

		if (  $this->enabled === 'no'  ) {
			return;
		}

		$order_key = urldecode( sanitize_text_field( isset($_GET['key'] ) ? $_GET['key']:''));
		$order_id  = absint( get_query_var( 'order-pay' ) );

		$order = wc_get_order( $order_id );

		//check payment method
		$payment_method = method_exists( $order, 'get_payment_method' ) ? $order->get_payment_method() : $order->payment_method;

		//exit script if mine is not selected
		if ( $this->id !== $payment_method ) {
			return;
		}

		wp_enqueue_script( 'jquery' );

		wp_enqueue_script( 'squad', 'https://checkout.squadinc.co/widget/squad.min.js', array( 'jquery' ), WC_SQUAD_VERSION, false );

		//wc_squad--> js key name
		wp_enqueue_script( 'wc_squad', plugins_url( 'assets/js/squad.js', WC_SQUAD_MAIN_FILE ), array( 'jquery', 'squad' ), WC_SQUAD_VERSION, false );

		$squad_params = array(
			'public_key' => $this->public_key,
		);

		if ( is_checkout_pay_page() && get_query_var( 'order-pay' ) ) {

			$email         	= method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;
			$amount        	= $order->get_total() * 100;
			$txnref        	= 'WOO' . $order_id . 'T' . time();//gen txnref from order id
			$txnref    		= filter_var($txnref, FILTER_SANITIZE_STRING);//sanitizr=e this field

			$the_order_id  = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
			$the_order_key = method_exists( $order, 'get_order_key' ) ? $order->get_order_key() : $order->order_key;
       
			if ( $the_order_id == $order_id && $the_order_key == $order_key ) {

				$squad_params['email']        = $email;
				$squad_params['amount']       = $amount;
				$squad_params['order_id']     = $order_id;
				$squad_params['txnref']       = $txnref;
				$squad_params['webhook_url']  = $this->webhook_url;
				$squad_params['payment_options']  = $this->payment_options;
				$squad_params['currency']     = get_woocommerce_currency();
				$squad_params['bank_channel'] = 'true';
				$squad_params['card_channel'] = 'true';
			}

			if ( $this->custom_metadata ) {

				//--> Include order id meta
				$squad_params['meta_order_id'] = $order_id;

				//include name
				$first_name = method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : $order->billing_first_name;
				$last_name  = method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : $order->billing_last_name;

				$squad_params['meta_name'] = $first_name . ' ' . $last_name;


				//Include phone
				$billing_phone = method_exists( $order, 'get_billing_phone' ) ? $order->get_billing_phone() : $order->billing_phone;
				$squad_params['meta_phone'] = $billing_phone;

				//Include products
				$line_items = $order->get_items();
				$products = '';

				foreach ( $line_items as $item_id => $item ) {
					$name      = $item['name'];
					$quantity  = $item['qty'];
					$products .= $name . ' (Qty: ' . $quantity . ')';
					$products .= ' | ';
				}

				$products = rtrim( $products, ' | ' );
				$squad_params['meta_products'] = $products;


				//--> Billing address
				$billing_address = $order->get_formatted_billing_address();
				$billing_address = esc_html( preg_replace( '#<br\s*/?>#i', ', ', $billing_address ) );

				$squad_params['meta_billing_address'] = $billing_address;

				//--> Shipping address
				$shipping_address = $order->get_formatted_shipping_address();
				$shipping_address = esc_html( preg_replace( '#<br\s*/?>#i', ', ', $shipping_address ) );

				if ( empty( $shipping_address ) ) {

					$billing_address = $order->get_formatted_billing_address();
					$billing_address = esc_html( preg_replace( '#<br\s*/?>#i', ', ', $billing_address ) );

					$shipping_address = $billing_address;

				}

				$squad_params['meta_shipping_address'] = $shipping_address;

			}

			//--> register/add '_squad_txn_ref' variable to the(this) current order
			update_post_meta( $order_id, '_squad_txn_ref', $txnref );
		}

		//--> retrieve "wc_squad" as set above and include the params
		//--> also, post 'squad_params' as 'wc_squad_params' on 'squad.js' page
		wp_localize_script( 'wc_squad', 'wc_squad_params', $squad_params );
	}

	/**
	 * Load admin scripts
	 */
	public function admin_scripts() {

		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			// return;
		}

		$squad_admin_params = array(
			'plugin_url' => FLW_WC_ASSET_URL,
			'countSubaccount' => $this->get_option( 'subaccount_count_saved' )
		  );
		wp_enqueue_script( 'wc_squad_admin', plugins_url( 'assets/js/squad-admin.js', WC_SQUAD_MAIN_FILE ), array(), WC_SQUAD_VERSION, true );
	  
		//post 'squad_admin_params' as 'wc_squad_admin_params' on 'squad-admin.js' page
		wp_localize_script( 'wc_squad_admin', 'wc_squad_admin_params', $squad_admin_params );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );


		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}


	/**
	 * Displays the payment page
	 */
	public function receipt_page( $order_id ) {
		$order = wc_get_order( $order_id );
		// $order = new WC_Order( $order_id );

		echo '<div id="wc-squad-form">';

		echo '<p>' . __( 'Thank you for your order, please click the button below to pay with Squad.', 'squad-payments-woo' ) . '</p>';

		echo '<div id="squad_form"><form id="order_review" method="post" action="' . WC()->api_request_url( 'WC_Gateway_Squad' ) . '"></form><button class="button" id="squad-payment-button">' . __( 'Make Payment', 'squad-payments-woo' ) . '</button>';

		echo '  <a class="button cancel" id="squad-cancel-payment-button" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'squad-payments-woo' ) . '</a></div>';

		echo '</div>';

	}



	/**
	 * Check If The Gateway Is Available For Use(enabled).
	 *
	 * @return bool
	 */
	public function is_available() {

		if ( 'yes' === $this->enabled ) {
			if ( ! ( $this->public_key && $this->secret_key ) ) {
				return false;
			}
			return true;
		}
		return false;
	}

	/**
	 * Display Squad payment icon.
	 */
	public function get_icon() {
		$base_location = wc_get_base_location();

		if ( 'NG' === $base_location['country'] ) {
			$icon = '<img src="' . WC_HTTPS::force_https_url( plugins_url( 'assets/images/logo.png', WC_SQUAD_MAIN_FILE ) ) . '" alt="Squad Payment Options" />';
		}else {
			$icon = '<img src="' . WC_HTTPS::force_https_url( plugins_url( 'assets/images/logo.png', WC_SQUAD_MAIN_FILE ) ) . '" alt="Squad Payment Options" />';
		}

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page() {
		if ( $this->instructions ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
			return;
		}

		$order = wc_get_order( $orderid );
	}

	/**
	 * Change payment complete order status to completed for squad orders.
	 *
	 * @since  1.0.1
	 * @param  string         $status Current order status.
	 * @param  int            $order_id Order ID.
	 * @param  WC_Order|false $order Order object.
	 * @return string
	 */
	public function change_payment_complete_order_status( $status, $order_id = 0, $order = false ) {
		if ( $order && 'squad' === $order->get_payment_method() ) {
			$status = 'completed';
		}
		return $status;
	}


	/**
	 * Verify Squad payment
	 */
	public function squad_verify_transaction() {

		if ( isset( $_REQUEST['squad_txnref'] ) ) {
			$squad_txn_ref = sanitize_text_field( $_REQUEST['squad_txnref'] );
		} else {
			$squad_txn_ref = false;
		}

		@ob_clean();
		if ( $squad_txn_ref ) {

			$squad_verify_url = "https://qa-api.squadinc.co/payment/TransactionHistory/UniqueTransaction?transaction_ref=${squad_txn_ref}";

			$headers = array(
				'Authorization' => 'Bearer ' . $this->secret_key,
			);

			$args = array(
				'headers' => $headers,
				'timeout' => 60,
			); 

			$request = wp_remote_get( $squad_verify_url, $args );

			if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {

				$squad_response = json_decode( wp_remote_retrieve_body( $request ) );

				$success = $squad_response->data->status;
				$success = true;//temp-> to be removed later!

				if ( $success ) {

					$order_details = explode( '_', $squad_response->data->reference );
					$order_details = explode( '_', $squad_txn_ref );// to be removed later
					$order_id      = (int) $order_details[1];
					$order         = wc_get_order( $order_id );

					if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {

						wp_redirect( $this->get_return_url( $order ) );

						exit;
					}

					$order_total      = $order->get_total();
					$order_currency   = method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->get_order_currency();
					$currency_symbol  = get_woocommerce_currency_symbol( $order_currency );
					$amount_paid      = $squad_response->data->amount / 100;
					$amount_paid      = $order_total; // to be removed later
					$squad_ref     = $squad_response->data->reference;
					$squad_ref     = $squad_txn_ref; // to be removed later
					$payment_currency = strtoupper( $squad_response->data->currency );
					$payment_currency = $currency_symbol; // to be removed later
					$gateway_symbol   = get_woocommerce_currency_symbol( $payment_currency );

					// check if the amount paid is equal to the order amount.
					if ( $amount_paid < $order_total ) {

						$order->update_status( 'on-hold', '' );

						add_post_meta( $order_id, '_transaction_id', $squad_ref, true );

						$notice      = sprintf( __( 'Thank you for shopping with us.%1$sYour payment transaction was successful, but the amount paid is not the same as the total order amount.%2$sYour order is currently on hold.%3$sKindly contact us for more information regarding your order and payment status.', 'squad-payments-woo' ), '<br />', '<br />', '<br />' );
						$notice_type = 'notice';

						// Add Customer Order Note
						$order->add_order_note( $notice, 1 );

						// Add Admin Order Note
						$admin_order_note = sprintf( __( '<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Amount paid is less than the total order amount.%3$sAmount Paid was <strong>%4$s (%5$s)</strong> while the total order amount is <strong>%6$s (%7$s)</strong>%8$s<strong>Squad Transaction Reference:</strong> %9$s', 'squad-payments-woo' ), '<br />', '<br />', '<br />', $currency_symbol, $amount_paid, $currency_symbol, $order_total, '<br />', $squad_ref );
						$order->add_order_note( $admin_order_note );

						function_exists( 'wc_reduce_stock_levels' ) ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();

						wc_add_notice( $notice, $notice_type );

					} else {

						if ( $payment_currency !== $order_currency ) {

							$order->update_status( 'on-hold', '' );

							update_post_meta( $order_id, '_transaction_id', $squad_ref );

							$notice      = sprintf( __( 'Thank you for shopping with us.%1$sYour payment was successful, but the payment currency is different from the order currency.%2$sYour order is currently on-hold.%3$sKindly contact us for more information regarding your order and payment status.', 'squad-payments-woo' ), '<br />', '<br />', '<br />' );
							$notice_type = 'notice';

							// Add Customer Order Note
							$order->add_order_note( $notice, 1 );

							// Add Admin Order Note
							$admin_order_note = sprintf( __( '<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Order currency is different from the payment currency.%3$sOrder Currency is <strong>%4$s (%5$s)</strong> while the payment currency is <strong>%6$s (%7$s)</strong>%8$s<strong>Squad Transaction Reference:</strong> %9$s', 'squad-payments-woo' ), '<br />', '<br />', '<br />', $order_currency, $currency_symbol, $payment_currency, $gateway_symbol, '<br />', $squad_ref );
							$order->add_order_note( $admin_order_note );

							function_exists( 'wc_reduce_stock_levels' ) ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();

							wc_add_notice( $notice, $notice_type );

						} else {

							$order->payment_complete( $squad_ref );
							$order->add_order_note( sprintf( __( 'Payment via Squad successful (Transaction Reference: %s)', 'squad-payments-woo' ), $squad_ref ) );

							if ( $this->is_autocomplete_order_enabled( $order ) ) {
								$order->update_status( 'completed' );
							}
						}
					}

					WC()->cart->empty_cart();

				} else {

					$order_details = explode( '_', $_REQUEST['squad_txnref'] );

					$order_id = (int) $order_details[1];

					$order = wc_get_order( $order_id );

					$order->update_status( 'failed', __( 'Payment was declined by Squad.', 'squad-payments-woo' ) );
				}
			}

			wp_redirect( $this->get_return_url( $order ) );

			exit;
		}

		wp_redirect( wc_get_page_permalink( 'cart' ) );

		exit;
	}

	/**
	 * Checks if autocomplete order is enabled for the payment method.
	 *
	 * @since 1.0
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	protected function is_autocomplete_order_enabled( $order ) {
		$autocomplete_order = false;

		$payment_method = $order->get_payment_method();

		$squad_settings = get_option('woocommerce_' . $payment_method . '_settings');

		if ( isset( $squad_settings['autocomplete_order'] ) && 'yes' === $squad_settings['autocomplete_order'] ) {
			$autocomplete_order = true;
		}

		return $autocomplete_order;
	}

	/**
	 * Process Webhook
	 */
	public function process_webhooks() {

		if ( ( strtoupper( sanitize_text_field(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') ) != 'POST' ) || ! array_key_exists( 'HTTP_X_SQUAD_SIGNATURE', $_SERVER ) ) {
			exit;
		}

      // Retrieve the request's body
		$json = @file_get_contents( 'php://input' );

		// validate event do all at once to avoid timing attack
		if ( hash_hmac( 'sha512', $json, $this->secret_key ) !== $_SERVER['HTTP_X_SQUAD_SIGNATURE'] ) {
			exit;
		}

		$event = json_decode( $json );

		if ( 'charge.success' == $event->event ) {

			sleep( 10 );

			$order_details = explode( '_', $event->data->reference );

			$order_id = (int) $order_details[0];

			$order = wc_get_order( $order_id );

			$squad_txn_ref = get_post_meta( $order_id, '_squad_txn_ref', true );

			if ( $event->data->reference != $squad_txn_ref ) {
				exit;
			}

			http_response_code( 200 );

			if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {
				exit;
			}

			// Log successful transaction to Squad plugin metrics tracker.
			$squad_logger = new WC_Squad_Plugin_Tracker( 'squad-payments-woo', $this->public_key );
			$squad_logger->log_transaction( $event->data->reference );

			$order_currency = method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->get_order_currency();

			$currency_symbol = get_woocommerce_currency_symbol( $order_currency );

			$order_total = $order->get_total();

			$amount_paid = $event->data->amount / 100;

			$squad_ref = $event->data->reference;

			// check if the amount paid is equal to the order amount.
			if ( $amount_paid < $order_total ) {

				$order->update_status( 'on-hold', '' );

				add_post_meta( $order_id, '_transaction_id', $squad_ref, true );

				$notice      = 'Thank you for shopping with us.<br />Your payment transaction was successful, but the amount paid is not the same as the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
				$notice_type = 'notice';

				// Add Customer Order Note
				$order->add_order_note( $notice, 1 );

				// Add Admin Order Note
				$order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was <strong>' . $currency_symbol . $amount_paid . '</strong> while the total order amount is <strong>' . $currency_symbol . $order_total . '</strong><br />Squad Transaction Reference: ' . $squad_ref );

				$order->reduce_order_stock();

				wc_add_notice( $notice, $notice_type );

				wc_empty_cart();

			} else {

				$order->payment_complete( $squad_ref );
				/* translators: %s: transaction reference */
				$order->add_order_note( sprintf( 'Squad Transaction Ref: %s', $squad_ref ) );

				wc_empty_cart();
			}

			exit;
		}

		exit;
	}
}