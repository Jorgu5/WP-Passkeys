<?php
/**
 * General plugin functionality.
 *
 * @package WpPasskeys
 * @version 1.0.0
 */

namespace WpPasskeys;

/**
 * Main plugin class.
 */
class WpPasskeys {
	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public const VERSION = '1.0.0';

	use SingletonTrait;

	/**
	 * Webauthn_Plugin Constructor.
	 */
	public function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 */
	public function includes(): void {
		require_once __DIR__ . '/class-publickeyrepository.php';
		require_once __DIR__ . '/class-algorithmmanager.php';
		require_once __DIR__ . '/class-attestationhandler.php';
		require_once __DIR__ . '/class-credentialloader.php';
		require_once __DIR__ . '/class-responsevalidator.php';
		require_once __DIR__ . '/class-db-passkeys.php';
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks(): void {
		add_action( 'init', array( $this, 'init' ), 0 );
	}

	/**
	 * Activation hook.
	 */
	public static function activate(): void {
		// Code that runs on plugin activation, such as setting up database tables, etc.
	}

	/**
	 * Run the plugin.
	 */
	public function run(): void {
		$this->init();
	}

	/**
	 * Init Webauthn_Plugin when WordPress initializes.
	 */
	public function init(): void {
		// Initialization code here.
	}
}
