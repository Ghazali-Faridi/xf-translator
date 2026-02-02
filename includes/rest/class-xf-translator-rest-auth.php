<?php
/**
 * REST API Authentication for External Workers
 *
 * @package Xf_Translator
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API authentication via Bearer token.
 */
class Xf_Translator_Rest_Auth {

    /**
     * Verify request has valid worker API token.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if valid, WP_Error otherwise.
     */
    public static function authenticate($request) {
        $settings = new Settings();
        $token = $settings->get('worker_api_token', '');

        if (empty($token)) {
            return new WP_Error(
                'no_token_configured',
                __('Worker API token is not configured. Add it in plugin settings.', 'xf-translator'),
                array('status' => 500)
            );
        }

        $auth_header = $request->get_header('Authorization');
        if (empty($auth_header)) {
            return new WP_Error(
                'missing_auth',
                __('Authorization header required. Use: Authorization: Bearer YOUR_TOKEN', 'xf-translator'),
                array('status' => 401)
            );
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $auth_header, $matches)) {
            return new WP_Error(
                'invalid_auth_format',
                __('Invalid Authorization format. Use: Bearer YOUR_TOKEN', 'xf-translator'),
                array('status' => 401)
            );
        }

        $provided_token = trim($matches[1]);
        if (!hash_equals($token, $provided_token)) {
            return new WP_Error(
                'invalid_token',
                __('Invalid worker API token.', 'xf-translator'),
                array('status' => 403)
            );
        }

        return true;
    }
}
