<?php
/**
 * Plugin Name: Quick Mailer for WooCommerce
 * Plugin URI: https://mirailit.com/
 * Description: Quick, Easy Emails to Customers right from WooCommerce Dashboard. This Plugin is a powerful and user-friendly tool designed to streamline the communication process between your online store's support team or shop managers and your customers.
 * Version: 1.0.4
 * Author: Mirailit Limited
 * Author URI: https://mirailit.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: quick-mailer-for-woocommerce
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.1
 * WC tested up to: 11.1
 *
 * Quick Mailer for WooCommerce
 * Copyright (C) 2024, Mirailit - https://mirailit.com/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Plugin version, url and paths.
define('QMFW_MAILER_VERSION', '1.0.4');
define('QMFW_MAILER_PATH', plugin_dir_path(__FILE__));
define('QMFW_MAILER_URL', plugin_dir_url(__FILE__));
define('QMFW_MAILER_BASENAME', plugin_basename(__FILE__));

// Admin settings page and email template model.
require_once QMFW_MAILER_PATH . 'includes/admin/settings-page.php';
require_once QMFW_MAILER_PATH . 'email-templates/emails.php';

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-mirai-mailer-activator.php
 */
function qmfw_activate_mailer()
{
    require_once QMFW_MAILER_PATH . 'includes/class-mirai-mailer-activator.php';
    QMFW_Mailer_Activator::activate();
}
register_activation_hook(__FILE__, 'qmfw_activate_mailer');

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 */
function qmfw_declare_hpos_compatibility()
{
    if (class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}
add_action('before_woocommerce_init', 'qmfw_declare_hpos_compatibility');

/**
 * Whether WooCommerce is loaded.
 *
 * @return bool
 */
function qmfw_is_woocommerce_active()
{
    return class_exists('WooCommerce') && function_exists('wc_get_order');
}

/**
 * Show an admin notice when WooCommerce is missing.
 */
function qmfw_woocommerce_missing_notice()
{
    if (qmfw_is_woocommerce_active() || !current_user_can('activate_plugins')) {
        return;
    }
    echo '<div class="notice notice-error"><p>' . esc_html__('Quick Mailer for WooCommerce requires WooCommerce to be installed and active.', 'quick-mailer-for-woocommerce') . '</p></div>';
}
add_action('admin_notices', 'qmfw_woocommerce_missing_notice');

/**
 * Screen id of the WooCommerce order edit screen (HPOS or legacy).
 *
 * @return string
 */
function qmfw_get_order_screen_id()
{
    if (
        class_exists('Automattic\WooCommerce\Utilities\OrderUtil')
        && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
        && function_exists('wc_get_page_screen_id')
    ) {
        return wc_get_page_screen_id('shop-order');
    }
    return 'shop_order';
}

/**
 * Whether the current admin request is the order edit screen (HPOS or legacy).
 *
 * @return bool
 */
function qmfw_is_order_edit_screen()
{
    if (!qmfw_is_woocommerce_active()) {
        return false;
    }

    // WooCommerce 7.7+ knows the difference between the HPOS orders list and the edit screen.
    if (
        class_exists('Automattic\WooCommerce\Utilities\OrderUtil')
        && method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'is_order_edit_screen')
    ) {
        return (bool) Automattic\WooCommerce\Utilities\OrderUtil::is_order_edit_screen();
    }

    $screen = get_current_screen();

    return $screen && qmfw_get_order_screen_id() === $screen->id;
}

/**
 * Enqueue plugin assets only on the order edit screen and the plugin settings page.
 *
 * @param string $hook Current admin page hook.
 */
function qmfw_email_add_scripts($hook)
{
    $is_settings_page = (QMFW_SETTINGS_HOOK === $hook);

    if (!$is_settings_page && !qmfw_is_order_edit_screen()) {
        return;
    }

    wp_enqueue_style('qmfw-email-style', QMFW_MAILER_URL . 'css/email-template.css', array(), QMFW_MAILER_VERSION, 'all');

    // Must stay in the footer: the meta box callback adds more localized data to this handle after <head> is printed.
    wp_enqueue_script('qmfw-mailer-js', QMFW_MAILER_URL . 'js/admin-scripts.js', array('jquery'), QMFW_MAILER_VERSION, true);
    wp_localize_script(
        'qmfw-mailer-js',
        'miraiMailerAjax',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
        )
    );
}
add_action('admin_enqueue_scripts', 'qmfw_email_add_scripts');

