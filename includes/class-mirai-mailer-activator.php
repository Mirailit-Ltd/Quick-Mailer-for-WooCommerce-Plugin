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

		// If $table_name exists then we assume the table has already been created
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
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
