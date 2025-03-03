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
        add_action('login_head', static function () {
            ob_start();
        }, 99);

        add_action('login_footer', [$formHandler, 'initializePasskeyForm']);
        add_action('login_form', [$formHandler, 'passkeysButton'], 1);
        add_action('register_form', [$formHandler, 'registerPasskeysFlow']);
    }

    public function initializePasskeyForm(): void
    {
        $form = ob_get_clean();
        $html = DomParser::str_get_html($form); // Load the HTML

        if (! $html) {
            echo $form; // In case of an error, fallback to the original form

            return;
        }

        if ($this->isRegisterFlow()) {
            $this->handleRegisterFlow($html);
        } else {
            $this->handleLoginFlow($html);
        }

        echo $html; // Output the modified HTML
    }

    private function handleRegisterFlow($html): void
    {
        $this->processRegisterFields($html);
        if ($this->removeDefaultRegisterSubmit) {
            $html->findOne('p.submit')->outertext = '';
        } else {
            $html->findOne('input[type=submit]')->value = 'Register with Password';
        }
    }

    private function processRegisterFields($html): void
    {
        foreach ($html->find('input[name=user_email], input[name=user_login]') as $element) {
            if (! in_array($element->name, $this->userDataSettings, true)) {
                $element->parent()->outertext      = '';
                $this->removeDefaultRegisterSubmit = true;
            }
        }
    }

    private function handleLoginFlow($html): void
    {
        $this->removePasswordWrapper($html);
        $this->updateSubmitButtonValue($html, 'Log in with Password');
        $this->updateAutocompleteAttributes($html);
    }

    private function removePasswordWrapper($html): void
    {
        $userPassWrap = $html->findOne('.user-pass-wrap');
        if ($userPassWrap) {
            $userPassWrap->setAttribute('hidden', 'hidden');
        }
    }

    private function updateSubmitButtonValue($html, string $value): void
    {
        foreach ($html->find('input[type=submit]') as $input) {
            $input->value = $value;
        }
    }

    private function updateAutocompleteAttributes($html): void
    {
        foreach ($html->find('input') as $input) {
            if ($input->autocomplete === 'current-password' || $input->autocomplete === 'username') {
                $input->autocomplete .= ' webauthn';
            }
        }
    }

    public function registerPasskeysFlow(): void
    {
        if (in_array('display_name', $this->userDataSettings, true)) {
            echo $this->passkeysDisplayNameInput();
        }
        $this->passkeysButton();
    }

    private function passkeysDisplayNameInput(): string
    {
        return '<p>
            <label for="display_name">' . __('Display name', 'wp-passkeys') . '</label>
            <input type="text" id="display_name" name="display_name" required="required" autocomplete="name">
            </p>
        ';
    }

    public function passkeysButton(): void
    {
        $submitButtonPrefix = $this->isRegisterFlow() ? 'Register' : 'Log in';
        echo "
        <button 
            type='button'
            class='button button-primary passkeys-button " . $this->passkeysButtonClass . "'>" .
             __($submitButtonPrefix . ' with passkeys', 'wp-passkeys') .
             "</button>
        ";
    }
}
