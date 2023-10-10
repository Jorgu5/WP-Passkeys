<?php

namespace WpPasskeys\Credentials;

use WP_REST_Request;
use WP_REST_Response;

interface CredentialsEndpointsInterface {
    public function setUserCredentials(WP_REST_Request $request): WP_REST_Response;
    public function removeUserCredentials(WP_REST_Request $request): WP_REST_Response;

}