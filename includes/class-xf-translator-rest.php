<?php
/**
 * REST API for external translation workers (DigitalOcean).
 *
 * @package Xf_Translator
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers and handles claim-job and submit-result endpoints.
 */
class Xf_Translator_Rest {

    const NAMESPACE = 'xf-translator/v1';

    /**
     * Register REST routes.
     */
    public static function register_routes() {
        register_rest_route(self::NAMESPACE, 'claim-job', array(
            'methods'             => array(WP_REST_Server::READABLE, WP_REST_Server::CREATABLE),
            'callback'            => array(__CLASS__, 'claim_job'),
            'permission_callback' => array(__CLASS__, 'check_auth'),
        ));

        register_rest_route(self::NAMESPACE, 'submit-result', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'submit_result'),
            'permission_callback' => array(__CLASS__, 'check_auth'),
        ));
    }

    /**
     * Permission callback: require Bearer token matching plugin setting Worker API Token.
     */
    public static function check_auth($request) {
        $token = self::get_bearer_token($request);
        if ($token === null) {
            return new WP_Error(
                'xf_translator_missing_auth',
                __('Authorization header with Bearer token is required.', 'xf-translator'),
                array('status' => 401)
            );
        }
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-settings.php';
        $settings = new Settings();
        $saved = $settings->get('worker_api_token', '');
        if ($saved === '') {
            return new WP_Error(
                'xf_translator_token_not_configured',
                __('Worker API Token is not set in plugin settings.', 'xf-translator'),
                array('status' => 503)
            );
        }
        if (!hash_equals((string) $saved, (string) $token)) {
            return new WP_Error(
                'xf_translator_invalid_token',
                __('Invalid Bearer token.', 'xf-translator'),
                array('status' => 403)
            );
        }
        return true;
    }

    /**
     * Get Bearer token from Authorization header.
     *
     * @param WP_REST_Request $request
     * @return string|null Token or null if not present
     */
    private static function get_bearer_token($request) {
        $auth = $request->get_header('Authorization');
        if (empty($auth) || stripos($auth, 'Bearer ') !== 0) {
            return null;
        }
        return trim(substr($auth, 7));
    }

    /**
     * GET claim-job: return next job or busy/empty.
     * When a job is returned, lang and language are also at root level for easier worker logging.
     */
    public static function claim_job($request) {
        if (!class_exists('Xf_Translator_Processor')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        }
        $processor = new Xf_Translator_Processor();
        $result = $processor->claim_job_for_worker();
        $result['request_id'] = uniqid('claim.', true);
        if (!empty($result['status']) && $result['status'] === 'success' && !empty($result['job'])) {
            $job = $result['job'];
            $result['lang'] = isset($job['lang']) ? $job['lang'] : (isset($job['language']) ? $job['language'] : '');
            $result['language'] = isset($job['language']) ? $job['language'] : (isset($job['lang']) ? $job['lang'] : '');
        }
        $response = new WP_REST_Response($result, 200);
        $response->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');
        return $response;
    }

    /**
     * POST submit-result: save translated content and mark job completed.
     * Accepts JSON body: { "queue_id": 123, "translated_content": { "title": "...", "content": "...", ... } }
     * Also accepts "translated" as alias for "translated_content".
     */
    public static function submit_result($request) {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }
        if (!is_array($params)) {
            $params = array();
        }

        $queue_id = isset($params['queue_id']) ? absint($params['queue_id']) : 0;
        $translated_content = isset($params['translated_content']) ? $params['translated_content'] : (isset($params['translated']) ? $params['translated'] : (isset($params['raw_translation_response']) ? $params['raw_translation_response'] : (isset($params['result']) ? $params['result'] : (isset($params['response']) ? $params['response'] : (isset($params['translation']) ? $params['translation'] : null)))));

        if (empty($queue_id)) {
            return new WP_REST_Response(
                array('success' => false, 'message' => 'Missing or invalid queue_id.', 'translated_post_id' => null),
                200
            );
        }
        if (!is_array($translated_content) && !is_string($translated_content)) {
            return new WP_REST_Response(
                array('success' => false, 'message' => 'translated_content must be an object (e.g. {"title":"...","content":"...","excerpt":"..."}) or a raw string with labeled lines (e.g. "Title: ...\n\nContent: ...").', 'translated_post_id' => null),
                200
            );
        }

        if (!class_exists('Xf_Translator_Processor')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        }
        $processor = new Xf_Translator_Processor();
        $result = $processor->submit_translation_result($queue_id, $translated_content);

        return new WP_REST_Response($result, 200);
    }
}
