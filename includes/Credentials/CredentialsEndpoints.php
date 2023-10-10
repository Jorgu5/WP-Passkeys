<?php

namespace WpPasskeys\Credentials;

use WP_REST_Request;
use WP_REST_Response;
use WpPasskeys\Exceptions\CredentialException;

class CredentialsEndpoints implements CredentialsEndpointsInterface
{
    public function setUserCredentials(WP_REST_Request $request): WP_REST_Response
    {
        $userData = $request->get_params();

        $userData = array_intersect_key($userData, array_flip(['user_email', 'user_login', 'display_name']));

        $savedData = [];

        if (empty($userData)) {
            return new WP_REST_Response('Registering with usernameless method', 200);
        }

        // foreach item in $userData, sanitize and set data in SessionHandler instance
        foreach ($userData as $userKey => $userValue) {
            if ($userKey === 'user_email') {
                $sanitizedValue = sanitize_email($userValue);
            } else {
                $sanitizedValue = sanitize_text_field($userValue);
            }
            $savedData[$userKey] = $sanitizedValue;
        }

        SessionHandler::set('user_data', $savedData);

        $savedKeys = implode(', ', array_keys($savedData));

        return new WP_REST_Response("Successfully saved {$savedKeys} in session", 200);
    }

    /**
     * @throws CredentialException
     */
    public function removeUserCredentials(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $userId = get_current_user_id();

        $pkCredentialId = get_user_meta($userId, 'pk_credential_id', true);

        if (!$pkCredentialId) {
            throw new CredentialException('No pk_credential_id found for user.');
        }

        // Remove the pk_credential_id meta input
        $metaResult = delete_user_meta($userId, 'pk_credential_id');

        if (!$metaResult) {
            throw new CredentialException('Failed to remove pk_credential_id meta input.');
        }

        // Remove the PublicKeyCredentialSource from custom table
        if (
            $wpdb->delete(
                'wp_pk_credential_sources',
                ['pk_credential_id' => $pkCredentialId],
                ['%s']
            ) === false
        ) {
            update_user_meta($userId, 'pk_credential_id', $pkCredentialId);
            throw new CredentialException('Failed to remove credential source.');
        }

        return new WP_REST_Response(['success' => true], 200);
    }
}
