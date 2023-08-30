<?php
/**
 * Singleton Trait for WP Pass Keys.
 *
 * @package WpPassKeys\SingletonTrait
 */

namespace WpPassKeys\SingletonTrait;

/**
 * Trait SingletonTrait
 *
 * Provides Singleton functionality.
 */
trait SingletonTrait {


	/**
	 * The instance of the class.
	 *
	 * @var SingletonTrait
	 */
	private static $instance;

	/**
	 * SingletonTrait constructor.
	 *
	 * Private to prevent instantiation.
	 */
	private function __construct() {
	}

	/**
	 * Returns the Singleton instance of this class.
	 *
	 * @return static
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Prevents the instance from being cloned.
	 *
	 * @return void
	 */
	private function __clone() {
	}

	/**
	 * Prevents the instance from being unserialized.
	 *
	 * @return void
	 */
	private function __wakeup() {
	}
}
