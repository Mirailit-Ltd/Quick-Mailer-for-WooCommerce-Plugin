jQuery(document).ready(function ($) {

    // ---------- Settings page: show / hide the SMTP password ----------
    $(document).on('click', '.qmfw-toggle-password', function () {
        var input = document.getElementById($(this).data('target'));
        if (!input) {
            return;
        }
        input.type = (input.type === 'password') ? 'text' : 'password';
    });

    // ---------- Order screen: template picker ----------
    var metaBox = window.qmfwMetaBoxData || null;

    function editorId() {
        return metaBox ? metaBox.editorId : 'custom_email_content_wpeditor';
    }

    function subjectId() {
        return metaBox ? metaBox.subjectId : 'custom_email_subject';
    }

    function getEditor() {
        return (window.tinyMCE && tinyMCE.get(editorId())) || null;
    }

    function setEditorContent(html) {
        var editor = getEditor();
        if (editor) {
            editor.setContent(html);
        } else {
            $('#' + editorId()).val(html);
        }
    }

    function getEditorContent() {
        var editor = getEditor();
        return editor ? editor.getContent() : $('#' + editorId()).val();
    }

    function replacePlaceholders(template, data) {
        return template.replace(/{([^{}]*)}/g, function (match, key) {
            return typeof data[key] === 'string' ? data[key] : match;
        });
    }

    // Fill subject and body from a saved template. When `raw` is true the
    // placeholders are left untouched so the template can be edited and re-saved.
    function applyTemplate(key, raw) {
        if (!metaBox || !metaBox.templates || !metaBox.templates[key]) {
            return;
        }
        var template = metaBox.templates[key];
        var subject = raw ? template.subject : replacePlaceholders(template.subject, metaBox.placeholders);
        var body = raw ? template.body : replacePlaceholders(template.body, metaBox.placeholders);

        $('#' + subjectId()).val(subject);
        setEditorContent(body);
    }

    $('#show_raw_text').on('change', function () {
        var selectedKey = $('#preformatted_email_select').val();
        applyTemplate(selectedKey, $(this).is(':checked'));
    });

    $('#preformatted_email_select').on('change', function () {
        var selectedKey = $(this).val();
        if (!selectedKey) {
            return;
        }

        if (selectedKey === 'New') {
            $('#template_name').show().val('');
            $('label[for="template_name"]').show();
            $('#' + subjectId()).val('');
            setEditorContent('');
            return;
        }

        $('#template_name').hide();
        $('label[for="template_name"]').hide();
        applyTemplate(selectedKey, $('#show_raw_text').is(':checked'));
    });

    // ---------- Send email ----------
    $('#qmfw_mirai_mailer_send_email').on('click', function () {
        var sendEmailBtn = $(this);
        var originalButtonText = sendEmailBtn.text();
        var orderId = $('#qmfw_order_number_input').val();

        var emailData = {
            action: 'qmfw_send_custom_email',
            nonce: $('#qmfw_mirai_mailer_email_nonce').val(),
            post_ID: orderId,
            custom_email_subject: $('#' + subjectId()).val(),
            customer_email: $('#customer_email').val(),
            custom_email_content_wpeditor: getEditorContent()
        };

        sendEmailBtn.prop('disabled', true).text('Sending Email...');

        $.ajax({
            url: miraiMailerAjax.ajax_url,
            type: 'post',
            data: emailData,
            success: function (response) {
                alert(response.data);

                if (response.success) {
                    $('.order_notes').html('<div class="loader"></div>');
                    refresh_order_notes(orderId);

                    // Clear the form after a successful send.
                    $('#' + subjectId()).val('');
                    setEditorContent('');
                    $('#preformatted_email_select').val('');
                }
            },
            error: function () {
                alert('An error occurred while sending the email.');
            },
            complete: function () {
                sendEmailBtn.prop('disabled', false).text(originalButtonText);
            }
        });
    });

    // ---------- Save email template ----------
    $('#mirai_mailer_save_email_template').on('click', function () {
        if (!$('#show_raw_text').is(':checked')) {
            alert('Please check the Show Placeholders checkbox to save the email template');
            return false;
        }

        var saveBtn = $(this);
        var originalButtonText = saveBtn.text();

        var key = $('#preformatted_email_select').val();
        if (key === 'New') {
            key = $('#template_name').val();
        }

        var emailData = {
            action: 'qmfw_save_email_template',
            nonce: $('#qmfw_mirai_mailer_email_nonce').val(),
            key: key,
            custom_email_subject: $('#' + subjectId()).val(),
            custom_email_content_wpeditor: getEditorContent()
        };

        saveBtn.prop('disabled', true).text('Saving Email Template...');

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
                saveBtn.prop('disabled', false).text(originalButtonText);
            }
        });
    });

    // Refresh the WooCommerce order notes list after an email is sent.
    function refresh_order_notes(orderId) {
        $.ajax({
            type: 'POST',
            url: miraiMailerAjax.ajax_url,
            data: {
                action: 'qmfw_handle_get_order_notes',
                nonce: $('#qmfw_mirai_mailer_email_nonce').val(),
                order_id: orderId
            },
            success: function (response) {
                if (response.success) {
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
