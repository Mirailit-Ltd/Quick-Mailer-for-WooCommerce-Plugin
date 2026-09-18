<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Fired during plugin activation
 *
 * @link       https://mirailit.com/
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
class QMFW_Mailer_Activator
{

	/**
	 * Create or update the email templates table. dbDelta() is idempotent, so this is safe to
	 * run on every activation and will apply schema changes in future versions.
	 *
	 * @since    1.0.0
	 */
	public static function activate()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'mirai_email_templates';

		$charset_collate = $wpdb->get_charset_collate();

		// dbDelta() needs the raw statement; the table name is built from $wpdb->prefix.
		$sql = "CREATE TABLE {$table_name} (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			template_name varchar(191) NOT NULL,
			description text NOT NULL,
			subject text NOT NULL,
			body text NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY template_name (template_name)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}
}
