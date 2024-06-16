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
        public readonly SessionHandlerInterface $sessionHandler,
        public readonly Utilities $utilities
    ) {
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function setUserCredentials(WP_REST_Request $request): WP_REST_Response
    {
        $userData = $request->get_params();

        $userData = array_intersect_key(
            $userData,
            array_flip(['user_email', 'user_login', 'display_name', 'user_pass'])
        );

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
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function removeUserCredentials(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $userId = get_current_user_id();
        // Retrieve pk_credential_id from the request
        $requestedPkCredentialId = $request->get_param('id');
        $pkCredentialIds         = get_user_meta($userId, 'pk_credential_id', true);

        if (empty($pkCredentialIds) || ! is_array($pkCredentialIds)) {
            return new WP_REST_Response([
                'code'    => 204,
                'message' => 'No credentials found for this user',
                'data'    => [],
            ]);
        }

        if (! in_array($requestedPkCredentialId, $pkCredentialIds, true)) {
            return new WP_REST_Response([
                'code'    => 204,
                'message' => 'Requested credential ID not found for this user',
                'data'    => [],
            ]);
        }

        // Remove the specific pk_credential_id from the array
        $updatedPkCredentialIds = array_filter(
            $pkCredentialIds,
            static function ($pkCredentialId) use ($requestedPkCredentialId) {
                return $pkCredentialId !== $requestedPkCredentialId;
            }
        );

        // Update the user's stored credentials
        update_user_meta($userId, 'pk_credential_id', $updatedPkCredentialIds);

        // Now, remove the specific credential from the custom table
        $deletedRows = $wpdb->delete(
            'wp_pk_credential_sources',
            ['pk_credential_id' => $requestedPkCredentialId],
            ['%s']
        );

        if ($deletedRows === false) {
            return new WP_REST_Response([
                'code'    => 500,
                'message' => 'Failed to remove credential source',
                'data'    => [],
            ]);
        }

        return new WP_REST_Response([
            'code'    => 200,
            'message' => 'Successfully removed credentials for user with ID: ' . $userId,
            'data'    => [],
        ]);
    }

    /**
     * Retrieves user credentials from the session.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function getUserCredentials(WP_REST_Request $request): WP_REST_Response
    {
        // Attempt to retrieve user data from the session.
        $userData = $this->sessionHandler->get('user_data');

        if (empty($userData)) {
            return new WP_REST_Response(
                [
                    'code'    => 204,
                    'message' => 'No user credentials found in the session.',
                    'data'    => [],
                ],
                204 // HTTP status code for No Content
            );
        }

        return new WP_REST_Response(
            [
                'code'    => 200,
                'message' => 'User credentials retrieved successfully.',
                'data'    => ['user_credentials' => $userData],
            ],
            200 // HTTP status code for OK
        );
    }
}
