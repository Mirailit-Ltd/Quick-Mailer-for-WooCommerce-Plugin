<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

function qmfw_create_settings_menu()
{
    add_menu_page(
        __('Quick Mailer Settings', 'quick-mailer-for-woocommerce'),
        __('Quick Mailer', 'quick-mailer-for-woocommerce'),
        'manage_options',
        'quick-mailer-settings',
        'qmfw_mirai_mailer_settings_page_content',
        'dashicons-email-alt',
        6
    );
}
add_action('admin_menu', 'qmfw_create_settings_menu');

function qmfw_mirai_mailer_settings_page_content()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    // Top-level menu pages do not get the core "Settings saved." notice automatically.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- flag set by options.php on redirect, no action taken.
    if (isset($_GET['settings-updated'])) {
        add_settings_error('qmfw_mirai_mailer_messages', 'qmfw_settings_updated', __('Settings saved.', 'quick-mailer-for-woocommerce'), 'updated');
    }
    settings_errors('qmfw_mirai_mailer_messages');
?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('qmfw_mirai_mailer');
            do_settings_sections('qmfw_mirai_mailer');
            ?>
            <?php submit_button(__('Save Settings', 'quick-mailer-for-woocommerce')); ?>
        </form>
    </div>
<?php
}

/**
 * Sanitize the settings array before it is written to the database.
 *
 * @param mixed $input Raw submitted settings.
 * @return array
 */
function qmfw_sanitize_settings($input)
{
    $input = is_array($input) ? $input : array();

    return array(
        'qmfw_mirai_mailer_send_email_using'   => empty($input['qmfw_mirai_mailer_send_email_using']) ? 0 : 1,
        'qmfw_mirai_mailer_smtp_host'          => isset($input['qmfw_mirai_mailer_smtp_host']) ? sanitize_text_field($input['qmfw_mirai_mailer_smtp_host']) : '',
        'qmfw_mirai_mailer_smtp_username'      => isset($input['qmfw_mirai_mailer_smtp_username']) ? sanitize_text_field($input['qmfw_mirai_mailer_smtp_username']) : '',
        'qmfw_mirai_mailer_smtp_password'      => isset($input['qmfw_mirai_mailer_smtp_password']) ? sanitize_text_field($input['qmfw_mirai_mailer_smtp_password']) : '',
        'qmfw_mirai_mailer_smtp_port'          => isset($input['qmfw_mirai_mailer_smtp_port']) ? absint($input['qmfw_mirai_mailer_smtp_port']) : 0,
        'qmfw_mirai_mailer_email_from_name'    => isset($input['qmfw_mirai_mailer_email_from_name']) ? sanitize_text_field($input['qmfw_mirai_mailer_email_from_name']) : '',
        'qmfw_mirai_mailer_email_from_address' => isset($input['qmfw_mirai_mailer_email_from_address']) ? sanitize_email($input['qmfw_mirai_mailer_email_from_address']) : '',
        'qmfw_mirai_mailer_email_signature'    => isset($input['qmfw_mirai_mailer_email_signature']) ? sanitize_textarea_field($input['qmfw_mirai_mailer_email_signature']) : '',
    );
}

