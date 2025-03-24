<?php
/**
 * PayPo order class.
 *
 * @package PayPo
 */

namespace PayPo;

/**
 * PayPoOrder class. The flow for order are made here.
 *
 * @package PayPo
 */
class PayPo_Gateway extends \WC_Payment_Gateway {

	/**
	 * Plugin info
	 * Contains names, desription, version, paths, etc.
	 *
	 * @var object $plugin_info
	 */
	protected $plugin_info;
	protected $payment_complete_status;

	/**
	 * Init PayPo payment gateway
	 *
	 * @param object $plugin_info .
	 */
	public function __construct( $plugin_info ) {

		// Plugin info object.
		$this->plugin_info = $plugin_info;

		// WooCommerce payement gateway details.
		$this->id                 = 'paypo'; // payment gateway plugin ID.
		$this->method_title       = 'PayPo'; // Method title.
		$this->method_description = __( 'PayPo - deferred payment', 'woocommerce-gateway-paypo' ); // will be displayed on the options page.
		$this->has_fields         = false; // no custom input fields like for eg. credit card are needed.
		$this->supports           = array( 'products', 'refunds' );

		// Settings.
		$this->init_form_fields();
		$this->init_settings();

		// Gateway variables.
		$this->title                   = $this->get_option( 'title' );
		$this->enabled                 = $this->get_option( 'enabled' );
        $this->banner                  = '<img class="payment_box_logo" src="' . ( ! empty( $this->get_option( 'banner_url' ) ) ? $this->get_option( 'banner_url' ) : $plugin_info->plugin_url . '/assets/images/PayPo_payment_banner.png' ) . '">';
        $this->desc                    = ( ! empty( $this->get_option( 'desc' ) ) ? '<span class="payment_box_desc">' . $this->get_option( 'desc' ) . '</span>' : '' );
		$this->description             = $this->banner. $this->desc;
		$this->merchant_id             = $this->get_option( 'merchant_id' );
		$this->merchant_secret         = $this->get_option( 'merchant_secret' );
		$this->payment_complete_status = $this->get_option( 'payment_complete_status' );
		$this->order_limit_min         = $this->get_option( 'order_limit_min' );
		$this->order_limit_max         = $this->get_option( 'order_limit_max' );
		$this->sandbox                 = $this->get_option( 'sandbox' );

		// Run hooks.
		$this->gateway_hooks();
	}



