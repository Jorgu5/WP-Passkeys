<?php

/**
 * Singleton Trait for WP Passkeys.
 *
 * Provides Singleton functionality for WP Passkeys.
 *
 * @package WpPasskeys\Traits
 */

namespace WpPasskeys\Traits;

/**
 * Trait Singleton
 */
trait SingletonTrait
{
    /**
     * The instance of the class.
     *
     * @var self
     */
    private static ?self $instance = null;

    /**
     * Singleton constructor.
     *
     * Private to prevent instantiation from outside.
     */
    private function __construct()
    {
    }

    /**
     * Returns the Singleton instance of this class.
     *
     * @return self
     */
    public static function instance(): self
    {
        // Initialize the instance if it hasn't been already.
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * Prevents the instance from being cloned.
     *
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * Prevents the instance from being unserialized.
     *
     * @return void
     */
    private function __wakeup()
    {
    }
}
