<?php
/*
Plugin Name: Quick Mailer for WooCommerce
Plugin URI: https://aimailer.mirailit.com/
Description: Quick, Easy Emails to Customers right from WooCommerce Dashboard. This Plugin is a powerful and user-friendly tool designed to streamline the communication process between your online store's support team or shop managers and your customers.
Version: 1.0.2
Author: Mirailit Limited
Author URI: https://mirailit.com/
License: GPLv2 or later
Requires PHP: 5.3

Quick Mailer for WooCommerce
Copyright (C) 2024, Mirailit - https://aimailer.mirailit.com/

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/


// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}


// define plugin version, url, paths
define('QMFW_MAILER_VERSION', '1.0.2');
define('QMFW_MAILER_PATH', plugin_dir_path(__FILE__));
define('QMFW_MAILER_URL', plugin_dir_url(__FILE__));

// include admin settings page
require_once QMFW_MAILER_PATH . 'includes/admin/settings-page.php';

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-mirai-mailer-activator.php
 */
function qmfw_activate_mailer()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-mirai-mailer-activator.php';
    QMFW_Mailer_Activator::activate();
}

register_activation_hook(__FILE__, 'qmfw_activate_mailer');


function qmfw_email_add_scripts()
{
    wp_enqueue_style('custom-email-style', plugin_dir_url(__FILE__) . 'css/email-template.css', array(), QMFW_MAILER_VERSION, 'all');

    // Send Email Ajax and Localize
    wp_enqueue_script('mirai-mailer-js', QMFW_MAILER_URL . 'js/admin-scripts.js', array('jquery'), QMFW_MAILER_VERSION, true);
    global $post;
    wp_localize_script(
        'mirai-mailer-js',
        'miraiMailerAjax',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            // if post is not null then send post id
            'order_id' => $post ? $post->ID : null,
        )
    );
}
add_action('admin_enqueue_scripts', 'qmfw_email_add_scripts');

//  Require Email Templates
require_once plugin_dir_path(__FILE__) . 'email-templates/emails.php';

// Use this get shop order page screen
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;


// Add settings links to plugin activate list
function qmfw_add_settings_link($links)
{
    $settings_link = '<a href="admin.php?page=quick-mailer-settings">' . __('Settings', 'quick-mailer-for-woocommerce') . '</a>';
    $pro_link = '<a style="color:#8e0000;font-weight: bold;" href="https://aimailer.mirailit.com" target="_blank">' . __('Go Pro', 'quick-mailer-for-woocommerce') . '</a>';
    array_push($links, $settings_link, $pro_link);

    return $links;
}
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'qmfw_add_settings_link');


