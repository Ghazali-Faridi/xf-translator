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
		// Unschedule any legacy cron events (processing moved to external workers)
		if (function_exists('wp_unschedule_all_events')) {
			wp_unschedule_all_events('xf_translator_process_new_cron');
			wp_unschedule_all_events('xf_translator_process_old_cron');
		}

		flush_rewrite_rules();
	}

}
