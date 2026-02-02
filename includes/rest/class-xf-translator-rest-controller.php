<?php
/**
 * REST API Controller for Worker Endpoints
 *
 * @package Xf_Translator
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers claim-job and submit-result REST routes.
 */
class Xf_Translator_Rest_Controller {

    /**
     * REST namespace.
     */
    const NAMESPACE = 'xf-translator/v1';

    /**
     * Register REST routes.
     */
    public static function register_routes() {
        register_rest_route(self::NAMESPACE, '/claim-job', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'claim_job'),
            'permission_callback' => array(__CLASS__, 'check_auth'),
        ));

        register_rest_route(self::NAMESPACE, '/submit-result', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'submit_result'),
            'permission_callback' => array(__CLASS__, 'check_auth'),
            'args' => array(
                'queue_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'raw_translation_response' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));

        register_rest_route(self::NAMESPACE, '/analyze-batch', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'analyze_batch'),
            'permission_callback' => array(__CLASS__, 'check_auth'),
        ));
    }

    /**
     * Permission callback - verify worker token.
     *
     * @param WP_REST_Request $request Request.
     * @return bool|WP_Error
     */
    public static function check_auth($request) {
        require_once plugin_dir_path(__FILE__) . 'class-xf-translator-rest-auth.php';
        return Xf_Translator_Rest_Auth::authenticate($request);
    }

    /**
     * Claim job handler.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public static function claim_job($request) {
        require_once plugin_dir_path(dirname(__FILE__)) . '../services/class-xf-translator-worker-service.php';
        $service = new Xf_Translator_Worker_Service();
        $result = $service->claim_job();

        return new WP_REST_Response($result, 200);
    }

    /**
     * Submit result handler.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public static function submit_result($request) {
        require_once plugin_dir_path(dirname(__FILE__)) . '../services/class-xf-translator-worker-service.php';
        $service = new Xf_Translator_Worker_Service();
        $result = $service->submit_result(
            $request->get_param('queue_id'),
            $request->get_param('raw_translation_response')
        );

        $status = !empty($result['success']) ? 200 : 400;
        return new WP_REST_Response($result, $status);
    }

    /**
     * Analyze batch handler - processes 50 posts per call.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public static function analyze_batch($request) {
        require_once plugin_dir_path(dirname(__FILE__)) . '../services/class-xf-translator-analyze-service.php';
        $service = new Xf_Translator_Analyze_Service();
        $result = $service->process_batch();
        return new WP_REST_Response($result, 200);
    }
}
