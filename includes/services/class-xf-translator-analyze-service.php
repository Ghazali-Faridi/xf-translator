<?php
/**
 * Analyze Posts Service - Queue population for missing translations
 *
 * @package Xf_Translator
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for analyzing posts and adding missing translations to queue.
 */
class Xf_Translator_Analyze_Service {

    const BATCH_SIZE = 50;

    /**
     * Process one batch of the active analyze job.
     *
     * @return array{processed: int, total: int, added: int, completed: bool, message?: string}
     */
    public function process_batch() {
        $job = get_transient('xf_analyze_active_job');
        if (!$job || ($job['status'] ?? '') !== 'processing') {
            return array(
                'processed' => 0,
                'total' => 0,
                'added' => 0,
                'completed' => true,
                'message' => __('No active analysis job.', 'xf-translator'),
            );
        }

        $settings = new Settings();
        $languages = $settings->get('languages', array());
        if (empty($languages)) {
            $this->mark_complete($job);
            return array(
                'processed' => $job['processed_posts'],
                'total' => $job['total_posts'],
                'added' => $job['added_entries'],
                'completed' => true,
                'message' => __('No languages configured.', 'xf-translator'),
            );
        }

        $post_ids = $this->get_next_batch($job);
        if (empty($post_ids)) {
            $this->mark_complete($job);
            return array(
                'processed' => $job['processed_posts'],
                'total' => $job['total_posts'],
                'added' => $job['added_entries'],
                'completed' => true,
                'message' => __('Analysis complete.', 'xf-translator'),
            );
        }

        $added = $this->add_queue_entries($post_ids, $languages);
        $job['processed_posts'] += count($post_ids);
        $job['added_entries'] += $added;
        $job['processed_post_ids'] = array_merge(
            $job['processed_post_ids'] ?? array(),
            $post_ids
        );
        if (count($job['processed_post_ids']) > 500) {
            $job['processed_post_ids'] = array_slice($job['processed_post_ids'], -500);
        }
        $job['last_updated'] = current_time('mysql');

        if ($job['processed_posts'] >= $job['total_posts']) {
            $this->mark_complete($job);
        } else {
            set_transient('xf_analyze_active_job', $job, DAY_IN_SECONDS);
        }

        return array(
            'processed' => $job['processed_posts'],
            'total' => $job['total_posts'],
            'added' => $job['added_entries'],
            'completed' => $job['processed_posts'] >= $job['total_posts'],
        );
    }

    /**
     * Get next batch of post IDs.
     *
     * @param array $job Job data.
     * @return int[]
     */
    private function get_next_batch($job) {
        $args = array(
            'post_type' => $job['post_types'],
            'post_status' => 'publish',
            'posts_per_page' => self::BATCH_SIZE,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'AND',
                array('key' => '_xf_translator_original_post_id', 'compare' => 'NOT EXISTS'),
                array('key' => '_api_translator_original_post_id', 'compare' => 'NOT EXISTS'),
                array('key' => '_xf_translator_language', 'compare' => 'NOT EXISTS'),
            ),
        );

        if (!empty($job['start_date']) || !empty($job['end_date'])) {
            $range = array('inclusive' => true);
            if (!empty($job['start_date'])) {
                $range['after'] = $job['start_date'] . ' 00:00:00';
            }
            if (!empty($job['end_date'])) {
                $range['before'] = $job['end_date'] . ' 23:59:59';
            }
            $args['date_query'] = array($range);
        }

        $processed = $job['processed_post_ids'] ?? array();
        if (!empty($processed)) {
            $args['post__not_in'] = $processed;
        }

        $query = new WP_Query($args);
        return $query->posts;
    }

    /**
     * Add queue entries for missing translations.
     *
     * @param int[] $post_ids  Post IDs.
     * @param array $languages Languages config.
     * @return int Number of entries added.
     */
    private function add_queue_entries($post_ids, $languages) {
        global $wpdb;
        $table = $wpdb->prefix . 'xf_translate_queue';

        $meta_keys = array();
        foreach ($languages as $lang) {
            $meta_keys[] = '_xf_translator_translated_post_' . $lang['prefix'];
        }

        $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        $existing = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT parent_post_id, lng FROM {$table} WHERE parent_post_id IN ({$placeholders})",
                ...array_map('intval', $post_ids)
            ),
            ARRAY_A
        );
        $existing_map = array();
        foreach ($existing as $row) {
            $existing_map[$row['parent_post_id'] . '_' . $row['lng']] = true;
        }

        $translation_meta = $this->get_translation_meta($post_ids, $meta_keys, $languages);

        $values = array();
        foreach ($post_ids as $post_id) {
            foreach ($languages as $lang) {
                $key = $post_id . '_' . $lang['name'];
                if (empty($translation_meta[$key]) && empty($existing_map[$key])) {
                    $values[] = $wpdb->prepare(
                        '(%d, NULL, %s, %s, %s, %s)',
                        $post_id,
                        $lang['name'],
                        'pending',
                        'OLD',
                        current_time('mysql')
                    );
                }
            }
        }

        if (empty($values)) {
            return 0;
        }

        $wpdb->query("INSERT INTO {$table} (parent_post_id, translated_post_id, lng, status, type, created) VALUES " . implode(', ', $values));
        return $wpdb->rows_affected ?: count($values);
    }

    /**
     * Get existing translation meta for posts.
     *
     * @param int[] $post_ids   Post IDs.
     * @param array $meta_keys Meta keys.
     * @param array $languages Languages config.
     * @return array Map of post_id_lang => translated_post_id.
     */
    private function get_translation_meta($post_ids, $meta_keys, $languages) {
        global $wpdb;
        $ids = array_map('intval', $post_ids);
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        $keys_placeholder = implode(',', array_fill(0, count($meta_keys), '%s'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ({$ids_placeholder}) AND meta_key IN ({$keys_placeholder})",
                array_merge($ids, $meta_keys)
            ),
            ARRAY_A
        );
        $map = array();
        foreach ($rows as $row) {
            foreach ($languages as $lang) {
                if ($row['meta_key'] === '_xf_translator_translated_post_' . $lang['prefix']) {
                    $tid = (int) $row['meta_value'];
                    if ($tid > 0 && get_post($tid)) {
                        $map[$row['post_id'] . '_' . $lang['name']] = $tid;
                    }
                    break;
                }
            }
        }
        return $map;
    }

    /**
     * Mark job as complete.
     *
     * @param array $job Job data.
     */
    private function mark_complete($job) {
        $job['status'] = 'completed';
        $job['completed_at'] = current_time('mysql');
        set_transient('xf_analyze_active_job', $job, DAY_IN_SECONDS);
    }
}
