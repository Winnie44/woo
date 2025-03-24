<?php
/**
 * PayPo push notification class.
 *
 * @package PayPo
 * @since 2.5.0
 */

namespace PayPo;

class PayPo_Notification {

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

	protected $mode;



	/**
	 * Init PayPo connect class
	 *
	 * @param string $merchant_id Merchant ID from PayPo settings.
	 * @param string $merchant_secret Merchant secret from PayPo settings.
	 */
	public function __construct( $merchant_id, $merchant_secret, $sandbox, $mode ) {
		$this->merchant_id     = $merchant_id;
		$this->merchant_secret = $merchant_secret;
		$this->sandbox         = $sandbox;
		$this->mode            = $mode;
	}



	/**
	 * Response handler
	 */
	public function response_handler() {

		$response_headers = getallheaders();
		$response_body    = json_decode( file_get_contents( 'php://input' ), true );

		// Validate PayPo signature with HMAC.
		if ( array_key_exists ( 'X-Paypo-Signature' , $response_headers ) && $this->hmac_generate( $response_body ) === $response_headers['X-Paypo-Signature'] ) :

			// Get order & transaction ID.
			$order_id       = explode ( '-', $response_body['referenceId'] )[0];
			$transaction_id = get_post_meta( $order_id, '_paypo_transaction_id', true );

			// Check if order ID exists.
			if ( "shop_order" === get_post_type( $order_id ) && $response_body['transactionId'] === $transaction_id ) :
				if ( $this->transaction_status_handler( $order_id, $response_body ) ):
					http_response_code( 200 );
					echo json_encode( array( 'message' => 'Status updated.' ) );
				endif;
			// Order does not exits.
			else:
				http_response_code( 409 );
				echo json_encode( array( 'message' => 'Order with this reference does not exists.' ) );
			endif;

		else:
			http_response_code( 401 );
			echo json_encode( array( 'message' => 'Unauthorized' ) );
		endif;

		exit();
	}



	/**
	 * Generate HMAC for notification validation
	 *
	 * @param string $response_body JSON, body from PayPo notification.
	 * @return string
	 */
	private function hmac_generate( $response_body ) {

		$endpoint = parse_url( WC()->api_request_url( 'paypo_notification' ) )['path'];
		$json = json_encode( $response_body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$data = 'POST+' . $endpoint . '+' . $json;

		return base64_encode( hash_hmac( 'sha256', $data, $this->merchant_secret, true ) );

	}



	/**
	 * Generate HMAC for notification validation
	 *
	 * @param string $response_body JSON, body from PayPo notification.
	 * @return string
	 */
	private function transaction_status_handler( $order_id, $response_body ) {

		$transaction_status = $response_body['transactionStatus'];

		$order = new \WC_Order( $order_id );

		if ( get_post_meta( $order_id, '_paypo_status', true ) != 'REFUNDED' ) :

			update_post_meta( $order_id, '_paypo_status', $transaction_status );

			switch ( $transaction_status ) :

				// Pending.
				case 'PENDING':
					// Take no action while payment is pending.
					break;

				// Accepted.
				case 'ACCEPTED':
					if ( 'automatically' === $this->mode ) :
						$update = new PayPo_Transaction( $this->merchant_id, $this->merchant_secret, $this->sandbox );
						$response = $update->patch_status( $order_id, 'COMPLETED' );
						if ( ! $response ) :
							$order->add_order_note( __( 'PayPo: There was an error preventing the order from being completed', 'woocommerce-gateway-paypo' ) );
						endif;
					else:
						if ( $order->has_status( 'pending' ) ) :
							$order->update_status( 'processing', __( 'PayPo: Payment is accepted', 'woocommerce-gateway-paypo' ) );
						endif;
					endif;
					break;

				// Completed.
				case 'COMPLETED':
						if ( 'automatically' === $this->mode ) :
							if ( $order->has_status( array( 'pending', 'canceled' ) ) ) :
								$order->update_status( 'processing', __( 'PayPo: Payment is completed', 'woocommerce-gateway-paypo' ) );
							endif;
						else:
							$order->add_order_note( __( 'PayPo: Payment is completed', 'woocommerce-gateway-paypo' ) );
						endif;
					break;

				// Rejected.
				case 'REJECTED':
					$order->add_order_note( __( 'PayPo: Payment rejected', 'woocommerce-gateway-paypo' ) );
					break;

				// Canceled.
				case 'CANCELED':
					if ( 'canceled' != $order->get_status() ) :
						$order->update_status( 'canceled', __( 'PayPo: Transaction has been cancel', 'woocommerce-gateway-paypo' ) );
					endif;
					break;

				// Error.
				case 'ERROR':
					$order->add_order_note( $response_body['message'] );
					break;
			endswitch;

		endif;

		return true;
	}

	

}