/**
 * Add a Settings link to the plugin row.
 *
 * @param array $links Existing links.
 * @return array
 */
function qmfw_add_settings_link($links)
{
    $links[] = '<a href="' . esc_url(admin_url('admin.php?page=quick-mailer-settings')) . '">' . esc_html__('Settings', 'quick-mailer-for-woocommerce') . '</a>';

    return $links;
}
add_filter('plugin_action_links_' . QMFW_MAILER_BASENAME, 'qmfw_add_settings_link');

/**
 * Render the Quick Mailer meta box on the order edit screen.
 *
 * @param WP_Post|WC_Order $post Post (legacy) or order (HPOS) object.
 */
function qmfw_email_meta_box_callback($post)
{
    // Get the order ID from either a WC_Order (HPOS) or a WP_Post (legacy storage).
    $order_id = 0;
    if (is_object($post)) {
        $order_id = method_exists($post, 'get_id') ? $post->get_id() : (int) $post->ID;
    }

    $order = $order_id ? wc_get_order($order_id) : false;
    if (!$order) {
        echo '<p>' . esc_html__('Order not found.', 'quick-mailer-for-woocommerce') . '</p>';
        return;
    }

    $templates = new QMFWEmailTemplates();

    $content = '';
    $subject = '';
    $customer_email = $order->get_billing_email();
    $order_status = $order->get_status();
    $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());

    $order_delivery_date = $order->get_meta('delivery_date');
    $order_delivery_timeslot = $order->get_meta('delivery_time');
    if (empty($order_delivery_date) || empty($order_delivery_timeslot)) {
        $delivery_date = 'N/A';
    } else {
        $delivery_date = $order_delivery_date . ' (' . $order_delivery_timeslot . ')';
    }

    $weight_info = $templates->qmfw_get_remaining_weight($order);
    $total_weight = $weight_info['items_weight'] . ' kg';
    $available_weight = $weight_info['remaining_weight'] . ' kg';

    $address = $order->get_formatted_billing_address();

    $duplicate_orders = $templates->qmfw_get_duplicate_order_numbers($order);
    $duplicate_order_numbers = implode(', ', $duplicate_orders);

    $options = get_option('qmfw_mirai_mailer_settings', array());
    $email_signature = isset($options['qmfw_mirai_mailer_email_signature']) ? $options['qmfw_mirai_mailer_email_signature'] : ' ';

    // Order number can differ from the order id when sequential order number plugins are used.
    $order_number = $order->get_order_number();

    // Available placeholders. All values are cast to strings for the JS replacer.
    $dynamic_data_placeholder = array(
        'customer_email' => (string) $customer_email,
        'order_status' => (string) $order_status,
        'customer_name' => (string) $customer_name,
        'order_number' => (string) $order_number,
        'delivery_date' => (string) $delivery_date,
        'total_weight' => (string) $total_weight,
        'address' => (string) $address,
        'email_signature' => (string) $email_signature,
        'available_weight' => (string) $available_weight,
        'duplicate_order_numbers' => (string) $duplicate_order_numbers,
    );

    $preformatted_emails = $templates->qmfw_get_all_templates();

    // Nonce field for the AJAX requests.
    wp_nonce_field('qmfw_mirai_mailer_email_nonce', 'qmfw_mirai_mailer_email_nonce');

    // Input field ids.
    $customer_email_id = 'customer_email';
    $custom_email_subject_id = 'custom_email_subject';
    $editor_id = 'custom_email_content_wpeditor';

    // Hand the templates and placeholders to the enqueued admin script.
    wp_localize_script(
        'qmfw-mailer-js',
        'qmfwMetaBoxData',
        array(
            'templates' => $preformatted_emails,
            'placeholders' => $dynamic_data_placeholder,
            'subjectId' => $custom_email_subject_id,
            'editorId' => $editor_id,
        )
    );
