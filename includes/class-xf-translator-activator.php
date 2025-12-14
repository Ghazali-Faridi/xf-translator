<?php

/**
 * Fired during plugin activation
 *
 * @link       https://xfinitive.co
 * @since      1.0.0
 *
 * @package    Xf_Translator
 * @subpackage Xf_Translator/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Xf_Translator
 * @subpackage Xf_Translator/includes
 * @author     ghazali <shafe_ghazali@xfinitive.co>
 */
class Xf_Translator_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-settings.php';
		$settings = new Settings();
		
		// Get settings (defaults to enabled if not set)
		$enable_new = $settings->get('enable_new_translations_cron', true);
		$enable_old = $settings->get('enable_old_translations_cron', true);
		
		// Schedule cron events only if enabled
		if ($enable_new && !wp_next_scheduled('xf_translator_process_new_cron')) {
			wp_schedule_event(time(), 'every_1_minute', 'xf_translator_process_new_cron');
		}
		
		if ($enable_old && !wp_next_scheduled('xf_translator_process_old_cron')) {
			wp_schedule_event(time(), 'every_1_minute', 'xf_translator_process_old_cron');
		}
		
		// Flush rewrite rules to register new author archive rules
		flush_rewrite_rules();
	}

}
