<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class QMFWEmailTemplates
{
    // Save Email template to Database
    public function qmfw_save_template($template_name, $description, $subject, $body)
    {
        $template_name = sanitize_text_field($template_name);
        $description = sanitize_text_field($description);
        $subject = sanitize_text_field($subject);

        $body = wp_kses_post($body);

        global $wpdb;
        $table_name = $wpdb->prefix . 'mirai_email_templates';
        $cache_key = 'template_id_' . $template_name; // Unique cache key based on the template name

        // Attempt to get the template ID from the cache
        $template_id = wp_cache_get($cache_key);

        // Check if the template ID exists in the cache
        if ($template_id === false) {
            // If not found in cache, perform the database query to get the template ID
            $template_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table_name WHERE template_name = %s",
                    $template_name
                )
            );

            // Cache the template ID (or null if not found), set an expiration for the cache (e.g., 86400 seconds for 24 hours)
            wp_cache_set($cache_key, $template_id, '', 86400);
        }

        if ($template_id) {
            // Update the existing template
            $wpdb->update(
                $table_name,
                array(
                    'description' => $description,
                    'subject' => $subject,
                    'body' => $body
                ),
                array('id' => $template_id),
                array(
                    '%s',
                    '%s',
                    '%s'
                ),
                array('%d')
            );
        } else {
            // Insert a new template
            $wpdb->insert(
                $table_name,
                array(
                    'template_name' => $template_name,
                    'description' => $description,
                    'subject' => $subject,
                    'body' => $body
                ),
                array(
                    '%s',
                    '%s',
                    '%s',
                    '%s'
                )
            );
        }
    }

    // Get Email Template from Database
    function qmfw_get_all_templates()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mirai_email_templates';
        $cache_key = 'all_mirai_email_templates'; // Unique cache key for all templates

        // Attempt to get the templates from the cache
        $templates = wp_cache_get($cache_key);

        // Check if the templates exist in the cache
        if ($templates === false) {
            // If not found in cache, perform the database query to get all templates
            $templates = $wpdb->get_results("SELECT * FROM {$table_name}", OBJECT);

            // Cache the templates, set an expiration for the cache (e.g., 86400 seconds for 24 hours)
            wp_cache_set($cache_key, $templates, '', 86400);
        }

        // Prepare the array
        $preformatted_emails = array();
        foreach ($templates as $template) {
            $preformatted_emails[$template->template_name] = array(
                'subject' => stripslashes_deep($template->subject),
                'body' => stripslashes_deep($template->body)
            );
        }
        return $preformatted_emails;
    }

    // Get Specific Email Template from table
    function qmfw_get_template_fields($template_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mirai_email_templates';
        $cache_key = 'mirai_email_template_' . $template_id; // Unique cache key for the template

        // Attempt to get the specific template from the cache
        $template = wp_cache_get($cache_key);

        // Check if the template exists in the cache
        if ($template === false) {
            // Directly passing the prepared statement to $wpdb->get_row()
            $template = $wpdb->get_row($wpdb->prepare(
                "SELECT template_name, description, subject, body FROM {$table_name} WHERE id = %d",
                $template_id
            ));

            // Cache the template, set an expiration for the cache (e.g., 86400 seconds for 24 hours)
            wp_cache_set($cache_key, $template, '', 86400);
        }

        return $template;
    }

    // Calculate Remaining Weight
    public function qmfw_get_remaining_weight($order)
    {
        if (!function_exists('qmfw_get_order_weight_wb')) {
            return array(
                'remaining_weight' => 0,
                'items_weight' => 0,
            );
        }

        // Get order weight infos
        $shipping_weight_result = apply_filters('qmfw_get_order_weight_wb', $order);
        $items_weight = $shipping_weight_result->items_weight;
        $dry_weight = $shipping_weight_result->dry_weight;
        $chilled_weight = $shipping_weight_result->chilled_weight;
        $dry_box_count = $shipping_weight_result->dry_box_count;
        $chilled_box_count = $shipping_weight_result->chilled_box_count;
        $mixed_box_count = $shipping_weight_result->mixed_box_count;
        $total_box_count = $shipping_weight_result->total_box_count;

        // Get Tracking Info
        $order_id = $order->get_id();
        $tracking_items = get_post_meta($order_id, '_wc_shipment_tracking_items', true);

        // Order Total Info 
        $total_amount = $order->get_total();

        try {
            //  Check Shipping Method Title
            foreach ($order->get_items('shipping') as $item_id => $item) {
                // Get the data in an unprotected array
                $item_data = $item->get_data();
                $shipping_method_title = $item_data['method_title'];
            }


            if (strpos($shipping_method_title, 'Chilled') !== false) {
                //  Total allocateable weight 
                $boxCapacity = 24;
                $total_allocateable_weight = $boxCapacity * $total_box_count;
                $remaining_weight = $total_allocateable_weight - $items_weight;
            } else {
                //  Total allocateable weight 
                $boxCapacity = 25;
                $total_allocateable_weight = $boxCapacity * $total_box_count;
                $remaining_weight = $total_allocateable_weight - $items_weight;
            }
        } catch (Exception $e) {
            $remaining_weight = 0;
        }


        return array(
            'remaining_weight' => $remaining_weight,
            'items_weight' => $items_weight,
        );
    }

    // Get customer all processing woocommerce order numbers
    public function qmfw_get_duplicate_order_numbers($order)
    {
        $customer_id = $order->get_customer_id();
        // Get customer processing orders by woocommerce query
        $customer_orders = wc_get_orders(
            array(
                'customer_id' => $customer_id,
                'status' => array('processing'),
            )
        );

        // Get all order numbers as a array
        $order_numbers = array();
        foreach ($customer_orders as $customer_order) {
            $order_numbers[] = $customer_order->get_order_number();
        }

        return $order_numbers;
    }
}
