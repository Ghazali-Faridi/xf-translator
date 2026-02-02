<?php
/**
 * Worker API Service - Job claiming and result submission
 *
 * @package Xf_Translator
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for external worker API operations.
 */
class Xf_Translator_Worker_Service {

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var Xf_Translator_Processor
     */
    private $processor;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->settings = new Settings();
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        $this->processor = new Xf_Translator_Processor();
    }

    /**
     * Claim next job from queue. NEW priority, then OLD.
     *
     * @return array{status: string, job?: array, message?: string}
     */
    public function claim_job() {
        global $wpdb;
        $table = $wpdb->prefix . 'xf_translate_queue';

        // Reclaim stuck jobs
        $this->reclaim_stuck_jobs();

        // Check concurrency limit
        $max = (int) $this->settings->get('max_concurrent_processing', 20);
        $processing = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'processing'");
        if ($processing >= $max) {
            return array('status' => 'busy');
        }

        // Try NEW first, then OLD (exclude EDIT - handled by cron/processor)
        $claimed = $this->atomic_claim($table, 'NEW') ?: $this->atomic_claim($table, 'OLD');
        if (!$claimed) {
            return array('status' => 'empty');
        }

        $payload = $this->build_job_payload($claimed);
        if (!$payload) {
            $this->release_job($claimed['id']);
            return array('status' => 'error', 'message' => $this->processor->get_last_error());
        }

        return array('status' => 'success', 'job' => $payload);
    }

    /**
     * Submit translation result from worker.
     *
     * @param int    $queue_id               Queue entry ID.
     * @param string $raw_translation_response Raw API response from DeepSeek.
     * @return array{success: bool, message?: string}
     */
    public function submit_result($queue_id, $raw_translation_response) {
        return $this->processor->submit_translation_result_from_worker($queue_id, $raw_translation_response);
    }

    /**
     * Atomically claim one pending job.
     *
     * @param string $table Table name.
     * @param string $type  Job type (NEW, OLD).
     * @return array|null Queue entry or null.
     */
    private function atomic_claim($table, $type) {
        global $wpdb;

        $delay = (int) $this->settings->get('processing_delay_minutes', 0);
        $where = "status = 'pending' AND type = %s";
        $params = array($type);

        if ($type === 'NEW' && $delay > 0) {
            $min_time = date('Y-m-d H:i:s', strtotime("-{$delay} minutes"));
            $where .= ' AND created <= %s';
            $params[] = $min_time;
        }

        $wpdb->query('START TRANSACTION');
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT 1 FOR UPDATE",
            $params
        ));

        if (empty($ids)) {
            $wpdb->query('ROLLBACK');
            return null;
        }

        $id = (int) $ids[0];
        $wpdb->update(
            $table,
            array('status' => 'processing', 'updated' => current_time('mysql')),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
        $wpdb->query('COMMIT');

        if ($wpdb->rows_affected !== 1) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
    }

    /**
     * Build job payload for worker.
     *
     * @param array $queue_entry Queue entry row.
     * @return array|null Payload or null on failure.
     */
    private function build_job_payload($queue_entry) {
        return $this->processor->build_job_payload_for_worker($queue_entry);
    }

    /**
     * Release job back to pending (on build failure).
     *
     * @param int $queue_id Queue entry ID.
     */
    private function release_job($queue_id) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'xf_translate_queue',
            array('status' => 'pending', 'updated' => current_time('mysql')),
            array('id' => $queue_id),
            array('%s', '%s'),
            array('%d')
        );
    }

    /**
     * Reclaim jobs stuck in processing too long.
     */
    private function reclaim_stuck_jobs() {
        global $wpdb;
        $max_runtime = (int) $this->settings->get('translation_job_max_runtime_minutes', 15);
        if ($max_runtime <= 0) {
            return;
        }
        $before = date('Y-m-d H:i:s', strtotime("-{$max_runtime} minutes"));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}xf_translate_queue 
            SET status = 'pending', error_message = NULL 
            WHERE status = 'processing' AND updated < %s",
            $before
        ));
    }
}
