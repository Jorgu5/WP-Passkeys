<?php

namespace WpPasskeys\Admin;

use JsonException;
use Webauthn\Exception\InvalidDataException;
use WpPasskeys\Credentials\CredentialHelper;
use WpPasskeys\Exceptions\InvalidUserDataException;

class UserPasskeysCardRender
{
    public function __construct(
        private readonly CredentialHelper $credentialHelper,
    ) {
    }

    public function render(array|string $pkCredentialIds): string
    {
        $buttonHtml = '';

        if (empty($pkCredentialIds)) {
            $passkeyCardsHtml = '<p>' . __(
                'There are no passkeys assigned to this account yet.',
                'wp-passkeys'
            ) . '</p>';
        } else {
            $passkeyCardsHtml = $this->renderPasskeyCard($pkCredentialIds);
            $buttonHtml       = AdminUtilities::renderButton(
                __('Add new passkey', 'wp-passkeys'),
                'add_new_passkey',
                'button-primary passkeys__button--add'
            );
        }

        $html = '<div class="passkeys-cards-container">';
        $html .= $passkeyCardsHtml;
        $html .= '</div>';
        $html .= '<p> ' . __(
            'If you remove passkeys, you will need to use your password to log in.',
            'wp-passkeys'
        ) . '</p>';
        $html .= $buttonHtml;

        return $html;
    }


    public function renderPasskeyCard(array|string $pkCredentialIds): string
    {
        $passkeyData = $this->preparePasskeyData($pkCredentialIds);

        ob_start();
        foreach ($passkeyData as $data) {
            $deviceIconPath = AdminUtilities::isDeviceMobile(
                $data['lastUsedOS']
            ) ? WP_PASSKEYS_PLUGIN_PATH . 'assets/img/mobile-phone.svg' :
                WP_PASSKEYS_PLUGIN_PATH . 'assets/img/desktop.svg';

            $lockIconPath = WP_PASSKEYS_PLUGIN_PATH . 'assets/img/lock.svg';

            $renderRemoveButton = AdminUtilities::renderButton(
                __(
                    'Remove passkey',
                    'wp-passkeys'
                ),
                $data['credentialId'],
                'passkey-card__button passkey-card__button--remove'
            );

            echo "<div class='passkey-card'>
                <div class='passkey-card__wrapper'>
                <div class='passkey-card__header'>
                    <div class='passkey-card__icon'>
                        <svg width='28' height='28' fill='none' viewBox='0 0 53 51' xmlns='http://www.w3.org/2000/svg'>
                          <path d='M20.25 24c6.627 0 12-5.373 12-12s-5.373-12-12-12-12 5.373-12 12 5.373 12 12 12Zm32 0a9.333 9.333 0 1 0-13.333 8.4v14.267l4 4L49.583 44l-4-4 4-4-3.306-3.307A9.334 9.334 0 0 0 52.25 24Zm-9.333 0a2.667 2.667 0 1 1 0-5.333 2.667 2.667 0 0 1 0 5.333Zm-12.16 5.387A16 16 0 0 0 24.25 28h-8a16 16 0 0 0-16 16v5.333h34.667V34.64a13.76 13.76 0 0 1-4.16-5.253Z' fill='#1d2327'/>
                        </svg>
                    </div>
                    <div class='passkey-card__heading'>
                    <p>" . __(
                'Saved with ' . $data['registrationOS'] . ' to my password manager on ' . $data['registrationDate']
            ) . "</p>
                </div>
                </div>
                <div class='passkey-card__details'>
                    <h4>Last Used <span class='passkey-card__details_lock-icon'>" . file_get_contents($lockIconPath) . "</span></h4>
                    <ul>
                        <li class='passkey-card__detail'>
                            <span class='passkey-card__details_device-icon'>" . file_get_contents($deviceIconPath) . "</span>
                            <span class='passkey-card__details_device-info'>
                                <span class='passkey-card__details_device-name'>" . $data['lastUsedOS'] . "</span>
                                <span class='passkey-card__details_device-date'>" . $data['lastUsedAt'] . "</span>
                            </span>
                        </li>
                    </ul>
                </div>
                <div class='passkey-card__footer'>
                    <div class='passkey-card__footer__remove'> " . $renderRemoveButton . "</div>
                </div>
              </div>
              <div class='passkey-card__meta'>
                <span><strong>" . __('Type: ', 'wp-passkeys') . "</strong>" . $data['type'] . "</span>
                <span><strong>" . __('Transport: ', 'wp-passkeys') . "</strong>" . $data['transports'] . "</span>
              </div></div>";
        }

        return ob_get_clean();
    }

    /**
     * @throws InvalidDataException
     * @throws JsonException
     * @throws InvalidUserDataException
     */
    private function preparePasskeyData(array|string $pkCredentialIds): array
    {
        $pkCredentialIds = is_string($pkCredentialIds) ? [$pkCredentialIds] : $pkCredentialIds;

        // Assuming we fetch descriptors in a way that they correspond to the pkCredentialIds
        $credentialDescriptors = $this->credentialHelper->findAllForUserEntity(wp_get_current_user()->user_login);

        $passkeyData = [];
        foreach ($pkCredentialIds as $index => $credentialId) {
            $registrationDate = $this->credentialHelper->getDataByCredentialId($credentialId, 'created_at') ?: 'N/A';
            $registrationOS   = $this->credentialHelper->getDataByCredentialId($credentialId, 'created_os') ?: 'N/A';
            $lastUsedAt       = $this->credentialHelper->getDataByCredentialId($credentialId, 'last_used_at') ?: 'N/A';
            $lastUsedOS       = $this->credentialHelper->getDataByCredentialId($credentialId, 'last_used_os') ?: 'N/A';

            // Directly use descriptors assuming a corresponding order or direct relationship
            $descriptor = $credentialDescriptors[$index] ?? null; // Adjust as necessary
            $transports = $descriptor ? implode(
                ', ',
                $descriptor->getTransports()
            ) : ''; // Assuming getTransports returns an array
            $type       = $descriptor ? $descriptor->getType() : '';

            $passkeyData[] = [
                'credentialId'     => $credentialId,
                'registrationDate' => $registrationDate,
                'registrationOS'   => $registrationOS,
                'lastUsedAt'       => $lastUsedAt,
                'lastUsedOS'       => $lastUsedOS,
                'transports'       => $transports,
                'type'             => $type,
            ];
        }

        return $passkeyData;
    }
}
