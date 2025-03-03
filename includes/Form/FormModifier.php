<?php

namespace WpPasskeys\Form;

use voku\helper\HtmlDomParser as DomParser;

class FormModifier
{
    private readonly string $passkeysButtonClass;
    private readonly array $userDataSettings;
    private bool $removeDefaultRegisterSubmit = false;

    public function __construct()
    {
        $this->passkeysButtonClass = $this->isRegisterFlow() ? 'passkeys-button--register' : 'passkeys-button--login';
        $this->userDataSettings    = (array)get_option('wppk_require_userdata');
    }

    private function isRegisterFlow(): bool
    {
        return isset($_GET['action']) && $_GET['action'] === 'register';
    }

    public static function register(): void
    {
        $formHandler = new self();

        // Start output buffering in login_head
        add_action('login_head', static function () {
            // Check if output buffering is already active
            if (ob_get_level() === 0) {
                ob_start();
            } else {
                error_log('[WP Passkeys] Output buffering already active at level ' . ob_get_level());
            }
        }, 99);

        // Process the form in login_footer
        add_action('login_footer', [$formHandler, 'initializePasskeyForm']);

        // Add the passkeys button to the login form
        add_action('login_form', [$formHandler, 'passkeysButton'], 1);

        // Add the passkeys flow to the registration form
        add_action('register_form', [$formHandler, 'registerPasskeysFlow']);

        // Add custom login message
        add_filter('login_message', [$formHandler, 'addPasskeysLoginMessage']);
    }

    /**
     * Add a helpful message about passkeys to the login screen
     *
     * @param string $message The existing login message
     * @return string The modified login message
     */
    public function addPasskeysLoginMessage(string $message): string
    {
        if ($this->isRegisterFlow()) {
            $additionalMessage = '<p class="message passkeys-message">' .
                __('Create a passkey to sign in without a password. Passkeys are faster and more secure than passwords.', 'wp-passkeys') .
                '</p>';
        } else {
            $additionalMessage = '<p class="message passkeys-message">' .
                __('Sign in with a passkey for a faster, more secure login experience.', 'wp-passkeys') .
                '</p>';
        }

        return $message . $additionalMessage;
    }

    public function initializePasskeyForm(): void
    {
        $form = ob_get_clean();

        if (empty($form)) {
            error_log('[WP Passkeys] Output buffer is empty in initializePasskeyForm');
            return;
        }

        // Parse the HTML using the SimpleHTMLDomParser
        $html = DomParser::str_get_html($form);

        if (!$html || !$html->findOne('html')) {
            error_log('[WP Passkeys] Failed to parse HTML or HTML structure is invalid');
            // Output the original form if parsing fails
            echo $form;
            return;
        }

        if ($this->isRegisterFlow()) {
            $this->handleRegisterFlow($html);
        } else {
            $this->handleLoginFlow($html);
        }

        // $this->addLoadingIndicator($html);

        echo $html;
    }

    /**
     * Add a loading indicator for passkey operations
     *
     * @param object $html The HTML DOM object
     * @return void
     */
    private function addLoadingIndicator($html): void
    {
        // Create the loading indicator HTML
        $loadingHtml = '<div id="passkeys-loading" class="passkeys-loading" aria-live="polite" hidden>
            <div class="passkeys-loading-spinner"></div>
            <p class="passkeys-loading-text">' . __('Verifying your passkey...', 'wp-passkeys') . '</p>
        </div>';

        // Find the body element
        $body = $html->findOne('body');

        if ($body && !$body->isRemoved()) {
            // Get the HTML document
            $document = $html->getDocument();

            // Create a new div element for the loading indicator
            $loadingDiv = $document->createElement('div');
            $loadingDiv->setAttribute('id', 'passkeys-loading');
            $loadingDiv->setAttribute('class', 'passkeys-loading');
            $loadingDiv->setAttribute('aria-live', 'polite');
            $loadingDiv->setAttribute('hidden', 'hidden');

            // Create the spinner element
            $spinner = $document->createElement('div');
            $spinner->setAttribute('class', 'passkeys-loading-spinner');
            $loadingDiv->appendChild($spinner);

            // Create the text element
            $text = $document->createElement('p');
            $text->setAttribute('class', 'passkeys-loading-text');
            $text->textContent = __('Verifying your passkey...', 'wp-passkeys');
            $loadingDiv->appendChild($text);

            // Append the loading div to the body
            $body->getNode()->appendChild($loadingDiv);
        } else {
            error_log('[WP Passkeys] Could not find body element to add loading indicator');
        }
    }

