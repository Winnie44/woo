<?php

namespace PayPo;

/**
 * Class Plugin Info
 * This class contains all plugin info like version, paths, urls, etc.
 *
 * @package PayPo
 * @since   2.0.0
 * */
class Plugin_Info {

	/**
	 * Plugin name
	 * Defined within plugin header
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Plugin basename
	 *
	 * @var string
	 */
	public $basename;

	/**
	 * Plugin description
	 * Defined within plugin header
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Plugin URL
	 * Defined within plugin header
	 *
	 * @var string
	 */
	public $url;

	/**
	 * Plugin version
	 * Defined within plugin header
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Plugin author
	 * Defined within plugin header
	 *
	 * @var string
	 */
	public $author;

	/**
	 * Plugin author URL
	 * Defined within plugin header
	 *
	 * @var string
	 */
	public $author_url;

	/**
	 * Plugin file with path
	 *
	 * @var string
	 */
	public $plugin_file;

	/**
	 * Plugin directory path
	 *
	 * @var string
	 */
	public $plugin_dir;

	/**
	 * Plugin directory url
	 *
	 * @var string
	 */
	public $plugin_url;

	/**
	 * Plugin text domain
	 * Defined within plugin header
	 *
	 * @var string
	 */
	public $text_domain;

	/**
	 * Plugin domain path
	 * Defined within plugin header
	 *
	 * @var string
	 */
	public $domain_path;

	/**
	 * Minimum WordPress version
	 * Defined within plugin header
	 *
	 * @var string
	 */
	public $required_wp;

	/**
	 * Minimum PHP version
	 * Defined within plugin header
	 *
	 * @var string
	 */
	public $required_php;

	/**
	 * Minimum WC version
	 * Defined within plugin header
	 *
	 * @var string
	 */
	public $required_wc;



	/**
	 * Init class
	 *
	 * @param  string $file - plugin file with path.
	 * @return void
	 */
	public function __construct( $file ) {

		$this->name          = $this->plugin_data( $file )['Name'];
		$this->basename      = plugin_basename( $file );
		$this->description   = $this->plugin_data( $file )['Description'];
		$this->url           = $this->plugin_data( $file )['PluginURI'];
		$this->version       = $this->plugin_data( $file )['Version'];
		$this->author        = $this->plugin_data( $file )['AuthorName'];
		$this->author_url    = $this->plugin_data( $file )['AuthorURI'];
		$this->plugin_file   = $file;
		$this->plugin_dir    = plugin_dir_path( $file );
		$this->plugin_url    = plugin_dir_url( $file );
		$this->text_domain   = $this->plugin_data( $file )['TextDomain'];
		$this->domain_path   = dirname( plugin_basename( $file ) ) . $this->plugin_data( $file )['DomainPath'];
		$this->required_wp   = $this->plugin_data( $file )['RequiresWP'];
		$this->required_php  = $this->plugin_data( $file )['RequiresPHP'];
		$this->required_wc   = $this->plugin_data( $file )['WC requires at least'];

	}



	/**
	 * Gets plugin data from get_plugin_data
	 *
	 * @param  string $file plugin file path.
	 * @return array  get_plugin_data.
	 */
	private function plugin_data( $file ) {

		if ( ! function_exists( 'get_plugin_data' ) ) :
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		endif;

		return get_plugin_data( $file );

	}

}