function qmfw_mirai_mailer_settings_init()
{
    register_setting(
        'qmfw_mirai_mailer',
        'qmfw_mirai_mailer_settings',
        array(
            'type'              => 'array',
            'sanitize_callback' => 'qmfw_sanitize_settings',
            'default'           => array(),
        )
    );

    // Section 1: choose the mailer.
    add_settings_section(
        'qmfw_mirai_mailer_mode_section',
        __('Send Email Using Custom SMTP or Use Other Email Provider', 'quick-mailer-for-woocommerce'),
        'qmfw_mirai_mailer_mode_section_cb',
        'qmfw_mirai_mailer'
    );

    add_settings_field(
        'qmfw_mirai_mailer_send_email_using',
        __('Use this Custom SMTP?', 'quick-mailer-for-woocommerce'),
        'qmfw_plugin_smtp_field_callback',
        'qmfw_mirai_mailer',
        'qmfw_mirai_mailer_mode_section',
        array(
            'label_for' => 'qmfw_mirai_mailer_send_email_using',
            'class'     => 'mirai_mailer_row',
        )
    );

    // Section 2: SMTP connection.
    add_settings_section(
        'qmfw_mirai_mailer_smtp_section',
        __('Your SMTP Settings', 'quick-mailer-for-woocommerce'),
        'qmfw_mirai_mailer_settings_section_cb',
        'qmfw_mirai_mailer'
    );

    $smtp_fields = array(
        'qmfw_mirai_mailer_smtp_host'     => __('SMTP Host', 'quick-mailer-for-woocommerce'),
        'qmfw_mirai_mailer_smtp_username' => __('SMTP Username', 'quick-mailer-for-woocommerce'),
    );
    foreach ($smtp_fields as $id => $label) {
        add_settings_field(
            $id,
            $label,
            'qmfw_mirai_mailer_settings_field_cb',
            'qmfw_mirai_mailer',
            'qmfw_mirai_mailer_smtp_section',
            array(
                'label_for' => $id,
                'class'     => 'mirai_mailer_row',
            )
        );
    }

    add_settings_field(
        'qmfw_mirai_mailer_smtp_password',
        __('SMTP Password', 'quick-mailer-for-woocommerce'),
        'qmfw_mirai_mailer_settings_field_password',
        'qmfw_mirai_mailer',
        'qmfw_mirai_mailer_smtp_section',
        array(
            'label_for' => 'qmfw_mirai_mailer_smtp_password',
            'class'     => 'mirai_mailer_row',
        )
    );

    add_settings_field(
        'qmfw_mirai_mailer_smtp_port',
        __('SMTP Port', 'quick-mailer-for-woocommerce'),
        'qmfw_mirai_mailer_settings_field_cb',
        'qmfw_mirai_mailer',
        'qmfw_mirai_mailer_smtp_section',
        array(
            'label_for' => 'qmfw_mirai_mailer_smtp_port',
            'class'     => 'mirai_mailer_row',
        )
    );

    // Section 3: sender details.
    add_settings_section(
        'qmfw_mirai_mailer_sender_section',
        __('Sender Details', 'quick-mailer-for-woocommerce'),
        '__return_null',
        'qmfw_mirai_mailer'
    );

    $sender_fields = array(
        'qmfw_mirai_mailer_email_from_name'    => __('Email From Name', 'quick-mailer-for-woocommerce'),
        'qmfw_mirai_mailer_email_from_address' => __('Email From Address', 'quick-mailer-for-woocommerce'),
    );
    foreach ($sender_fields as $id => $label) {
        add_settings_field(
            $id,
            $label,
            'qmfw_mirai_mailer_settings_field_cb',
            'qmfw_mirai_mailer',
            'qmfw_mirai_mailer_sender_section',
            array(
                'label_for' => $id,
                'class'     => 'mirai_mailer_row',
            )
        );
    }

    add_settings_field(
        'qmfw_mirai_mailer_email_signature',
        __('Email Signature', 'quick-mailer-for-woocommerce'),
        'qmfw_email_signature_callback',
        'qmfw_mirai_mailer',
        'qmfw_mirai_mailer_sender_section',
        array(
            'label_for' => 'qmfw_mirai_mailer_email_signature',
            'class'     => 'mirai_mailer_row',
        )
    );
}
add_action('admin_init', 'qmfw_mirai_mailer_settings_init');

function qmfw_mirai_mailer_mode_section_cb()
{
    echo '<p>' . esc_html__('Tick the box to send emails through the SMTP server below. Leave it unticked to use the default WordPress mailer (or any SMTP plugin you already have).', 'quick-mailer-for-woocommerce') . '</p>';
}

function qmfw_mirai_mailer_settings_section_cb()
{
    echo '<p>' . esc_html__('Enter your SMTP details below to configure email sending.', 'quick-mailer-for-woocommerce') . '</p>';
}

/**
 * Read one value from the saved settings array.
 *
 * @param string $key Setting key.
 * @return string
 */
function qmfw_get_setting_value($key)
{
    $options = get_option('qmfw_mirai_mailer_settings', array());

    return isset($options[$key]) ? (string) $options[$key] : '';
}

function qmfw_mirai_mailer_settings_field_cb($args)
{
    $value = qmfw_get_setting_value($args['label_for']);

    echo '<input type="text" class="regular-text" id="' . esc_attr($args['label_for']) . '" name="qmfw_mirai_mailer_settings[' . esc_attr($args['label_for']) . ']" value="' . esc_attr($value) . '">';
}

function qmfw_mirai_mailer_settings_field_password($args)
{
    $value = qmfw_get_setting_value($args['label_for']);

    echo '<input type="password" class="regular-text" id="' . esc_attr($args['label_for']) . '" name="qmfw_mirai_mailer_settings[' . esc_attr($args['label_for']) . ']" value="' . esc_attr($value) . '" autocomplete="new-password">';
    // The toggle behaviour lives in js/admin-scripts.js.
    echo ' <button type="button" class="button qmfw-toggle-password" data-target="' . esc_attr($args['label_for']) . '" aria-label="' . esc_attr__('Show or hide password', 'quick-mailer-for-woocommerce') . '">&#128065;</button>';
}

function qmfw_plugin_smtp_field_callback($args)
{
    $value = qmfw_get_setting_value($args['label_for']);
?>
    <input type="checkbox" id="<?php echo esc_attr($args['label_for']); ?>" name="qmfw_mirai_mailer_settings[<?php echo esc_attr($args['label_for']); ?>]" value="1" <?php checked(1, (int) $value); ?>>
    <label for="<?php echo esc_attr($args['label_for']); ?>"><?php esc_html_e('Yes', 'quick-mailer-for-woocommerce'); ?></label>
<?php
}

function qmfw_email_signature_callback($args)
{
    $value = qmfw_get_setting_value($args['label_for']);
?>
    <textarea id="<?php echo esc_attr($args['label_for']); ?>" name="qmfw_mirai_mailer_settings[<?php echo esc_attr($args['label_for']); ?>]" rows="5" cols="50"><?php echo esc_textarea($value); ?></textarea>
<?php
}