    private function handleRegisterFlow($html): void
    {
        // Check if the HTML structure is as expected
        $registerForm = $html->findOne('#registerform');
        if (!$registerForm || $registerForm->isRemoved()) {
            error_log('[WP Passkeys] Could not find #registerform in the HTML structure');
            return;
        }

        $this->processRegisterFields($html);

        if ($this->removeDefaultRegisterSubmit) {
            $submitElement = $html->findOne('p.submit');
            if ($submitElement && !$submitElement->isRemoved()) {
                $submitElement->outertext = '';
            } else {
                error_log('[WP Passkeys] Could not find p.submit element to remove');
            }
        } else {
            $submitButton = $html->findOne('input[type=submit]');
            if ($submitButton && !$submitButton->isRemoved()) {
                $submitButton->value = __('Register with Password', 'wp-passkeys');
                $submitButton->setAttribute('aria-label', __('Register with password instead of passkey', 'wp-passkeys'));
            } else {
                error_log('[WP Passkeys] Could not find submit button to update');
            }
        }
    }

    private function processRegisterFields($html): void
    {
        $elements = $html->find('input[name=user_email], input[name=user_login]');

        if (empty($elements)) {
            error_log('[WP Passkeys] Could not find user_email or user_login inputs');
            return;
        }

        foreach ($elements as $element) {
            if (!$element || $element->isRemoved()) {
                continue;
            }

            if (!in_array($element->name, $this->userDataSettings, true)) {
                $parent = $element->parent();
                if ($parent && !$parent->isRemoved()) {
                    $parent->outertext = '';
                    $this->removeDefaultRegisterSubmit = true;
                }
            } else {
                // Add proper ARIA attributes for accessibility
                $element->setAttribute('aria-required', 'true');

                // Add descriptive labels
                if ($element->name === 'user_email') {
                    $element->setAttribute('aria-describedby', 'email-description');
                    $descriptionHtml = '<p id="email-description" class="description">' .
                        __('Your email will be used to identify your passkey.', 'wp-passkeys') .
                        '</p>';

                    $parent = $element->parent();
                    if ($parent && !$parent->isRemoved()) {
                        $parent->innertext .= $descriptionHtml;
                    }
                }
            }
        }
    }

    private function handleLoginFlow($html): void
    {
        // Check if the HTML structure is as expected
        $loginForm = $html->findOne('#loginform');
        if (!$loginForm || $loginForm->isRemoved()) {
            error_log('[WP Passkeys] Could not find #loginform in the HTML structure');
            return;
        }

        $this->removePasswordWrapper($html);
        $this->updateSubmitButtonValue($html, __('Log in with Password', 'wp-passkeys'));
        $this->updateAutocompleteAttributes($html);

        // Add a dedicated hidden input field for WebAuthn
        $this->addWebAuthnInput($html);

        // Add a visual separator between passkey and password options
        $this->addVisualSeparator($html);
    }

    /**
     * Add a dedicated hidden input field for WebAuthn
     *
     * @param object $html The HTML DOM object
     * @return void
     */
    private function addWebAuthnInput($html): void
    {
        $loginForm = $html->findOne('#loginform');
        if ($loginForm && !$loginForm->isRemoved()) {
            // Create a visible input field with autocomplete="webauthn" as the ONLY value
            // The SimpleWebAuthn library specifically looks for this
            $webauthnInput = '<div id="webauthn-container" style="position: relative;">
                <input type="text" 
                       name="webauthn-input" 
                       id="webauthn-input" 
                       autocomplete="webauthn" 
                       style="position: relative; width: 100%; height: 40px; opacity: 1; z-index: 1; margin-bottom: 10px; border: 1px solid #ddd; padding: 5px;" 
                       placeholder="' . esc_attr__('Click to use passkey', 'wp-passkeys') . '"
                       readonly>
            </div>';

            // Add it as the first element inside the form
            $loginForm->innertext = $webauthnInput . $loginForm->innertext;

            // Log for debugging
            error_log('[WP Passkeys] Added visible WebAuthn input field to login form');
        } else {
            error_log('[WP Passkeys] Could not find #loginform to add WebAuthn input');
        }
    }

