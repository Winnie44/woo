<?php
/**
 * PayPo connect class.
 *
 * @package PayPo
 */

namespace PayPo;

class PayPo_Connect {

	/**
	 * API Url
	 *
	 * @var string $api_url url for requests.
	 */
	protected $api_url;

	/**
	 * Error codes
	 *
	 * @var array $error_codes code => message.
	 */
	protected $error_codes;



	/**
	 * Init PayPo connect class
	 *
	 * @param string $sandbox If is in sandbox mode for testing, accepted: yes, no.
	 */
	public function __construct( $sandbox ) {
		$this->api_url = $this->get_api_url( $sandbox );
		$this->error_codes = array(
			'400' => __( 'Incorrect data. Please contact the store administrator', 'woocommerce-gateway-paypo' ),
			'401' => __( 'Authentication error. Please contact the store administrator', 'woocommerce-gateway-paypo' ),
			'403' => __( 'Resource not authorized. Please contact the store administrator', 'woocommerce-gateway-paypo' ),
			'404' => __( 'URL or resource not found. Please contact the store administrator', 'woocommerce-gateway-paypo' ),
			'409' => __( 'Asset identification conflict. Please contact the store administrator', 'woocommerce-gateway-paypo' ),
			'503' => __( 'Service temporarily unavailable. Please try again later', 'woocommerce-gateway-paypo' ),
		);
	}



	/**
	 * Get PayPo URL urls
	 *
	 * @param  string $sandbox Accepted: yes, no.
	 * @return string $url PayPo api url.
	 */
	private function get_api_url( $sandbox ) {
		$url = 'https://api.paypo.pl/v3';
		if ( 'yes' === $sandbox ) {
			$url = 'https://api.sandbox.paypo.pl/v3';
		}
		return $url;
	}



	/**
	 * Convert zł to gr
	 *
	 * @param string $amount Order/refund amount.
	 * @return integer $amount
	 */
	protected function amount_conversion( $amount ) {
		return intval( str_replace( ',', '.', $amount ) * 100 );
	}



	/**
	 * Http post using wp_remote_post
	 *
	 * @param string $method PyPo method from url.
	 * @param array  $args Post body.
	 * @return array|void
	 */
	public function remote_post( $method, $args ) {
		$response = wp_remote_post( $this->api_url . $method, $args );
		if ( is_wp_error( $response ) ) {
			wc_add_notice( $response->get_error_message(), 'error' );
		} else {
			return (array) $this->response_handler( $response );
		}
	}



	/**
	 * Http request using wp_remote_request
	 *
	 * @param string $method PyPo method from url.
	 * @param array  $args Post body.
	 * @return array|void
	 */
	public function remote_request( $method, $args ) {
		$response = wp_remote_request( $this->api_url . $method, $args );
		if ( is_wp_error( $response ) ) {
			wc_add_notice( $response->get_error_message(), 'error' );
		} else {
			return (array) $this->response_handler( $response );
		}
	}



	/**
	 * Response handler
	 *
	 * @param array $response Remote post response.
	 * @return array|void
	 */
	public function response_handler( $response ) {
		$code = wp_remote_retrieve_response_code( $response );
		switch ( $code ) :
			case '200' || '201':
				return json_decode( wp_remote_retrieve_body( $response ), true );
			case array_key_exists( $code, $this->error_codes ):
				wc_add_notice( sprintf("<strong>{%s}</strong> {%s}", $response['response']['message'], $this->error_codes['$code'] ), 'error' );
				error_log( print_r( $response, true ) );
				break;
			default:
				error_log( print_r( $response, true ) );
		endswitch;
	}



	/**
	 * Get PayPo token for transaction post
	 *
	 * @param string $merchant_id Merchant ID from gateway settings.
	 * @param string $merchant_secret Merchant secret from gateway settings.
	 * @return boolean|array $token
	 */
	protected function get_access_token( $merchant_id, $merchant_secret ) {

		$args = array(
			'headers'     => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'data_format' => 'body',
			'body'        => 'grant_type=client_credentials&client_id=' . $merchant_id . '&client_secret=' . $merchant_secret,
		);

		// Get token value.
		$token = $this->remote_post( '/oauth/tokens', $args );

		// Check if token contains required data.
		if ( ! empty( $token ) && array_key_exists( 'access_token', $token ) ) {
			return $token;
		} else {
			return false;
		}
	}
	

}