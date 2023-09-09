<?php
/**
 * General plugin functionality.
 *
 * @package WpPasskeys
 * @since 0.1.0
 */

namespace WpPasskeys;

use WpPasskeys\Traits\Singleton;

/**
 * Main plugin class.
 */
class Passkeys_Plugin {
	use Singleton;

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks(): void {
		Enqueue_Assets::instance()->init();
		Authentication_Handler::instance()->init();
		Registration_Handler::instance()->init();
	}

	/**
	 * Activation hook.
	 */
	public static function activate(): void {
		Utilities::set_plugin_version();
	}

	/**
	 * Run the plugin.
	 */
	public function run(): void {
		$this->init_hooks();
		self::activate();
	}

	/**
	 * Create tables on plugin activattion.
	 */

	public static function create_tables(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'webauthn_credential_options';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
  id mediumint(9) NOT NULL AUTO_INCREMENT,
  user_cookie varchar(55) DEFAULT '' NOT NULL,
  timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
  credential_options LONGBLOB NOT NULL,
  PRIMARY KEY  (id)
) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