    /**
     * Add a visual separator between passkey and password login options
     *
     * @param object $html The HTML DOM object
     * @return void
     */
    private function addVisualSeparator($html): void
    {
        $submitButton = $html->findOne('p.submit');
        if ($submitButton && !$submitButton->isRemoved()) {
            $separator = '<div class="passkeys-separator">
                <span class="passkeys-separator-text">' . __('or', 'wp-passkeys') . '</span>
            </div>';
            $submitButton->outertext = $separator . $submitButton->outertext;
        } else {
            error_log('[WP Passkeys] Could not find p.submit element to add separator');
        }
    }

    private function removePasswordWrapper($html): void
    {
        $userPassWrap = $html->findOne('.user-pass-wrap');
        if ($userPassWrap && !$userPassWrap->isRemoved()) {
            $userPassWrap->setAttribute('hidden', 'hidden');
            $userPassWrap->setAttribute('aria-hidden', 'true');
        } else {
            error_log('[WP Passkeys] Could not find .user-pass-wrap element');
        }
    }

    private function updateSubmitButtonValue($html, string $value): void
    {
        $inputs = $html->find('input[type=submit]');

        if (empty($inputs)) {
            error_log('[WP Passkeys] Could not find any submit buttons to update');
            return;
        }

        foreach ($inputs as $input) {
            if ($input && !$input->isRemoved()) {
                $input->value = $value;
                $input->setAttribute('aria-label', __('Log in with password instead of passkey', 'wp-passkeys'));
            }
        }
    }

    private function updateAutocompleteAttributes($html): void
    {
        // We no longer need to modify existing inputs for WebAuthn
        // since we're adding a dedicated input with autocomplete="webauthn"

        // Keep this for backward compatibility, but log that we're using a different approach
        error_log('[WP Passkeys] Using dedicated WebAuthn input field instead of modifying existing inputs');

        // Ensure username field has proper autocomplete
        $inputs = $html->find('input[name=log]');

        if (empty($inputs)) {
            error_log('[WP Passkeys] Could not find username input field');
            return;
        }

        foreach ($inputs as $input) {
            if ($input && !$input->isRemoved()) {
                if (!$input->hasAttribute('autocomplete') || $input->getAttribute('autocomplete') === '') {
                    $input->setAttribute('autocomplete', 'username');
                }
            }
        }
    }

    public function registerPasskeysFlow(): void
    {
        if (in_array('display_name', $this->userDataSettings, true)) {
            echo $this->passkeysDisplayNameInput();
        }

        // Add a visible input field with autocomplete="webauthn" as the ONLY value
        echo '<div id="webauthn-container" style="position: relative;">
            <input type="text" 
                   name="webauthn-input" 
                   id="webauthn-input" 
                   autocomplete="webauthn" 
                   style="position: relative; width: 100%; height: 40px; opacity: 1; z-index: 1; margin-bottom: 10px; border: 1px solid #ddd; padding: 5px;" 
                   placeholder="' . esc_attr__('Click to create passkey', 'wp-passkeys') . '"
                   readonly>
        </div>';
        error_log('[WP Passkeys] Added visible WebAuthn input field to registration form');

        $this->passkeysButton();
    }

    private function passkeysDisplayNameInput(): string
    {
        return '<p>
            <label for="display_name">' . __('Display name', 'wp-passkeys') . '</label>
            <input type="text" id="display_name" name="display_name" required="required" autocomplete="name" aria-required="true">
            <span class="description" id="display-name-description">' . __('This name will be displayed on your account.', 'wp-passkeys') . '</span>
            </p>
        ';
    }

    public function passkeysButton(): void
    {
        $isRegister = $this->isRegisterFlow();
        $submitButtonPrefix = $isRegister ? __('Register', 'wp-passkeys') : __('Log in', 'wp-passkeys');
        $ariaLabel = $isRegister
            ? __('Register with passkey for passwordless login', 'wp-passkeys')
            : __('Log in with passkey for passwordless login', 'wp-passkeys');

        echo "
        <button 
            type='button'
            class='button button-primary passkeys-button " . $this->passkeysButtonClass . "'
            aria-label='" . esc_attr($ariaLabel) . "'
            id='passkeys-button'
        >
            <span class='passkeys-button-icon'></span>
            <span class='passkeys-button-text'>" .
            esc_html($submitButtonPrefix . ' ' . __('with passkey', 'wp-passkeys')) .
            "</span>
        </button>
        <div id='passkeys-status' class='passkeys-status' aria-live='polite'></div>
        ";
    }
}