?>

    <!-- Order Subject and Summary -->
    <div class="order-summary-plugin">
        <div class="problem-template">
            <label for="preformatted_email_select"><?php esc_html_e('Choose a Template', 'quick-mailer-for-woocommerce'); ?></label>

            <select id="preformatted_email_select" name="preformatted_email_type">
                <option value=""><?php esc_html_e('Choose a template...', 'quick-mailer-for-woocommerce'); ?></option>
                <?php foreach ($preformatted_emails as $key => $value) : ?>
                    <option value="<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($key); ?>
                    </option>
                <?php endforeach; ?>
                <option value="New"><?php esc_html_e('+ Create a New template...', 'quick-mailer-for-woocommerce'); ?></option>
            </select>

            <!-- Template name input, hidden until "New" is selected -->
            <label for="template_name" style="display: none;"><?php esc_html_e('Template Name', 'quick-mailer-for-woocommerce'); ?></label>
            <input type="text" name="template_name" id="template_name" placeholder="<?php esc_attr_e('Template Name', 'quick-mailer-for-woocommerce'); ?>" style="display: none;">

            <label for="<?php echo esc_attr($custom_email_subject_id); ?>"><?php esc_html_e('Subject', 'quick-mailer-for-woocommerce'); ?></label>
            <input type="text" name="<?php echo esc_attr($custom_email_subject_id); ?>" id="<?php echo esc_attr($custom_email_subject_id); ?>" value="<?php echo esc_attr($subject); ?>" placeholder="<?php esc_attr_e('Write a Subject', 'quick-mailer-for-woocommerce'); ?>" />

            <label for="<?php echo esc_attr($customer_email_id); ?>"><?php esc_html_e('To (Customer Email)', 'quick-mailer-for-woocommerce'); ?></label>
            <input type="text" name="<?php echo esc_attr($customer_email_id); ?>" id="<?php echo esc_attr($customer_email_id); ?>" value="<?php echo esc_attr($customer_email); ?>" />

            <!-- Checkbox to show raw text without replacing placeholders -->
            <label for="show_raw_text"><?php esc_html_e('Show Placeholders', 'quick-mailer-for-woocommerce'); ?></label>
            <input type="checkbox" name="show_raw_text" id="show_raw_text" value="show_raw_text" />
        </div>

        <div class="order-summary">
            <h3 style="margin-bottom: 6px;"><?php esc_html_e('Order Summary', 'quick-mailer-for-woocommerce'); ?></h3>
            <div class="order-summary-inner">
                <div>
                    <div>
                        <p><?php esc_html_e('Order No:', 'quick-mailer-for-woocommerce'); ?>
                            <strong>#<span id="order_number"><?php echo esc_html($order_number); ?></span></strong>
                            <input type="hidden" id="qmfw_order_number_input" value="<?php echo esc_attr($order_id); ?>">
                        </p>
                    </div>

                    <div>
                        <p><?php esc_html_e('Status:', 'quick-mailer-for-woocommerce'); ?>
                            <strong><?php echo esc_html(ucfirst($order_status)); ?></strong>
                        </p>
                    </div>
                </div>

                <div>
                    <div>
                        <p><?php esc_html_e('Name:', 'quick-mailer-for-woocommerce'); ?>
                            <strong><?php echo esc_html($customer_name); ?></strong>
                        </p>
                    </div>

                    <div>
                        <p><?php esc_html_e('Total Weight:', 'quick-mailer-for-woocommerce'); ?>
                            <strong><?php echo esc_html($total_weight); ?></strong>
                        </p>
                    </div>
                </div>

                <p style="margin-bottom: 6px;"><?php esc_html_e('Delivery Date:', 'quick-mailer-for-woocommerce'); ?>
                    <strong><?php echo esc_html($delivery_date); ?></strong>
                </p>

                <p><?php esc_html_e('Address:', 'quick-mailer-for-woocommerce'); ?><strong> <?php echo wp_kses_post(preg_replace('/<br\s?\/?>/', ', ', $address)); ?></strong></p>
            </div>
        </div>
    </div>

    <div class="email-body-section">
        <label for="<?php echo esc_attr($editor_id); ?>"><?php esc_html_e('Email Body', 'quick-mailer-for-woocommerce'); ?></label>
        <?php
        wp_editor(
            $content,
            $editor_id,
            array(
                'media_buttons' => false,
                'textarea_rows' => 10,
                'quicktags' => false,
            )
        );
        ?>
    </div>

    <div class="button-section">
        <button type="button" id="mirai_mailer_save_email_template" class="save-email-button"><?php esc_html_e('Save Email', 'quick-mailer-for-woocommerce'); ?></button>
        <button type="button" id="qmfw_mirai_mailer_send_email" class="send-email-button"><?php esc_html_e('Send Email', 'quick-mailer-for-woocommerce'); ?></button>
    </div>

    <!-- Which mailer will be used -->
    <?php if (qmfw_check_email_serv_is_set()) : ?>
        <p class="mailer-status"><?php esc_html_e('Email Will be Sent Using Quick Mailer Custom SMTP', 'quick-mailer-for-woocommerce'); ?></p>
    <?php else : ?>
        <p class="mailer-status"><?php esc_html_e('Email Will be Sent Using Default WordPress Mailer', 'quick-mailer-for-woocommerce'); ?></p>
    <?php endif; ?>

    <!-- Available placeholders -->
    <div class="placeholder-card">
        <h4><?php esc_html_e('Available Placeholders', 'quick-mailer-for-woocommerce'); ?></h4>
        <div class="placeholder-card-inner">
            <p>{customer_email} - <?php esc_html_e('Customer Email', 'quick-mailer-for-woocommerce'); ?></p>
            <p>{order_status} - <?php esc_html_e('Order Status', 'quick-mailer-for-woocommerce'); ?></p>
            <p>{customer_name} - <?php esc_html_e('Customer Name', 'quick-mailer-for-woocommerce'); ?></p>
            <p>{order_number} - <?php esc_html_e('Order Number', 'quick-mailer-for-woocommerce'); ?></p>
            <p>{delivery_date} - <?php esc_html_e('Delivery Date', 'quick-mailer-for-woocommerce'); ?></p>
            <p>{total_weight} - <?php esc_html_e('Total Weight', 'quick-mailer-for-woocommerce'); ?></p>
            <p>{available_weight} - <?php esc_html_e('Available Weight', 'quick-mailer-for-woocommerce'); ?></p>
            <p>{address} - <?php esc_html_e('Address', 'quick-mailer-for-woocommerce'); ?></p>
            <p>{email_signature} - <?php esc_html_e('Email Signature', 'quick-mailer-for-woocommerce'); ?></p>
            <p>{duplicate_order_numbers} - <?php esc_html_e('Duplicate Order Numbers', 'quick-mailer-for-woocommerce'); ?></p>
        </div>
    </div>

