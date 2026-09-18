<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 * Removes the settings option and the saved email templates table.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$qmfw_table_name = $wpdb->prefix . 'mirai_email_templates';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing the plugin's own table on uninstall.
$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $qmfw_table_name));

delete_option('qmfw_mirai_mailer_settings');

if (function_exists('wp_cache_supports') && wp_cache_supports('flush_group')) {
    wp_cache_flush_group('qmfw_mailer');
} else {
    wp_cache_flush();
}
