<?php
/**
 * PayPo Refund class.
 *
 * @package PayPo
 */

namespace PayPo;

class PayPo_Refund_Cancel extends PayPo_Connect {

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
	 * Init PayPo refund class
	 * This class includes methods and properties for PayPo API refund submit.
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
	 * Refund order
	 *
	 * @param integer $order_id Order Id.
	 * @param string  $amount Order amount.
	 * @return array
	 */
	public function post_refund( $order_id, $amount ) {

		$transaction_id = get_post_meta( $order_id, '_paypo_transaction_id', TRUE );
		$token          = $this->get_access_token( $this->merchant_id, $this->merchant_secret );

		if ( $transaction_id && $amount && $token ) {
			$args     = array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => $token['token_type'] . ' ' . $token['access_token'],
				),
				'body'    => wp_json_encode( array( 'amount' => $this->amount_conversion( $amount ) ) ),
			);
			$response = $this->remote_post( '/transactions/' . $transaction_id . '/refunds', $args );

			return $response;
		}
	}

}