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
		// Clean up any legacy translation cron events if they exist (e.g. from older plugin versions)
		if (function_exists('wp_unschedule_all_events')) {
			wp_unschedule_all_events('xf_translator_process_new_cron');
			wp_unschedule_all_events('xf_translator_process_old_cron');
		}
	}

}