// Add custom email meta box
function qmfw_email_meta_box_callback($post)
{

    // Get the order ID
    $order_number = method_exists($post, 'get_id') ? $post->get_id() : $post->ID;


    // Get Order Object
    $order = wc_get_order($order_number);

    global $$qmfw_correct_address_text;

    //  Construct Email Templates
    $templates = new QMFWEmailTemplates();

    // Pull these from the database or the context in which the shortcode is used.
    $content = '';
    $subject = '';
    $customer_email = $order->get_billing_email();

    $order_status = $order->get_status();

    $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

    $order_delivery_date = get_post_meta($order->get_id(), 'delivery_date', true);
    $order_delivery_timeslot = get_post_meta($order->get_id(), 'delivery_time', true);
    if (empty($order_delivery_date) || empty($order_delivery_timeslot)) {
        $delivery_date = 'N/A';
    } else {
        $delivery_date = $order_delivery_date . ' (' . $order_delivery_timeslot . ')';
    }

    $total_weight = $templates->qmfw_get_remaining_weight($order)['items_weight'] . ' kg';
    $available_weight = $templates->qmfw_get_remaining_weight($order)['remaining_weight'] . ' kg';

    $address = $order->get_formatted_billing_address();

    // TODO: Check if working
    $duplicate_orders = $templates->qmfw_get_duplicate_order_numbers($order);
    $duplicate_order_numbers = implode(', ', $duplicate_orders);


    $options = get_option('qmfw_mirai_mailer_settings');
    $email_signature = isset($options['qmfw_mirai_mailer_email_signature']) ? $options['qmfw_mirai_mailer_email_signature'] : ' ';

    // Get Order number , Post Id gives error sometimes
    $order_number = $order->get_order_number();
    // Available Placeholders
    $dynamic_data_placeholder = array(
        'customer_email' => $customer_email,
        'order_status' => $order_status,
        'customer_name' => $customer_name,
        'order_number' => $order_number,
        'delivery_date' => $delivery_date,
        'total_weight' => $total_weight,
        'address' => $address,
        'email_signature' => $email_signature,
        'available_weight' => $available_weight,
        'duplicate_order_numbers' => $duplicate_order_numbers,
    );

    // Get and display all templates
    $saved_templates = $templates->qmfw_get_all_templates();
    $preformatted_emails = $saved_templates;

    // Nonce field for security
    wp_nonce_field('qmfw_mirai_mailer_email_nonce', 'qmfw_mirai_mailer_email_nonce');


    // Input field ids
    $customer_email_id = 'customer_email';
    $textarea_id = 'custom_email_content';
    $custom_email_subject_id = 'custom_email_subject';
    // Wp Editor
    $editor_id = 'custom_email_content_wpeditor';


?>

    <!-- Order Subject and Summary -->
    <div class="order-summary-plugin">
        <div class="problem-template">
            <label for="preformatted_email_select">Choose a Template</label>

            <select id="preformatted_email_select" name="preformatted_email_type">
                <option value="">Choose a template...</option>
                <?php foreach ($preformatted_emails as $key => $value) : ?>
                    <option value="<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($key); ?>
                    </option>
                <?php endforeach; ?>
                <option value="New">+ Create a New template...</option>
            </select>

            <!-- A Template name input field hide at first -->
            <label for="template_name" style="display: none;">Template Name</label>
            <input type="text" name="template_name" id="template_name" placeholder="Template Name" style="display: none;">

            <label for="email-subject">Subject</label>

            <input type="text" name="<?php echo esc_attr($custom_email_subject_id); ?>" id="<?php echo esc_attr($custom_email_subject_id); ?>" value="<?php echo esc_attr($subject); ?>" placeholder="Write a Subject" />
            <label for="customer-email">To (Customer Email)</label>
            <input type="text" name="<?php echo esc_attr($customer_email_id); ?>" id="<?php echo esc_attr($customer_email_id); ?>" value="<?php echo esc_attr($customer_email); ?>" />

            <!-- A Checkbox to Show Raw Texts without replacing placeholder -->
            <label for="show_raw_text">Show Placeholders </label>
            <input type="checkbox" name="show_raw_text" id="show_raw_text" value="show_raw_text" />
        </div>

        <div class="order-summary">
            <h3 style="margin-bottom: 6px;">Order Summary</h3>
            <div class="order-summary-inner">
                <div>
                    <div>
                        <p>Order No:
                            <strong>#<?php echo '<span id="order_number">' . esc_html($order_number) . '</span>';
                                        // hidden input field with order number
                                        echo '<input type="hidden" id="qmfw_order_number_input" value="' . esc_html($order_number) . '">';
                                        ?>
                            </strong>
                        </p>
                    </div>

                    <div>
                        <p>Status:
                            <strong><?php echo esc_html(ucfirst($order_status)); ?></strong>
                        </p>
                    </div>
                </div>

                <div>
                    <div>
                        <p>Name:
                            <strong>
                                <?php echo esc_html($customer_name); ?>
                            </strong>
                        </p>
                    </div>

                    <div>
                        <p>Total Weight:
                            <strong>
                                <?php echo esc_html($total_weight); ?>
                            </strong>
                        </p>
                    </div>
                </div>

                <p style="margin-bottom: 6px;">Delivery Date:
                    <strong>
                        <?php echo esc_html($delivery_date); ?>
                    </strong>
                </p>

                <p>Address:<strong> <?php echo wp_kses_post(preg_replace('/<br\s?\/?>/', ', ', $address)); ?></strong></p>
            </div>
        </div>
    </div>


    <div class="email-body-section">
        <?php

        // Add label for wp editor
        echo '<label for="' . esc_attr($editor_id) . '">Email Body</label>';
        // Wp Editor for Email Body
        $settings = array(
            'media_buttons' => false, // Disable media upload buttons
            'textarea_rows' => 10, // Textarea rows
            // Disable Code/text
            'quicktags' => false,
        );
        wp_editor($content, $editor_id, $settings);


        ?>
    </div>


    <div class="button-section">
        <?php echo '<button type="button" id="mirai_mailer_save_email_template" class="save-email-button">' . esc_html__('Save Email', 'quick-mailer-for-woocommerce') . '</button>'; ?>
        <!-- Send Email Ajax Button -->
        <?php echo '<button type="button" id="qmfw_mirai_mailer_send_email" class="send-email-button">' . esc_html__('Send Email', 'quick-mailer-for-woocommerce') . '</button>'; ?>

    </div>

    <!-- A section showing if using default mailer or Plugin SMPTP Mailer -->
    <?php
    $custom_or_default_mailer = qmfw_check_email_serv_is_set();
    if ($custom_or_default_mailer) {
        echo '<p class="mailer-status">Email Will be Sent Using Quick Mailer Custom SMTP</p>';
    } else {
        echo '<p class="mailer-status">Email Will be Sent Using Default Wordpress Mailer</p>';
    }
    ?>

    <!-- A card to show all the available placeholders -->
    <div class="placeholder-card">
        <h4>Available Placeholders</h4>
        <div class="placeholder-card-inner">
            <p>{customer_email} - Customer Email</p>
            <p>{order_status} - Order Status</p>
            <p>{customer_name} - Customer Name</p>
            <p>{order_number} - Order Number</p>
            <p>{delivery_date} - Delivery Date</p>
            <p>{total_weight} - Total Weight</p>
            <p>{address} - Address</p>
            <p>{email_signature} - Email Signature</p>
            <p>{duplicate_order_numbers} - Duplicate Order Numbers</p>
        </div>
    </div>


    <script>
        // Using jQuery to populate the textarea based on the dropdown selection
        jQuery(document).ready(function($) {

            // Function to replace placeholders in the template
            function replacePlaceholders(template, data) {
                return template.replace(/{([^{}]*)}/g, function(a, b) {
                    return typeof data[b] === 'string' ? data[b] : a;
                });
            }

            // show_raw_text when is toggled then change preformatted_email_select to empty value
            $('#show_raw_text').change(function() {
                var emailsData = <?php echo wp_json_encode($preformatted_emails); ?>;
                var dynamicData = <?php echo wp_json_encode($dynamic_data_placeholder); ?>;
                var selectedKey = $('#preformatted_email_select').val();

                if (!emailsData[selectedKey]) {
                    return;
                }
                if ($(this).is(':checked')) {

                    console.log(emailsData[selectedKey].subject);
                    $('#<?php echo esc_attr($custom_email_subject_id); ?>').val(emailsData[selectedKey].subject);
                    tinyMCE.get('custom_email_content_wpeditor').setContent(emailsData[selectedKey].body);

                } else {

                    // Replace placeholders in the subject
                    var populatedSubject = replacePlaceholders(emailsData[selectedKey].subject, dynamicData);
                    $('#<?php echo esc_attr($custom_email_subject_id); ?>').val(populatedSubject);

                    // Replace placeholders in the body
                    var populatedBody = replacePlaceholders(emailsData[selectedKey].body, dynamicData);
                    // Set emailsData[selectedKey].body to also wp editor custom_email_content_wpeditor by tinyMCE
                    tinyMCE.get('custom_email_content_wpeditor').setContent(populatedBody);

                }
            });


            // Upon slecting an option from the dropdown
            $('#preformatted_email_select').change(function() {
                var selectedKey = $(this).val();
                if (selectedKey) {
                    // If selectedKey is New then show the template name input field
                    if (selectedKey == 'New') {
                        $('#template_name').show();
                        // Also show the label
                        $('label[for="template_name"]').show();
                        $('#template_name').val('');
                        $('#<?php echo esc_attr($custom_email_subject_id); ?>').val('');
                        tinyMCE.get('custom_email_content_wpeditor').setContent('');
                        return;
                    } else {
                        $('#template_name').hide();
                        $('label[for="template_name"]').hide();

                        // Dynamic Data to Replace
                        var dynamicData = <?php echo wp_json_encode($dynamic_data_placeholder); ?>;

                        var emailsData = <?php echo wp_json_encode($preformatted_emails); ?>;

                        if (emailsData[selectedKey]) {

                            // If show_raw_text is checked then show raw text without replacing placeholder
                            if (!$('#show_raw_text').is(':checked')) {
                                // Replace placeholders in the subject
                                var populatedSubject = replacePlaceholders(emailsData[selectedKey].subject, dynamicData);
                                $('#<?php echo esc_attr($custom_email_subject_id); ?>').val(populatedSubject);

                                // Replace placeholders in the body
                                var populatedBody = replacePlaceholders(emailsData[selectedKey].body, dynamicData);
                                // Set emailsData[selectedKey].body to also wp editor custom_email_content_wpeditor by tinyMCE
                                tinyMCE.get('custom_email_content_wpeditor').setContent(populatedBody);
                            }
                            // Else show raw texts without replacing placeholder
                            else {
                                $('#<?php echo esc_attr($custom_email_subject_id); ?>').val(emailsData[selectedKey].subject);
                                tinyMCE.get('custom_email_content_wpeditor').setContent(emailsData[selectedKey].body);
                            }

                        }
                    }
                }
            });
        });
    </script>


<?php
}

