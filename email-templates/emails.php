<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class QMFWEmailTemplates
{
    const CACHE_GROUP = 'qmfw_mailer';
    const ALL_TEMPLATES_CACHE_KEY = 'all_mirai_email_templates';

    /**
     * Name of the templates table.
     *
     * @return string
     */
    private function get_table_name()
    {
        global $wpdb;

        return $wpdb->prefix . 'mirai_email_templates';
    }

    /**
     * Save (insert or update) an email template.
     */
    public function qmfw_save_template($template_name, $description, $subject, $body)
    {
        $template_name = sanitize_text_field($template_name);
        $description = sanitize_text_field($description);
        $subject = sanitize_text_field($subject);
        $body = wp_kses_post($body);

        global $wpdb;
        $table_name = $this->get_table_name();
        $cache_key = 'template_id_' . md5($template_name);

        $template_id = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($template_id === false) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table, result cached below.
            $template_id = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT id FROM %i WHERE template_name = %s',
                    $table_name,
                    $template_name
                )
            );
        }

        if ($template_id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table.
            $result = $wpdb->update(
                $table_name,
                array(
                    'description' => $description,
                    'subject' => $subject,
                    'body' => $body,
                ),
                array('id' => $template_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table.
            $result = $wpdb->insert(
                $table_name,
                array(
                    'template_name' => $template_name,
                    'description' => $description,
                    'subject' => $subject,
                    'body' => $body,
                ),
                array('%s', '%s', '%s', '%s')
            );
            $template_id = $wpdb->insert_id;
        }

        // Drop the list cache in every case so a partial write can never leave stale data.
        wp_cache_delete(self::ALL_TEMPLATES_CACHE_KEY, self::CACHE_GROUP);
        wp_cache_delete('mirai_email_template_' . (int) $template_id, self::CACHE_GROUP);

        if ($result === false || !$template_id) {
            wp_cache_delete($cache_key, self::CACHE_GROUP);
            throw new Exception(
                $wpdb->last_error
                    ? esc_html($wpdb->last_error)
                    : esc_html__('The template could not be saved.', 'quick-mailer-for-woocommerce')
            );
        }

        wp_cache_set($cache_key, $template_id, self::CACHE_GROUP, DAY_IN_SECONDS);
    }

    /**
     * All templates keyed by template name.
     *
     * @return array
     */
    public function qmfw_get_all_templates()
    {
        global $wpdb;
        $table_name = $this->get_table_name();

        $templates = wp_cache_get(self::ALL_TEMPLATES_CACHE_KEY, self::CACHE_GROUP);

        if ($templates === false) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table, result cached below.
            $templates = $wpdb->get_results(
                $wpdb->prepare('SELECT * FROM %i', $table_name),
                OBJECT
            );
            if (!is_array($templates)) {
                $templates = array();
            }
            wp_cache_set(self::ALL_TEMPLATES_CACHE_KEY, $templates, self::CACHE_GROUP, DAY_IN_SECONDS);
        }

        // Values were unslashed before being stored, so they are returned as-is.
        $preformatted_emails = array();
        foreach ($templates as $template) {
            $preformatted_emails[$template->template_name] = array(
                'subject' => $template->subject,
                'body' => $template->body,
            );
        }

        return $preformatted_emails;
    }

    /**
     * One template by id.
     *
     * @param int $template_id Template id.
     * @return object|null
     */
    public function qmfw_get_template_fields($template_id)
    {
        global $wpdb;
        $table_name = $this->get_table_name();
        $cache_key = 'mirai_email_template_' . absint($template_id);

        $template = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($template === false) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table, result cached below.
            $template = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT template_name, description, subject, body FROM %i WHERE id = %d',
                    $table_name,
                    $template_id
                )
            );
            wp_cache_set($cache_key, $template, self::CACHE_GROUP, DAY_IN_SECONDS);
        }

        return $template;
    }

    /**
     * Item weight and remaining box capacity for an order.
     *
     * Relies on an optional third-party filter `qmfw_get_order_weight_wb` that receives the
     * order and returns an object with `items_weight` and `total_box_count`. Returns zeros when
     * nothing is hooked.
     *
     * @param WC_Order $order Order.
     * @return array
     */
    public function qmfw_get_remaining_weight($order)
    {
        $empty = array(
            'remaining_weight' => 0,
            'items_weight' => 0,
        );

        if (!has_filter('qmfw_get_order_weight_wb')) {
            return $empty;
        }

        $shipping_weight_result = apply_filters('qmfw_get_order_weight_wb', $order);
        if (!is_object($shipping_weight_result) || !isset($shipping_weight_result->items_weight, $shipping_weight_result->total_box_count)) {
            return $empty;
        }

        $items_weight = (float) $shipping_weight_result->items_weight;
        $total_box_count = (int) $shipping_weight_result->total_box_count;

        $shipping_method_title = '';
        foreach ($order->get_items('shipping') as $item) {
            $shipping_method_title = $item->get_method_title();
        }

        // Chilled boxes carry 24 kg, other boxes 25 kg.
        $box_capacity = (strpos($shipping_method_title, 'Chilled') !== false) ? 24 : 25;
        $remaining_weight = ($box_capacity * $total_box_count) - $items_weight;

        return array(
            'remaining_weight' => $remaining_weight,
            'items_weight' => $items_weight,
        );
    }

    /**
     * Order numbers of the customer's other processing orders (the current order is excluded).
     *
     * @param WC_Order $order Order.
     * @return array
     */
    public function qmfw_get_duplicate_order_numbers($order)
    {
        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            return array();
        }

        $customer_orders = wc_get_orders(
            array(
                'customer_id' => $customer_id,
                'status' => array('processing'),
                'limit' => -1,
            )
        );

        $current_id = $order->get_id();
        $order_numbers = array();
        foreach ($customer_orders as $customer_order) {
            if ($customer_order->get_id() === $current_id) {
                continue;
            }
            $order_numbers[] = $customer_order->get_order_number();
        }

        return $order_numbers;
    }
}