<?php
}

/**
 * Register the meta box on the order edit screen.
 */
function qmfw_email_meta_box()
{
    // Same test as the asset enqueue, so the box never renders without its script (e.g. on "Add new order").
    if (!qmfw_is_order_edit_screen()) {
        return;
    }

    add_meta_box(
        'qmfw_custom_email_meta_box',
        __('Quick Mailer for WooCommerce', 'quick-mailer-for-woocommerce'),
        'qmfw_email_meta_box_callback',
        qmfw_get_order_screen_id(),
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'qmfw_email_meta_box');

/**
 * Capability and environment checks shared by the AJAX handlers. Dies with a JSON error on failure.
 * The nonce is verified inline in each handler so static analysis can see it next to the $_POST reads.
 */
function qmfw_verify_ajax_capability()
{
    if (!qmfw_is_woocommerce_active()) {
        wp_send_json_error(__('WooCommerce is not active.', 'quick-mailer-for-woocommerce'));
    }

    if (!current_user_can('edit_shop_orders')) {
        wp_send_json_error(__('You do not have permission to do this.', 'quick-mailer-for-woocommerce'));
    }
}

/**
 * AJAX: save an email template.
 */
function qmfw_save_email_template()
{
    if (!check_ajax_referer('qmfw_mirai_mailer_email_nonce', 'nonce', false)) {
        wp_send_json_error(__('Nonce is not valid.', 'quick-mailer-for-woocommerce'));
    }
    qmfw_verify_ajax_capability();

    $sanitized_content = isset($_POST['custom_email_content_wpeditor']) ? wp_kses_post(wp_unslash($_POST['custom_email_content_wpeditor'])) : '';
    $sanitized_subject = isset($_POST['custom_email_subject']) ? sanitize_text_field(wp_unslash($_POST['custom_email_subject'])) : '';
    $sanitized_key = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';

    $error = '';
    if (empty($sanitized_content)) {
        $error .= __('Email Body is empty. ', 'quick-mailer-for-woocommerce');
    }
    if (empty($sanitized_subject)) {
        $error .= __('Email Subject is empty. ', 'quick-mailer-for-woocommerce');
    }
    if (empty($sanitized_key)) {
        $error .= __('Template Key is empty. ', 'quick-mailer-for-woocommerce');
    }
    if ($error) {
        wp_send_json_error($error);
    }

    $email_obj = new QMFWEmailTemplates();

    try {
        $email_obj->qmfw_save_template($sanitized_key, $sanitized_key, $sanitized_subject, $sanitized_content);
        wp_send_json_success(__('Email Template has been saved successfully', 'quick-mailer-for-woocommerce'));
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_qmfw_save_email_template', 'qmfw_save_email_template');

/**
 * AJAX: send the composed email to the customer.
 */
function qmfw_send_custom_email()
{
    if (!check_ajax_referer('qmfw_mirai_mailer_email_nonce', 'nonce', false)) {
        wp_send_json_error(__('Nonce is not valid.', 'quick-mailer-for-woocommerce'));
    }
    qmfw_verify_ajax_capability();

    $order_id = isset($_POST['post_ID']) ? absint($_POST['post_ID']) : 0;
    $message = isset($_POST['custom_email_content_wpeditor']) ? wp_kses_post(wp_unslash($_POST['custom_email_content_wpeditor'])) : '';
    $subject = isset($_POST['custom_email_subject']) ? sanitize_text_field(wp_unslash($_POST['custom_email_subject'])) : '';
    $recipient = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';

    if (empty($message) || empty($subject) || empty($recipient) || empty($order_id)) {
        $error = '';
        if (empty($message)) {
            $error .= __('Email Body is empty. ', 'quick-mailer-for-woocommerce');
        }
        if (empty($subject)) {
            $error .= __('Email Subject is empty. ', 'quick-mailer-for-woocommerce');
        }
        if (empty($recipient)) {
            $error .= __('Recipient Email is empty or invalid. ', 'quick-mailer-for-woocommerce');
        }
        if (empty($order_id)) {
            $error .= __('Order ID is empty. ', 'quick-mailer-for-woocommerce');
        }
        wp_send_json_error($error);
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(__('Order not found.', 'quick-mailer-for-woocommerce'));
    }

    $headers = array('Content-Type: text/html; charset=UTF-8');

    if (qmfw_check_email_serv_is_set()) {
        $email_sent = qmfw_send_email_via_gmail_smtp($recipient, $subject, $message);
    } else {
        $email_sent = wp_mail($recipient, $subject, $message, $headers);
    }

    if (!$email_sent) {
        wp_send_json_error(
            sprintf(
                /* translators: %s: recipient email address */
                __('Email failed to send to %s. Please check your SMTP settings.', 'quick-mailer-for-woocommerce'),
                $recipient
            )
        );
    }

    $order->add_order_note(
        sprintf(
            /* translators: %1$s: recipient email, %2$s: email subject */
            __('Email sent to: %1$s, Subject: %2$s', 'quick-mailer-for-woocommerce'),
            $recipient,
            $subject
        ),
        false, // Not a customer note.
        true   // Added by the current user.
    );

    wp_send_json_success(
        sprintf(
            /* translators: %s: recipient email address */
            __('Email has been sent successfully to %s', 'quick-mailer-for-woocommerce'),
            $recipient
        )
    );
}
add_action('wp_ajax_qmfw_send_custom_email', 'qmfw_send_custom_email');

/**
 * Send an email through the SMTP server configured on the plugin settings page.
 *
 * @param string $to      Recipient.
 * @param string $subject Subject.
 * @param string $message HTML body.
 * @return bool
 */
function qmfw_send_email_via_gmail_smtp($to, $subject, $message)
{
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
    }

    $options = get_option('qmfw_mirai_mailer_settings', array());
    $host = isset($options['qmfw_mirai_mailer_smtp_host']) ? $options['qmfw_mirai_mailer_smtp_host'] : '';
    $username = isset($options['qmfw_mirai_mailer_smtp_username']) ? $options['qmfw_mirai_mailer_smtp_username'] : '';
    $password = isset($options['qmfw_mirai_mailer_smtp_password']) ? $options['qmfw_mirai_mailer_smtp_password'] : '';
    $port = !empty($options['qmfw_mirai_mailer_smtp_port']) ? absint($options['qmfw_mirai_mailer_smtp_port']) : 587;
    $from_address = isset($options['qmfw_mirai_mailer_email_from_address']) ? $options['qmfw_mirai_mailer_email_from_address'] : '';
    $from_name = isset($options['qmfw_mirai_mailer_email_from_name']) ? $options['qmfw_mirai_mailer_email_from_name'] : '';

    if ('' === $host || '' === $username || '' === $password) {
        return false;
    }

    // Enable exceptions so that any failure is reported instead of silently ignored.
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        // Port 465 is implicit TLS (SMTPS); every other port negotiates STARTTLS.
        $mail->SMTPSecure = (465 === $port)
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $port;
        $mail->CharSet = 'UTF-8';

        if (is_email($from_address)) {
            $mail->setFrom($from_address, $from_name);
        }

        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = wp_strip_all_tags($message);
        $mail->isHTML(true);

        return (bool) $mail->send();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * AJAX: return the rendered order notes list so it can be refreshed after sending.
 */
function qmfw_handle_get_order_notes()
{
    if (!check_ajax_referer('qmfw_mirai_mailer_email_nonce', 'nonce', false)) {
        wp_send_json_error(__('Nonce is not valid.', 'quick-mailer-for-woocommerce'));
    }
    qmfw_verify_ajax_capability();

    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $order = $order_id ? wc_get_order($order_id) : false;

    if (!$order) {
        wp_send_json_error(__('Invalid order ID', 'quick-mailer-for-woocommerce'));
    }

    $notes = wc_get_order_notes(array('order_id' => $order_id));

    ob_start();

    foreach ($notes as $note) {
        // Mirror the markup WooCommerce uses in the order notes meta box.
        echo '<li rel="' . esc_attr($note->id) . '" class="note ' . ($note->customer_note ? 'customer-note' : '') . '">';
        echo '<div class="note_content">';
        echo '<p>' . esc_html($note->content) . '</p>';
        echo '</div>';
        echo '<p class="meta">';
        echo '<abbr class="exact-date" title="' . esc_attr($note->date_created->date('Y-m-d H:i:s')) . '">' . esc_html($note->date_created->date_i18n('F j, Y, g:i a')) . '</abbr>';
        echo ' ' . esc_html__('by', 'quick-mailer-for-woocommerce') . ' ' . esc_html($note->added_by);
        echo ' <a href="#" class="delete_note" role="button">' . esc_html__('Delete note', 'quick-mailer-for-woocommerce') . '</a>';
        echo '</p>';
        echo '</li>';
    }

    $notes_html = ob_get_clean();

    wp_send_json_success(array('notes_html' => $notes_html));
}
add_action('wp_ajax_qmfw_handle_get_order_notes', 'qmfw_handle_get_order_notes');

/**
 * Whether the plugin's own SMTP settings should be used instead of wp_mail().
 *
 * @return bool
 */
function qmfw_check_email_serv_is_set()
{
    $options = get_option('qmfw_mirai_mailer_settings', array());

    return !empty($options['qmfw_mirai_mailer_send_email_using']);
}
