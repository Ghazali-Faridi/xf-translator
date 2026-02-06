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
		// Translation processing is done by external workers (DigitalOcean). No wp-cron scheduling.
		xf_translator_unschedule_all_events('xf_translator_process_new_cron');
		xf_translator_unschedule_all_events('xf_translator_process_old_cron');
		// Flush rewrite rules to register new author archive rules
		flush_rewrite_rules();
	}

}