	/**
	 * Init actions required by this gateway
	 */
	public function gateway_hooks() {

		// Save admin (settings form) fields.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Cancel order handle.
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'process_cancel' ) , 21, 1 );

		// Refund order handle.
		add_action( 'woocommerce_order_status_refunded', array( $this, 'process_refund' ) , 21, 1 );

		// Set PayPo status to completed.
		add_action( 'woocommerce_order_status_changed', array( $this, 'update_payment_status_completed' ) , 10, 4 );

		// Display or hide PayPo gateway in checkout page.
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'gateway_availability_in_checkout' ), 999, 1 );

		add_action( 'woocommerce_api_paypo_notification', array( new PayPo_Notification( $this->merchant_id, $this->merchant_secret, $this->sandbox, $this->payment_complete_status ), 'response_handler' ) );

	}



	/**
	 * Admin settings form
	 *
	 * @return void
	 */
	public function init_form_fields() {

		$this->form_fields = array(

			// Default - WooCommerce required.
			'enabled'                 => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-paypo' ),
				'label'   => __( 'Enable PayPo Payment Gateway', 'woocommerce-gateway-paypo' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
            'title'                    => array(
                'title'       => __( 'Title', 'woocommerce-gateway-paypo' ),
                'type'        => 'text',
                'description' => __( 'Payment gateway title', 'woocommerce-gateway-paypo' ),
                'default'     => __( 'PayPo | Buy now, pay in a month', 'woocommerce-gateway-paypo' ),
            ),
			'desc'                    => array(
				'title'       => __( 'Description', 'woocommerce-gateway-paypo' ),
				'type'        => 'textarea',
				'description' => __( 'Add short description for Your customers', 'woocommerce-gateway-paypo' ),
				'default'     => __( 'Buy now, pay in a month!', 'woocommerce-gateway-paypo' ),
			),

			// PayPo authorization.
			'merchant_id'             => array(
				'title'       => __( 'Merchant ID', 'woocommerce-gateway-paypo' ),
				'type'        => 'text',
				'description' => __( 'Set merchant id provided by PayPo.', 'woocommerce-gateway-paypo' ),
			),
			'merchant_secret'         => array(
				'title'       => __( 'Merchant Secret', 'woocommerce-gateway-paypo' ),
				'type'        => 'text',
				'description' => __( 'Set merchant secret provided by PayPo.', 'woocommerce-gateway-paypo' ),
			),

			// Sandbox.
			'sandbox'                 => array(
				'title'       => __( 'Enable Sandbox Mode', 'woocommerce-gateway-paypo' ),
				'type'        => 'select',
				'description' => __( 'You can enable test mode for sandbox integration.', 'woocommerce-gateway-paypo' ) . '<br>' .
								 __(  'Remeber to provide Merchant ID and Merchant Secret for sandbox service', 'woocommerce-gateway-paypo' ),
				'desc_tip'    => false,
				'options'     => array(
					'no'  => __( 'No', 'woocommerce-gateway-paypo' ),
					'yes' => __( 'Yes', 'woocommerce-gateway-paypo' ),
				),
			),

			// Paid status.
			'payment_complete_status' => array(
				'title'       => __( 'Set payment complete status', 'woocommerce-gateway-paypo' ),
				'type'        => 'select',
				'description' => __( 'Select how You want to complete PayPo transaction:.', 'woocommerce-gateway-paypo' ) . '<br>' .
								 __( 'Automatically - send confirmation immediately', 'woocommerce-gateway-paypo' ) . '<br>' .
								 __( 'Manually - send confirmation by changing order status to Complete', 'woocommerce-gateway-paypo' ),
				'desc_tip'    => false,
				'default'     => 'manually',
				'options'     => array(
					'automatically'  => __( 'Automatically', 'woocommerce-gateway-paypo' ),
					'manually'       => __( 'Manually', 'woocommerce-gateway-paypo' ),
				),
			),

			// Product type.
			'product_type' => array(
				'title'       => __( 'Product type', 'woocommerce-gateway-paypo' ),
				'type'        => 'select',
				'desc_tip'    => false,
				'default'     => 'CORE',
				'options'     => array(
					'CORE'  => __( 'CORE', 'woocommerce-gateway-paypo' ),
					'PNX'   => __( 'PNX', 'woocommerce-gateway-paypo' ),
				),
			),

			// Installment count.
			'installment_count'     => array(
				'title'             => __( 'Installment count', 'woocommerce-gateway-paypo' ),
				'type'              => 'number',
				'default'           => 1,
				'custom_attributes' => array(
					'min'      => 1,
					'step'     => 1,
				)
			),

			// Order limits.
			'order_limit_min'         => array(
				'title'             => __( 'Minimum order amount for payment', 'woocommerce-gateway-paypo' ),
				'type'              => 'number',
				'description'       => __( 'Set the minimum value of orders handled by PayPo. Our minimum of serviced transactions is 10 PLN. Choose when PayPo should be displayed on checkout page.', 'woocommerce-gateway-paypo' ),
				'default'           => '',
				'custom_attributes' => array(
					'min'  => 10,
					'step' => 0.01,
				),
			),

			'order_limit_max'         => array(
				'title'             => __( 'Maximum order amount for payment', 'woocommerce-gateway-paypo' ),
				'type'              => 'number',
				'description'       => __( 'Set the maximum value of orders handled by PayPo. Choose when PayPo should be displayed on checkout page.', 'woocommerce-gateway-paypo' ),
				'default'           => '',
				'custom_attributes' => array(
					'min'  => 10.01,
					'step' => 0.01,
				),
			),

            // Custom banner
            'banner_url'             => array(
                'title'       => __( 'Custom banner', 'woocommerce-gateway-paypo' ),
                'type'        => 'text',
                'description' => __( 'Url for custom banner visible over description. Leave blank for default.', 'woocommerce-gateway-paypo' ),
                'placeholder' => $this->plugin_info->plugin_url . '/assets/images/PayPo_payment_banner.png',
            ),
		);
	}



	/**
	 * Payment process
	 *
	 * @param integer $order_id WooCommerce order Id.
	 * @return array|void
	 */
	public function process_payment( $order_id ) {

		$transaction = new PayPo_Transaction( $this->merchant_id, $this->merchant_secret, $this->sandbox );
		$response    = $transaction->transaction_post( $order_id );

		if ( $response ) {
			update_post_meta( $order_id, '_paypo_transaction_id', $response['transactionId'] );

			return array(
				'result'   => 'success',
				'redirect' => $response['redirectUrl'],
			);
		} else {
			wc_add_notice( sprintf("<strong>%s</strong> %s", __( 'Error', 'woocommerce-gateway-paypo' ), __( 'Unfortunately, we encountered an error during the payment process. Please contact the site administrator', 'woocommerce-gateway-paypo' ) ), 'error' );
			error_log( print_r( $response, true ) );
		}

	}



	/**
	 * Update order payment status from PayPo response
	 *
	 * @param integer $order_id WooCommerce order Id.
	 */
	public function update_payment_status( $order_id ) {

        global $woocommerce;
        $order = new \WC_Order( $order_id ); // Get order by ID

		if ( $this->id === $order->get_payment_method() ) {
			$status = $_GET['status'];

			// Check if cookie exist and unset it for future transactions.
			$cookie_name = sanitize_key( 'PayPo_transaction' );
			if ( isset( $_COOKIE[ $cookie_name ] ) ) {
				unset( $_COOKIE[ $cookie_name ] );
				setcookie( $cookie_name, NULL, - 1, '/' );
			}

			if ( isset( $status ) && 'OK' === $status ) {

				// we received the payment
				$order->payment_complete();

				// Empty cart
				$woocommerce->cart->empty_cart();

			} else {

				// Add order note
				$order->add_order_note( __( 'PayPo: Payment rejected', 'woocommerce-gateway-paypo' ) );

				// Redirect to payment.
				wp_redirect( $order->get_checkout_payment_url( $on_checkout = FALSE ) );
				exit;

			}
		}
	}



	/**
	 * Change PayPo status to COMPLETED when automatic setting is enable & Woo status changed to completed
	 *
	 * @param integer   $order_id WooCommerce order Id.
	 * @param string    $this_status_transition_from Order status before change.
	 * @param string    $this_status_transition_to   Target order status.
	 * @param \WC_Order $order WooCommerce order.
	 * @return void
	 */
	public function update_payment_status_completed( $order_id, $this_status_transition_from, $this_status_transition_to, $order ) {
		if ( $this->id === $order->get_payment_method() && 'manually' === $this->payment_complete_status && 'completed' === $this_status_transition_to ) {
			$current_status = get_post_meta( $order_id, '_paypo_status', TRUE );
			$status         = 'COMPLETED';
			if ( 'COMPLETED' !== $current_status ) {
				$order    = new \WC_Order( $order_id );
				$update   = new PayPo_Transaction( $this->merchant_id, $this->merchant_secret, $this->sandbox );
				$response = $update->patch_status( $order_id, $status );

				if ( $response ) {
					update_post_meta( $order_id, '_paypo_status', 'COMPLETED' );
					$order->add_order_note( __( 'PayPo: Payment is completed', 'woocommerce-gateway-paypo' ) );
				} else {
					$order->add_order_note( __( 'PayPo: There was an error preventing the order from being completed', 'woocommerce-gateway-paypo' ) );
				}
			}
		}
	}



	/**
	 * WooCommerce refund process handler
	 *
	 * @param integer $order_id WooCommerce order Id.
	 * @param string  $amount WooCommerce order amount.
	 * @param string  $reason Refund reason.
	 * @return boolean
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$order = new \WC_Order( $order_id ); // Get order by ID

		if ( $this->id === $order->get_payment_method() && get_post_meta( $order_id, '_paypo_status', true ) != 'REFUNDED' ) {

			// Amount is null
			if ( null === $amount ) {
				if ( metadata_exists( 'post', $order_id, '_paypo_refund' ) ) {
					$amount = (float) get_post_meta( $order_id, '_paypo_refund', true );
				} else {
					$amount = $order->get_total();
				}
			}

			$refund   = new PayPo_Refund_Cancel( $this->merchant_id, $this->merchant_secret, $this->sandbox );
			$response = $refund->post_refund( $order_id, $amount );

			// Check if response is OK.
			if ( 201 === $response['code'] ) {

				// Check if it is full refund.
				if ( $amount === $order->get_total() ) {

					update_post_meta( $order_id, '_paypo_status', 'REFUNDED' ); // Update PayPo status
					$order->add_order_note( __( 'PayPo: The order has been fully returned', 'woocommerce-gateway-paypo' ) ); // Add order note

				} else {

					// Get remaining refund amount.
					$remaining_refund = $order->get_remaining_refund_amount();
					update_post_meta( $order_id, '_paypo_refund', $remaining_refund );
					$order->add_order_note( __( 'PayPo: The amount has been refunded: ', 'woocommerce-gateway-paypo' ) . $amount ); // Add order note.

				}

				return true;

			}

		}
	}



	/**
	 * Cancel order process handler.
	 *
	 * @param integer $order_id WooCommerce order Id.
	 * @return void
	 */
	public function process_cancel( $order_id ) {

        $order = new \WC_Order( $order_id ); // Get order by ID

		if ( $this->id === $order->get_payment_method() ) {

			// Statuses.
			$current_status = get_post_meta( $order_id, '_paypo_status', TRUE );
			$status         = 'CANCELED';

			// Prevent COMPLETED order from being cancelled.
			if ( 'COMPLETED' !== $current_status ) {
				$update   = new PayPo_Transaction( $this->merchant_id, $this->merchant_secret, $this->sandbox );
				$response = $update->patch_status( $order_id, $status );

				if ( $response ):
					$order->add_order_note( __( 'PayPo: The transaction has been canceled', 'woocommerce-gateway-paypo' ) );
				else:
					$order->add_order_note( __( 'PayPo: There was an error preventing the order from being cancelled', 'woocommerce-gateway-paypo' ) );
				endif;

			} else {
				$order->add_order_note( __( 'PayPo: Completed transaction cannot be cancel', 'woocommerce-gateway-paypo' ) );
			}
		}
	}



	/**
	 * Show or hide payment gateway depended of order total
	 *
	 * @param array $payment_gateways WooCommerce payment gateway.
	 * @return array $payment_gateways WooCommerce payment gateway.
	 */
	public function gateway_availability_in_checkout( $payment_gateways ) {
		if ( ! empty( $payment_gateways ) ) {

			// Order total amount.
			$order_total = \WC()->cart->total;

			if ( isset( $payment_gateways['paypo'] ) && 'yes' === $payment_gateways['paypo']->enabled ) {
				if ( isset( $this->order_limit_min ) && ( $order_total < (float) $this->order_limit_min ) || isset( $this->order_limit_max ) && $order_total > (float) $this->order_limit_max ) {
					unset( $payment_gateways['paypo'] );
				}
			}
		}

		return $payment_gateways;
	}

}