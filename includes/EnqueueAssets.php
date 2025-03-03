<?php

namespace WpPasskeys;

class EnqueueAssets
{
    /**
     * Vite manifest data
     *
     * @var array|null
     */
    private ?array $manifest = null;

    /**
     * List of script handles that should be loaded as ES modules
     *
     * @var array
     */
    private array $moduleScripts = [];

    /**
     * Initializes the function.
     *
     * @return void
     */
    public static function register(): void
    {
        $pluginAssets = new self();
        add_action('login_enqueue_scripts', [$pluginAssets, 'enqueueLoginAssets']);
        add_action('admin_enqueue_scripts', [$pluginAssets, 'enqueueAdminAssets']);

        // Add filter to modify script tags for ES modules
        add_filter('script_loader_tag', [$pluginAssets, 'addModuleTypeAttribute'], 10, 3);
    }

    /**
     * Constructor - loads the manifest file
     */
    public function __construct()
    {
        $this->loadManifest();
        $this->moduleScripts = [];
    }

    /**
     * Loads the Vite manifest file
     *
     * @return void
     */
    private function loadManifest(): void
    {
        $manifestPath = plugin_dir_path(__DIR__) . 'dist/manifest.json';

        if (file_exists($manifestPath)) {
            $this->manifest = json_decode(file_get_contents($manifestPath), true);
        }
    }

    /**
     * Adds type="module" attribute to script tags for ES modules
     *
     * @param string $tag    The script tag.
     * @param string $handle The script handle.
     * @param string $src    The script source.
     * @return string Modified script tag
     */
    public function addModuleTypeAttribute($tag, $handle, $src): string
    {
        // Check if this script should be loaded as a module
        if (in_array($handle, $this->moduleScripts, true)) {
            // Replace the script tag with one that has type="module"
            $tag = '<script type="module" src="' . esc_url($src) . '" id="' . $handle . '-js"></script>';
        }

        return $tag;
    }

    /**
     * Registers a script as an ES module
     *
     * @param string $handle Script handle
     * @return void
     */
    private function registerAsModule(string $handle): void
    {
        if (!in_array($handle, $this->moduleScripts, true)) {
            $this->moduleScripts[] = $handle;
        }
    }

    /**
     * Enqueues all login page assets (scripts and styles)
     *
     * @return void
     */
    public function enqueueLoginAssets(): void
    {
        // Always load the form script and styles on login page
        $this->enqueueLoginStyles();
        $this->enqueueFormScript();

        // Load registration script only when needed
        if ($this->shouldLoadRegistrationScript()) {
            $this->enqueueRegistrationScript();
        }

        // Load authentication script only when needed
        if ($this->shouldLoadAuthenticationScript()) {
            $this->enqueueAuthenticationScript();
        }
    }

    /**
     * Enqueues all admin page assets (scripts and styles)
     *
     * @param string $hook The current admin page
     * @return void
     */
    public function enqueueAdminAssets($hook): void
    {
        // Load profile scripts only on profile pages
        if ($this->isProfilePage($hook)) {
            $this->enqueueSettingStyles();
            $this->enqueueUserProfileScript();
        }
    }

    /**
     * Check if we should load the registration script
     *
     * @return bool
     */
    private function shouldLoadRegistrationScript(): bool
    {
        return isset($_GET['email'], $_GET['pkEmailToken']) &&
            in_array('user_email', get_option('wppk_require_userdata', []), true);
    }

    /**
     * Check if we should load the authentication script
     *
     * @return bool
     */
    private function shouldLoadAuthenticationScript(): bool
    {
        return !isset($_GET['action']) || $_GET['action'] !== 'register';
    }

    /**
     * Check if current page is a profile page
     *
     * @param string $hook The current admin page
     * @return bool
     */
    private function isProfilePage($hook): bool
    {
        return $hook === 'profile.php' || $hook === 'user-edit.php';
    }

    /**
     * Enqueues the form script
     *
     * @return void
     */
    private function enqueueFormScript(): void
    {
        wp_enqueue_script(
            'passkeys-form',
            $this->getAssetUrl('js/form/index.js'),
            ['wp-api-fetch'], // Add WordPress core dependency
            $this->getAssetVersion('js/form/index.js'),
            true // Load in footer for better performance
        );

        // Register this script as an ES module
        $this->registerAsModule('passkeys-form');

        wp_localize_script(
            'passkeys-form',
            'pkUser',
            [
                'restEndpoints' => [
                    'main' => rest_url('wp-passkeys'),
                ],
                'nonce' => wp_create_nonce('wp_rest'),
            ]
        );
    }

