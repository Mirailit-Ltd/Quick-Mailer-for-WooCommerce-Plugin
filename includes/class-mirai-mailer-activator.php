<?php

/**
 * Fired during plugin activation
 *
 * @link       https://https://mirailit.com/
 * @since      1.0.0
 *
 * @package    Mirai_Mailer
 * @subpackage Mirai_Mailer/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Mirai_Mailer
 * @subpackage Mirai_Mailer/includes
 * @author     Mirailit Ltd <mirailit@mirailit.com>
 */
class Mirai_Mailer_Activator
{

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'mirai_email_templates';

		// Check if the table exists in the cache first
		$table_exists = wp_cache_get('table_exists_' . $table_name);

		// If it's not in the cache, perform the operation to check table existence
		if ($table_exists === false) {
			// Use the SHOW TABLES LIKE query to check for the table's existence
			$result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));

			$table_exists = !empty($result); // If result is not empty, table exists

			// Cache the result using WordPress's wp_cache_set
			wp_cache_set('table_exists_' . $table_name, $table_exists, '', 86400); // The third parameter is the group, which is left as an empty string for default handling
		}

		// Check if the table exists
		if ($table_exists) {
			error_log("Table $table_name already exists");
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				template_name varchar(191) NOT NULL,
				description text NOT NULL,
				subject text NOT NULL,
				body text NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE (template_name)
			) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
}
