<?php
/**
 * PHPUnit bootstrap file for WpPassKeys.
 *
 * @package WpPassKeys
 */

// Define the test directory.
$wppasskeys_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $wppasskeys_tests_dir ) {
	$wppasskeys_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $wppasskeys_tests_dir . '/includes/functions.php' ) ) {
	echo esc_html( 'Could not find ' . $wppasskeys_tests_dir . '/includes/functions.php, have you run bin/install-wp-tests.sh ?' ) . PHP_EOL;
	exit( 1 );
}

// Include the tests functions.
require_once $wppasskeys_tests_dir . '/includes/functions.php';

// Load the WP_CLI library.
require_once 'phar:///usr/local/bin/wp-cli.phar/vendor/autoload.php';

// Prevent WP_CLI::error() from exiting and throwing an exception instead.
$wppasskeys_wp_cli_capture_exit = new \ReflectionProperty( 'WP_CLI', 'capture_exit' );
$wppasskeys_wp_cli_capture_exit->setValue( true );

// Load the PHPUnit Polyfills library.
$wppasskeys_phpunit_polyfills_lib = './vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
if ( ! file_exists( $wppasskeys_phpunit_polyfills_lib ) ) {
	echo esc_html( "Could not find $wppasskeys_phpunit_polyfills_lib, have you run `docker-compose up` in order to install Composer packages?" . PHP_EOL ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
	exit( 1 );
}
require_once $wppasskeys_phpunit_polyfills_lib;

/**
 * Manually load the plugin being tested.
 */
function wppasskeys_manually_load_plugin(): void {
	require dirname( __FILE__, 2 ) . '/wp-passkeys.php';
}
tests_add_filter( 'muplugins_loaded', 'wppasskeys_manually_load_plugin' );

// Boot the WP testing environment.
require $wppasskeys_tests_dir . '/includes/bootstrap.php';