// ======= Admin Meta Box for Custom Email =======
function qmfw_email_meta_box()
{
    // Get Shop Order Screen Name
    $screen = wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id('shop-order') : 'shop_order';

    add_meta_box(
        'custom_email_meta_box_id',
        'Quick Mailer for WooCommerce',
        'qmfw_email_meta_box_callback',
        $screen, //'shop_order' or 'shop-order'
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'qmfw_email_meta_box');
// ========= End Admin Meta Box for Custom Email =========

// ========= Save Email Template Ajax Post Reciever and Sender =========
function qmfw_save_email_template()
{

    // Check for nonce security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'qmfw_mirai_mailer_email_nonce')) {
        wp_send_json_error('Nonce is not valid.');
    }

    // Retrieve and unslash the input data
    $custom_email_content = isset($_POST['custom_email_content_wpeditor']) ? wp_unslash($_POST['custom_email_content_wpeditor']) : '';
    $custom_email_subject = isset($_POST['custom_email_subject']) ? wp_unslash($_POST['custom_email_subject']) : '';
    $key = isset($_POST['key']) ? wp_unslash($_POST['key']) : '';

    // Sanitize the input data
    $sanitized_content = wp_kses_post($custom_email_content);
    $sanitized_subject = sanitize_text_field($custom_email_subject);
    $sanitized_key = sanitize_text_field($key);


    $error = '';
    if (empty($sanitized_content)) {
        $error .= 'Email Body is empty. ';
    }
    if (empty($sanitized_subject)) {
        $error .= 'Email Subject is empty. ';
    }
    if (empty($sanitized_key)) {
        $error .= 'Template Key is empty. ';
    }
    if ($error) {
        wp_send_json_error($error);
    }

    // No need to escape before saving as content is already sanitized
    $email_obj = new QMFWEmailTemplates();

    try {
        $email_obj->qmfw_save_template($sanitized_key, $sanitized_key, $sanitized_subject, $sanitized_content);
        wp_send_json_success('Email Template has been saved successfully');
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_qmfw_save_email_template', 'qmfw_save_email_template');




//=========  Send Email Ajax Post Reciever and Sender =========
function qmfw_send_custom_email()
{

    // Check for nonce security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'qmfw_mirai_mailer_email_nonce')) {
        wp_send_json_error('Nonce is not valid.');
    }

    $order_id = isset($_POST['post_ID']) ? absint($_POST['post_ID']) : 0;
    $message = isset($_POST['custom_email_content_wpeditor']) ? wp_kses_post(wp_unslash($_POST['custom_email_content_wpeditor'])) : '';
    $subject = isset($_POST['custom_email_subject']) ? sanitize_text_field(wp_unslash($_POST['custom_email_subject'])) : '';

    $recipient = sanitize_email(wp_unslash($_POST['customer_email']));

    // If any field is empty send error and which field is empty using switch case statement
    if (empty($message) || empty($subject) || empty($recipient) || empty($order_id)) {
        $error = '';
        if (empty($message)) {
            $error .= 'Email Body is empty. ';
        }
        if (empty($subject)) {
            $error .= 'Email Subject is empty. ';
        }
        if (empty($recipient)) {
            $error .= 'Recipient Email is empty. ';
        }
        if (empty($order_id)) {
            $error .= 'Order ID is empty. ';
        }
        wp_send_json_error($error);
    }

    // Remove slashes from message and subject
    $message = stripslashes_deep($message);
    $subject = stripslashes_deep($subject);

    $headers = array('Content-Type: text/html; charset=UTF-8');

    // ================= Send email with email service or custom SMTP ==================

    $custom_or_default_mailer = qmfw_check_email_serv_is_set(); // Returns true if Using AI Mailer Custom SMTP


    if ($custom_or_default_mailer) {
        $email_sent = qmfw_send_email_via_gmail_smtp($recipient, $subject, $message, $headers);
    } else {
        $email_sent = wp_mail($recipient, $subject, $message, $headers);
    }


    if ($email_sent) {
        // Get Order and add note 
        $order = wc_get_order($order_id);
        $order->add_order_note(
            wp_kses_post(sprintf(
                // Translators: %1$s: Recipient email, %2$s: Email subject
                __('Email sent to: %1$s, Subject: %2$s', 'quick-mailer-for-woocommerce'),
                esc_html($recipient),
                esc_html($subject)
            )),
            false, // Is Customer Note (Show the Email Note Customer Also)
            true // Add Username for Admin Log
        );



        // Send Ajax response
        wp_send_json_success('Email has been sent successfully to ' . $recipient);
    } else {
        // Email failed to send
        wp_send_json_error('Email failed to send to ' . $recipient . ' Please check your SMTP settings');
    }
}
add_action('wp_ajax_qmfw_send_custom_email', 'qmfw_send_custom_email');


// Send Email Via Custom GMAIL SMTP setting setup with Plugin settings
function qmfw_send_email_via_gmail_smtp($to, $subject, $message, $headers = '', $attachments = array())
{
    // Include PHPMailer class if not autoloaded
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
    }

    // Create a new PHPMailer instance
    $mail = new PHPMailer\PHPMailer\PHPMailer();

    try {
        // Fetch options from the database
        $options = get_option('qmfw_mirai_mailer_settings');

        // Configure SMTP settings (dynamic)
        $mail->isSMTP();
        $mail->Host = $options['qmfw_mirai_mailer_smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $options['qmfw_mirai_mailer_smtp_username'];
        $mail->Password = $options['qmfw_mirai_mailer_smtp_password'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $options['qmfw_mirai_mailer_smtp_port'];
        // Set email content
        $mail->setFrom($options['qmfw_mirai_mailer_email_from_address'], $options['qmfw_mirai_mailer_email_from_name']); // Replace with your name


        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->isHTML(true); // Set email format to HTML

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
// ========== End Send Email Ajax Post Reciever and Sender =========


//====== Get and Update Order Notes Via AJAX ======
function qmfw_handle_get_order_notes()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'qmfw_mirai_mailer_email_nonce')) {
        wp_send_json_error('Nonce is not valid.');
    } else {
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    }

    if ($order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $notes = wc_get_order_notes(array('order_id' => $order_id));

            ob_start(); // Start output buffer

            foreach ($notes as $note) {
                // Construct each note as WooCommerce does in the admin panel
                echo '<li rel="' . esc_attr($note->id) . '" class="note ' . ($note->customer_note ? 'customer-note' : '') . '">';
                echo '<div class="note_content">';
                echo '<p>' . esc_html($note->content) . '</p>';
                echo '</div>';
                echo '<p class="meta">';
                echo '<abbr class="exact-date" title="' . esc_attr($note->date_created->date('Y-m-d H:i:s')) . '">' . esc_html($note->date_created->date_i18n('F j, Y, g:i a')) . '</abbr>';
                echo ' by ' . esc_html($note->added_by);
                echo ' <a href="#" class="delete_note" role="button">Delete note</a>';
                echo '</p>';
                echo '</li>';
            }

            $notes_html = ob_get_clean();


            wp_send_json_success(array('notes_html' => $notes_html));
        }
    }

    wp_send_json_error('Invalid order ID');
}
add_action('wp_ajax_qmfw_handle_get_order_notes', 'qmfw_handle_get_order_notes');

//====== END Get Order Notes Via AJAX ======


// Check if using custom Mirai Mailer SMTP or default mailer
function qmfw_check_email_serv_is_set()
{

    $options = get_option('qmfw_mirai_mailer_settings');
    // Check if qmfw_mirai_mailer_send_email_using is set in $options
    if (isset($options['qmfw_mirai_mailer_send_email_using'])) {
        $custom_or_default_mailer = $options['qmfw_mirai_mailer_send_email_using'];
        if ($custom_or_default_mailer) {
            return true;
        } else {
            return false;
        }
    } else {
        $custom_or_default_mailer = false;
    }
}
