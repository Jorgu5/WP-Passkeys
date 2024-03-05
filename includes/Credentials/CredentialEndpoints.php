<?php

namespace WpPasskeys\Credentials;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WpPasskeys\Exceptions\InvalidCredentialsException;
use WpPasskeys\Utilities;

class CredentialEndpoints implements CredentialEndpointsInterface
{
    public function __construct(
        public readonly SessionHandlerInterface $sessionHandler
    ) {
    }

    public function setUserCredentials(WP_REST_Request $request): WP_REST_Response
    {
        $userData = $request->get_params();

        $userData = array_intersect_key($userData, array_flip(['user_email', 'user_login', 'display_name']));

        $savedData = [];

        if (empty($userData)) {
            return new WP_REST_Response(
                [
                    'code'    => 204,
                    'message' => 'No user data found in the request.',
                    'data'    => [],
                ]
            );
        }

        foreach ($userData as $userKey => $userValue) {
            if ($userKey === 'user_email') {
                $sanitizedValue = sanitize_email($userValue);
            } else {
                $sanitizedValue = sanitize_text_field($userValue);
            }
            $savedData[$userKey] = $sanitizedValue;
        }

        $this->sessionHandler->set('user_data', $savedData);

        $savedKeys = implode(', ', array_keys($savedData));

        return new WP_REST_Response(
            [
                'code'    => 200,
                'message' => 'Successfully saved `' . $savedKeys . '` to session.',
                'data'    => [],
            ]
        );
    }

    /**
     * @throws InvalidCredentialsException
     */
    public function removeUserCredentials(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $userId         = get_current_user_id();
        $pkCredentialId = get_user_meta($userId, 'pk_credential_id', true);
        $response       = null;

        if ($pkCredentialId) {
            $response = new WP_Error('no_credentials', 'No credentials found for this user', ['status' => 404]);
        }

        if (! delete_user_meta($userId, 'pk_credential_id')) {
            throw new InvalidCredentialsException('Failed to remove pk_credential_id meta input');
        }

        $deletedRows = $wpdb->delete('wp_pk_credential_sources', ['pk_credential_id' => $pkCredentialId], ['%s']);

        /**
         * If the delete query fails, we need to reassign the credential id to the user a.k.a backup plan.
         */
        if ($deletedRows === false) {
            update_user_meta($userId, 'pk_credential_id', $pkCredentialId);
            throw new InvalidCredentialsException('Failed to remove credential source');
        }

        if (is_wp_error($response)) {
            Utilities::handleWpError($response);
        }

        return new WP_REST_Response(
            [
                'code'    => 200,
                'message' => 'Successfully removed credentials for user with ID: ' . $userId,
                'data'    => [],
            ]
        );
    }
}