    /**
     * Enqueues the registration script
     *
     * @return void
     */
    private function enqueueRegistrationScript(): void
    {
        wp_enqueue_script(
            'passkeys-register',
            $this->getAssetUrl('js/registration/index.js'),
            ['passkeys-form'], // Add dependency on form script
            $this->getAssetVersion('js/registration/index.js'),
            true
        );

        // Register this script as an ES module
        $this->registerAsModule('passkeys-register');
    }

    /**
     * Enqueues the authentication script
     *
     * @return void
     */
    private function enqueueAuthenticationScript(): void
    {
        wp_enqueue_script(
            'passkeys-auth',
            $this->getAssetUrl('js/authentication/index.js'),
            ['passkeys-form'], // Add dependency on form script
            $this->getAssetVersion('js/authentication/index.js'),
            true
        );

        // Register this script as an ES module
        $this->registerAsModule('passkeys-auth');
    }

    /**
     * Retrieves the URL to an asset
     *
     * @param string $relativePath The relative path to the asset
     * @return string The URL to the asset
     */
    private function getAssetUrl(string $relativePath): string
    {
        // If we have a manifest entry for this file, use it
        if ($this->manifest && isset($this->manifest[$relativePath])) {
            return plugin_dir_url(__DIR__) . 'dist/' . $this->manifest[$relativePath]['file'];
        }

        // Fallback to the original path
        return plugin_dir_url(__DIR__) . 'dist/' . $relativePath;
    }

    /**
     * Get the version for an asset (from manifest or file modification time)
     *
     * @param string $relativePath The relative path to the asset
     * @return string The version string
     */
    private function getAssetVersion(string $relativePath): string
    {
        // If we have a manifest entry for this file, use a hash from the filename
        if ($this->manifest && isset($this->manifest[$relativePath])) {
            // Extract hash from the filename if present
            $file = $this->manifest[$relativePath]['file'];
            if (preg_match('/-([a-zA-Z0-9]+)\.(js|css)$/', $file, $matches)) {
                return $matches[1];
            }
        }

        $filePath = plugin_dir_path(__DIR__) . 'dist/' . $relativePath;

        // Use file modification time if available, otherwise use plugin version
        if (file_exists($filePath)) {
            return (string) filemtime($filePath);
        }

        return defined('WP_PASSKEYS_VERSION') ? WP_PASSKEYS_VERSION : '1.0.0';
    }

    /**
     * Enqueues the user profile script
     *
     * @return void
     */
    public function enqueueUserProfileScript(): void
    {
        if (get_current_screen()->id !== 'profile') {
            return;
        }

        wp_enqueue_script(
            'passkeys-user-profile-scripts',
            $this->getAssetUrl('js/admin/index.js'),
            ['wp-api-fetch'], // Add WordPress core dependency
            $this->getAssetVersion('js/admin/index.js'),
            true
        );

        // Register this script as an ES module
        $this->registerAsModule('passkeys-user-profile-scripts');

        wp_localize_script(
            'passkeys-user-profile-scripts',
            'pkUser',
            [
                'nonce' => wp_create_nonce('wp_rest'),
                'restEndpoints' => [
                    'main' => rest_url('wp-passkeys'),
                    'user' => rest_url('wp-passkeys/creds/user'),
                ],
            ]
        );
    }

    /**
     * Enqueues the login styles
     *
     * @return void
     */
    public function enqueueLoginStyles(): void
    {
        wp_enqueue_style(
            'passkeys-main-styles',
            $this->getAssetUrl('css/default-login.css'),
            [],
            $this->getAssetVersion('css/default-login.css'),
            'all'
        );
    }

    /**
     * Enqueues the settings styles
     *
     * @return void
     */
    public function enqueueSettingStyles(): void
    {
        wp_enqueue_style(
            'passkeys-plugin-settings-styles',
            $this->getAssetUrl('css/plugin-settings.css'),
            [],
            $this->getAssetVersion('css/plugin-settings.css'),
            'all'
        );
    }
}
