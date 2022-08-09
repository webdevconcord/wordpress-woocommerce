<?php

/**
 * Add ConcordPay link to WordPress menu.
 */
class ConcordpayMenu {

	/**
	 * Link for menu item.
	 *
	 * @var string
	 */
	public $slug = 'admin.php?page=wc-settings&tab=checkout&section=concordpay';

	/**
	 * Constructor method.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
	}

	/**
	 * Register menu item.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_menu_page(
			'ConcordPay',
			'ConcordPay',
			'manage_options',
			$this->slug,
			false,
			plugin_dir_url( __DIR__ ) . 'assets/img/concordpay-logo.svg',
			26
		);
	}
}
