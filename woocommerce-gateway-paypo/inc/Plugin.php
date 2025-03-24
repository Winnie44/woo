<?php

namespace PayPo;

/**
 * Class Plugin. Register PayPo Payment gateway
 *
 * @package PayPo
 * */
class Plugin {

	/**
	 * Plugin info
	 * Contains names, desription, version, paths, etc.
	 *
	 * @var object $plugin_info
	 */
	private $plugin_info;



	/**
	 * Plugin init
	 *
	 * @param  object $plugin_info .
	 * @return void
	 */
	public function __construct( $plugin_info ) {

		// Plugin info object.
		$this->plugin_info = $plugin_info;

		// Register WooCommerce payment gateway.
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_paypo_payment' ) );

		// Load plugin textdomain for translations.
		$this->text_domain();

		// Frontend assets.
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );

        // Plugins list action link to settings.
        add_filter( 'plugin_action_links_' . $plugin_info->basename , array( $this, 'gateway_settings_link' ) );

	}



	/**
	 * Register PayPo payment method
	 *
	 * @param  array $methods array with WC payment methods.
	 * @return array $methods array with WC payment methods.
	 */
	public function register_paypo_payment( $methods ) {
		$paypo_class = new PayPo_Gateway( $this->plugin_info );
		$methods[] = $paypo_class;

		return $methods;
	}



	/**
	 * Load Plugin text Domain
	 */
	public function text_domain() {
		load_plugin_textdomain( $this->plugin_info->text_domain, false, $this->plugin_info->domain_path );
	}



    /**
     * Enqueue frontend assets
     */
    public function frontend_assets() {
        wp_enqueue_style( 'paypo', $this->plugin_info->plugin_url . 'assets/css/paypo.css', array(), $this->plugin_info->version );
    }



	/**
	 * Enqueue admin assets
	 */
    public function admin_assets() {
	    if (  'woocommerce_page_wc-settings' === get_current_screen()->base ) {
		    wp_enqueue_script( 'paypo', $this->plugin_info->plugin_url . 'assets/js/paypo.js', [], '1.0.0', TRUE );
	    }
    }



    /**
     * Add Settings link to gateway settings on plugins list
     *
     * @param  array $actions Plugin actions.
     * @return array $actions Plugin actions.
     */
    public function gateway_settings_link( $actions ) {
        $settings = array(
            '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paypo' ) . '">' . __( 'Settings', 'woocommerce-gateway-paypo' ) . '</a>',
        );
        $actions = array_merge( $actions, $settings );
        return $actions;
    }
}