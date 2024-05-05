<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function mirai_mailer_create_settings_menu()
{
    add_menu_page(
        __('Quick Mailer Settings', 'mirai-mailer'),
        'Quick Mailer',
        'manage_options',
        'quick-mailer-settings',
        'mirai_mailer_settings_page_content',
        'dashicons-email-alt',
        6
    );
}
add_action('admin_menu', 'mirai_mailer_create_settings_menu');

function mirai_mailer_settings_page_content()
{
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Output the settings form
?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            // Output security fields
            settings_fields('mirai_mailer');

            // Output setting sections and their fields
            do_settings_sections('mirai_mailer');
            ?>
            <p>Upgrade to the Pro version for advanced features and AI-powered email generation.</p>
            <p><a href="https://aimailer.mirailit.com/" target="_blank">Get the Pro Version</a></p>
            <?php
            // Output save settings button
            submit_button(__('Save Settings', 'mirai-mailer'));
            ?>
        </form>
    </div>
<?php
}


function mirai_mailer_settings_init()
{
    // Register settings
    register_setting('mirai_mailer', 'mirai_mailer_settings');


    // Add Setting section and field With checkbox to send email using Custom PLugin SMTP or other plugin email settings
    add_settings_section(
        'mirai_mailer_settings_section',
        __('Send Email Using Custom SMTP or Use Other Email privder', 'mirai-mailer'),
        'mirai_mailer_settings_section_cb',
        'mirai_mailer'
    );

    add_settings_field(
        'mirai_mailer_send_email_using',
        __('Use this Custom SMTP?', 'mirai-mailer'),
        'custom_plugin_smtp_field_callback',
        'mirai_mailer',
        'mirai_mailer_settings_section',
        [
            'label_for' => 'mirai_mailer_send_email_using',
            'class' => 'mirai_mailer_row',
            'mirai_mailer_custom_data' => 'custom',
        ]
    );

    // Add settings section
    add_settings_section(
        'mirai_mailer_settings_section',
        __('Your SMTP Settings', 'mirai-mailer'),
        'mirai_mailer_settings_section_cb',
        'mirai_mailer'
    );

    // Add settings fields SMTP Host
    add_settings_field(
        'mirai_mailer_smtp_host',
        __('SMTP Host', 'mirai-mailer'),
        'mirai_mailer_settings_field_cb',
        'mirai_mailer',
        'mirai_mailer_settings_section',
        [
            'label_for' => 'mirai_mailer_smtp_host',
            'class' => 'mirai_mailer_row',
            'mirai_mailer_custom_data' => 'custom',
        ]
    );

    // SMTP username
    add_settings_field(
        'mirai_mailer_smtp_username',
        __('SMTP Username', 'mirai-mailer'),
        'mirai_mailer_settings_field_cb',
        'mirai_mailer',
        'mirai_mailer_settings_section',
        [
            'label_for' => 'mirai_mailer_smtp_username',
            'class' => 'mirai_mailer_row',
            'mirai_mailer_custom_data' => 'custom',
        ]
    );

    // SMTP password
    add_settings_field(
        'mirai_mailer_smtp_password',
        __('SMTP Password', 'mirai-mailer'),
        'mirai_mailer_settings_field_password',
        'mirai_mailer',
        'mirai_mailer_settings_section',
        [
            'label_for' => 'mirai_mailer_smtp_password',
            'class' => 'mirai_mailer_row',
            'mirai_mailer_custom_data' => 'custom',
        ]
    );

    // SMTP port
    add_settings_field(
        'mirai_mailer_smtp_port',
        __('SMTP Port', 'mirai-mailer'),
        'mirai_mailer_settings_field_cb',
        'mirai_mailer',
        'mirai_mailer_settings_section',
        [
            'label_for' => 'mirai_mailer_smtp_port',
            'class' => 'mirai_mailer_row',
            'mirai_mailer_custom_data' => 'custom',
        ]
    );

    // Add settings section
    add_settings_section(
        'mirai_mailer_settings_section',
        __('Your SMTP Settings', 'mirai-mailer'),
        'mirai_mailer_settings_section_cb',
        'mirai_mailer'
    );

    // Email From Name
    add_settings_field(
        'mirai_mailer_email_from_name',
        __('Email From Name', 'mirai-mailer'),
        'mirai_mailer_settings_field_cb',
        'mirai_mailer',
        'mirai_mailer_settings_section',
        [
            'label_for' => 'mirai_mailer_email_from_name',
            'class' => 'mirai_mailer_row',
            'mirai_mailer_custom_data' => 'custom',
        ]
    );

    // Email From Address
    add_settings_field(
        'mirai_mailer_email_from_address',
        __('Email From Address', 'mirai-mailer'),
        'mirai_mailer_settings_field_cb',
        'mirai_mailer',
        'mirai_mailer_settings_section',
        [
            'label_for' => 'mirai_mailer_email_from_address',
            'class' => 'mirai_mailer_row',
            'mirai_mailer_custom_data' => 'custom',
        ]
    );

    add_settings_field(
        'mirai_mailer_email_signature',
        __('Email Signature', 'mirai-mailer'),
        'mirai_mailer_email_signature_callback',
        'mirai_mailer',
        'mirai_mailer_settings_section',
        [
            'label_for' => 'mirai_mailer_email_signature',
            'class' => 'mirai_mailer_row',
            'mirai_mailer_custom_data' => 'custom',
        ]
    );
}

