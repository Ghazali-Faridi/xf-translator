<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://xfinitive.co
 * @since      1.0.0
 *
 * @package    Xf_Translator
 * @subpackage Xf_Translator/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Xf_Translator
 * @subpackage Xf_Translator/includes
 * @author     ghazali <shafe_ghazali@xfinitive.co>
 */
class Xf_Translator_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// Unschedule cron events when plugin is deactivated
		$timestamp = wp_next_scheduled('xf_translator_process_new_cron');
		if ($timestamp) {
			wp_unschedule_event($timestamp, 'xf_translator_process_new_cron');
		}
		
		$timestamp = wp_next_scheduled('xf_translator_process_old_cron');
		if ($timestamp) {
			wp_unschedule_event($timestamp, 'xf_translator_process_old_cron');
		}
	}

}
