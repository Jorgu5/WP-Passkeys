<?php

namespace WpPasskeys;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WpPasskeys\Exceptions\CredentialException;

class CredentialsApi
{

    /**
     * @throws CredentialException
     */
    public function setUserLogin(WP_REST_Request $request): WP_REST_Response
    {
        $userLogin = $request->get_param('name');
        if (empty($userLogin)) {
            throw new CredentialException('No user login provided.');
        }
        $sanitizedUserLogin = sanitize_text_field($userLogin);

        SessionHandler::instance()->set('user_login', $sanitizedUserLogin);

        $user = get_user_by('login', $sanitizedUserLogin);

        $response = [
            'isExistingUser' => (bool)$user
        ];

        return new WP_REST_Response($response, 200);
    }

    /**
     * @throws CredentialException
     */
    public function removeUserCredentials(WP_REST_Request $request): WP_REST_Response
    {
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
        global $wpdb;
        $tableResult = $wpdb->delete(
            'wp_pk_credential_sources',
            ['pk_credential_id' => $pkCredentialId],
            ['%s']
        );

        if ($tableResult === false) {
            update_user_meta($userId, 'pk_credential_id', $pkCredentialId);
            throw new CredentialException('Failed to remove credential source.');
        }

        return new WP_REST_Response(['success' => true], 200);
    }
}