add_action('admin_init', 'mirai_mailer_settings_init');

function mirai_mailer_settings_section_cb($args)
{
    // Section introduction text
    echo '<p>' . esc_html__('Enter your SMTP details below to configure email sending.', 'mirai-mailer') . '</p>';
}

function mirai_mailer_settings_field_cb($args)
{
    // Output the HTML for the input field
    $options = get_option('mirai_mailer_settings');
    $value = isset($options[$args['label_for']]) ? esc_attr($options[$args['label_for']]) : '';
    echo '<input type="text" id="' . esc_attr($args['label_for']) . '" name="mirai_mailer_settings[' . esc_attr($args['label_for']) . ']" value="' . $value . '">';
}

// Password field callback function
function mirai_mailer_settings_field_password($args)
{
    // Retrieve the value from the options
    $options = get_option('mirai_mailer_settings');
    $value = isset($options[$args['label_for']]) ? esc_attr($options[$args['label_for']]) : '';
    echo '<input type="password" id="' . esc_attr($args['label_for']) . '" name="mirai_mailer_settings[' . esc_attr($args['label_for']) . ']" value="' . $value . '">';
    echo '<span id="togglePassword" style="cursor:pointer;">👁️</span>';

    // Include JavaScript to toggle password visibility
?>
    <script>
        document.getElementById('togglePassword').addEventListener('click', function(e) {
            var passwordInput = document.getElementById('<?php echo esc_js($args['label_for']); ?>');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        });
    </script>
<?php
}


// Callback function for the Checkbox field
function custom_plugin_smtp_field_callback($args)
{
    // Get the value of the setting we've registered with register_setting()
    $options = get_option('mirai_mailer_settings');
    $value = $options[$args['label_for']] ?? '';
?>
    <input type="checkbox" id="<?php echo esc_attr($args['label_for']); ?>" name="mirai_mailer_settings[<?php echo esc_attr($args['label_for']); ?>]" value="1" <?php checked(1, $value); ?>>
    <label for="<?php echo esc_attr($args['label_for']); ?>"><?php esc_html_e('Yes', 'mirai-mailer'); ?></label>
<?php
}

// Field callback function
function mirai_mailer_email_signature_callback($args)
{
    // Get the value of the setting we've registered with register_setting()
    $options = get_option('mirai_mailer_settings');
    $value = $options[$args['label_for']] ?? '';
?>
    <textarea id="<?php echo esc_attr($args['label_for']); ?>" name="mirai_mailer_settings[<?php echo esc_attr($args['label_for']); ?>]" rows="5" cols="50"><?php echo esc_textarea($value); ?></textarea>
<?php
}
