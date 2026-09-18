
jQuery(document).ready(function ($) {

    // Send Email Via Ajax
    $('#qmfw_mirai_mailer_send_email').on('click', function () {
        console.log('Send Email Button Clicked');
        // Show loader and disable button
        var sendEmailBtn = $('#qmfw_mirai_mailer_send_email');
        var originalButtonText = sendEmailBtn.text(); // Store the original button text

        
        var order_id = $('#qmfw_order_number_input').val();
        console.log('Order id: ' + order_id);

        var emailData = {
           
            'action': 'qmfw_send_custom_email', // This is the name of the WP AJAX action 
            'nonce': $('#qmfw_mirai_mailer_email_nonce').val(),

            'post_ID': order_id,
            'custom_email_subject': $('#custom_email_subject').val(),
            'customer_email': $('#customer_email').val(),
            'custom_email_content_wpeditor': tinyMCE.get('custom_email_content_wpeditor').getContent(),

        };

        // Disable the button and show a loader
        sendEmailBtn.prop('disabled', true).text('Sending Email...');

        $.ajax({
            url: miraiMailerAjax.ajax_url,
            type: 'post',
            data: emailData,
            success: function (response) {
                // toastr.success(response.data);
                alert(response.data);

                // If note added successfully, refresh the notes list
                $('.order_notes').html('<div class="loader"></div>');
                refresh_order_notes(order_id);
            },
            error: function (response) {
                alert('An error occurred', response.data);
                // toastr.error('An error occurred:', response.data);
            },
            complete: function () {
                // Re-enable the button and remove the loader
                sendEmailBtn.prop('disabled', false).text(originalButtonText);
                // Clear the datas
                $('#custom_email_subject').val('');
                tinyMCE.get('custom_email_content_wpeditor').setContent('');

                //  #preformatted_email_select dropdown clear value
                $('#preformatted_email_select').val('default');
            }

        });
    });

    // Save Email Template Via Ajax
    $('#mirai_mailer_save_email_template').on('click', function () {

        // $('#show_raw_text').is(':checked') if not checked alert to check  placeholders and then save
        if (!$('#show_raw_text').is(':checked')) {
            alert('Please check the Show Placeholders checkbox to save the email template');
            return false;
        }
        console.log('Save Email Template Button Clicked');
        // Show loader and disable button
        var $saveEmailTemplateBtn = $('#mirai_mailer_save_email_template');
        var originalButtonText = $saveEmailTemplateBtn.text(); // Store the original button text

        // Get the key value from the dropdown
        $key = $('#preformatted_email_select').val();
        // If key is New then key will be the input field value template_name
        if ($key == 'New') {
            $key = $('#template_name').val();
        }

        var emailData = {
            //PHP hook for action save_email_template -> add_action('wp_ajax_save_email_template', 'save_email_template');
            'action': 'qmfw_save_email_template', // This is the name of the WP AJAX action 
            'nonce': $('#qmfw_mirai_mailer_email_nonce').val(),
            'key': $key,
            'custom_email_subject': $('#custom_email_subject').val(),
            'customer_email': $('#customer_email').val(),
            'custom_email_content_wpeditor': tinyMCE.get('custom_email_content_wpeditor').getContent(),

        };

        // Disable the button and show a loader
        $saveEmailTemplateBtn.prop('disabled', true).text('Saving Email Template...');


        $.ajax({
            url: miraiMailerAjax.ajax_url,
            type: 'post',
            data: emailData,
            success: function (response) {
                alert(response.data);
            },
            error: function () {
                alert('An error occurred.');
            },
            complete: function () {
                // Re-enable the button and remove the loader
                $saveEmailTemplateBtn.prop('disabled', false).text(originalButtonText);
            }
        });
    });


    // Function to refresh the list of notes
    function refresh_order_notes(order_id) {
        $.ajax({
            type: "POST",
            url: miraiMailerAjax.ajax_url,
            data: {
                action: 'qmfw_handle_get_order_notes',
                nonce: $('#qmfw_mirai_mailer_email_nonce').val(),
                order_id: order_id,
            },
            success: function (response) {
                if (response.success) {
                    // #woocommerce-order-notes inside the div .order_notes replace with response data
                    $('#woocommerce-order-notes .order_notes').html(response.data.notes_html);

                } else {
                    alert('Failed to refresh notes: ' + response.data);
                }
            },
            error: function () {
                alert('Failed to refresh notes.');
            }

        });
    }
});