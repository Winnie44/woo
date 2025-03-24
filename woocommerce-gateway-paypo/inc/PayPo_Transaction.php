<?php
/**
 * PayPo Transaction class.
 *
 * @package PayPo
 */

namespace PayPo;

class PayPo_Transaction extends PayPo_Connect {

	/**
	 * Merchant ID
	 *
	 * @var string $merchant_id from PayPo settings.
	 */
	protected $merchant_id;

	/**
	 * Merchant secret
	 *
	 * @var string $merchant_secret from PayPo settings.
	 */
	protected $merchant_secret;



	/**
	 * Init PayPo transaction class
	 * This class includes methods and properties for PayPo API transaction submit.
	 *
	 * @param string $merchant_id Merchant ID from PayPo settings.
	 * @param string $merchant_secret Merchant secret from PayPo settings.
	 * @param string $sandbox If is in sandbox mode for testing, accepted: yes, no.
	 */
	public function __construct( $merchant_id, $merchant_secret, $sandbox ) {
		parent::__construct( $sandbox );
		$this->merchant_id     = $merchant_id;
		$this->merchant_secret = $merchant_secret;
	}


    /**
     * Return PayPo order ID
     *
     *
     * @param integer $order_id WooCommerce order ID.
     * @return string PoyPo order ID.
     */
    public function get_paypo_order_ID( $order_id ) : string {
        if ( metadata_exists('post', $order_id, '_paypo_order_id' ) ) {
	        return get_post_meta( $order_id, '_paypo_order_id', true );
        } else {
	        return $order_id . '-' . time();
        }
    }



	/**
	 * Product type
	 *
	 * @return string Available options: core, PNX.
	 */
	private function get_product_type() : string {
    	$configuration = get_option( 'woocommerce_paypo_settings', false );
    	if ( $configuration ) {
    		return $configuration['product_type'];
	    }
    	return 'CORE';
    }



	/**
	 * Installment count.
	 *
	 * @return int No. of installment defined within gateway configuration. Minimum amount is 1.
	 */
	private function get_installment_count() : int {
		$configuration = get_option( 'woocommerce_paypo_settings', false );
		if ( $configuration ) {
			return (int) $configuration['installment_count'];
		}
		return 1;
	}



	/**
	 * Prepare transaction body to post
	 *
	 * @param integer $order_id WooCommerce order ID.
	 * @return array
	 */
	private function transaction_post_body( $order_id ) {

		$order        = wc_get_order( $order_id );
		$product_type = $this->get_product_type();

		$transaction = array(
			'merchantId'    => $this->merchant_id,
			'order'         => array(
				'referenceId'     => $this->get_paypo_order_ID( $order_id ),
				'amount'          => $this->amount_conversion( $order->get_total() ),
				'billingAddress'  => array(
					'street'   => esc_attr( $order->get_billing_address_1() ) . ' ' . esc_attr( $order->get_billing_address_2() ),
					'building' => '',
					'flat'     => '',
					'zip'      => esc_attr( $order->get_billing_postcode() ),
					'city'     => esc_attr( $order->get_billing_city() ),
				),
				'shippingAddress' => array(
					'street'   => ( $order->get_shipping_address_1() ? esc_attr( $order->get_shipping_address_1() ) . ' ' . esc_attr( $order->get_shipping_address_2() ) : esc_attr( $order->get_billing_address_1() ) . ' ' . esc_attr( $order->get_billing_address_2() ) ),
					'building' => '',
					'flat'     => '',
					'zip'      => ( $order->get_shipping_postcode() ? esc_attr( $order->get_shipping_postcode() ) : esc_attr( $order->get_billing_postcode() ) ),
					'city'     => ( $order->get_shipping_city() ? esc_attr( $order->get_shipping_city() ) : esc_attr( $order->get_billing_city() ) ),
				),
			),
			'customer'      => array(
				'name'    => esc_attr( $order->get_billing_first_name() ),
				'surname' => esc_attr( $order->get_billing_last_name() ),
				'email'   => esc_attr( $order->get_billing_email() ),
				'phone'   => esc_attr( $order->get_billing_phone() ),
			),
			'configuration' => array(
				'returnUrl' => $order->get_checkout_order_received_url(),
				'notifyUrl' => WC()->api_request_url( 'paypo_notification' ),
				'cancelUrl' => wc_get_checkout_url(),
				'product'   => array(
					'productType'      => $product_type,
					'installmentCount' => 'PNX' === $product_type ? $this->get_installment_count() : 4,
				)
			),
		);

		return $transaction;
	}



	/**
	 *  Post transaction to PayPo
	 *
	 * @param integer $order_id WooCommerce order ID.
	 * @return array transactionId, redirectUrl.
	 */
	public function transaction_post( $order_id ) {

		// Cookie variables.
		$cookie_name  = sanitize_key( 'PayPo_transaction' );
		$cookie_value = array();

		// Check if cookie exists.
		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			$cookie_value = json_decode( wp_unslash( $_COOKIE[ $cookie_name ] ), TRUE );
		}

		// Check whenever order id are the same.
		if ( ! empty( $cookie_value ) && $cookie_value['order_id'] === $this->get_paypo_order_ID( $order_id ) ) {

			return $cookie_value;

		} else { // Proceed to new transaction.

			// Get token.
			$token = $this->get_access_token( $this->merchant_id, $this->merchant_secret );

			// Check if token is OK.
			if ( $token ) {
				$args = array(
					'headers' => array(
						'Accept'        => 'application/json',
						'Content-Type'  => 'application/json',
						'Authorization' => $token['token_type'] . ' ' . $token['access_token'],
					),
					'body'    => wp_json_encode( $this->transaction_post_body( $order_id ) ),
				);

				// Get response.
				$response = $this->remote_post( '/transactions', $args );

				// Check if required data exists.
				if ( array_key_exists( 'redirectUrl', $response ) ) {
					$cookie_arg           = [
						'expires' => time() + $token['expires_in'],
						'path'    => '/',
					];
					$response['order_id'] = $order_id; // Add order id.
					setcookie( $cookie_name, wp_json_encode( $response ), $cookie_arg );

					return $response;
				}
			}
		}
	}


	/**
	 * Patch PoyPo status.
	 *
	 * @param integer $order_id Order Id.
	 * @param string  $status PayPo status to change.
	 * @return bool .
	 */
	public function patch_status( $order_id, $status ) {

		$transaction_id = get_post_meta( $order_id, '_paypo_transaction_id', true );
		$token          = $this->get_access_token( $this->merchant_id, $this->merchant_secret );

		if ( $transaction_id && $token ) {
			$args     = [
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => $token['token_type'] . ' ' . $token['access_token'],
				],
				'body'    => wp_json_encode( [ 'status' => $status ] ),
				'method'  => 'PATCH',
			];
			$response = $this->remote_request( '/transactions/' . $transaction_id, $args );
			if ( 200 === $response['code'] ) {
				return true;
			}
			return false;
		}
	}

}