<?php
/**
 * HubSpot Abandoned Cart - Email Templates Admin UI
 */

if (!defined('ABSPATH')) exit;

/**
 * Load saved templates
 */
function get_hubspot_email_templates() {
    return get_option('hubspot_abandoned_email_templates', []);
}

/**
 * Save all templates
 */
function save_hubspot_email_templates($templates) {
    update_option('hubspot_abandoned_email_templates', $templates);
}

/**
 * Render email template management UI
 */
function render_hubspot_email_templates_page() {
    $templates = get_hubspot_email_templates();
    ?>
    <div class="wrap">
        <h1>Email Templates</h1>

        <form id="hubspot-email-template-form">
            <?php wp_nonce_field('hubspot_email_template_nonce', 'hubspot_nonce'); ?>
            <input type="hidden" name="template_id" id="template_id" value="">
            <table class="form-table">
                <tr><th scope="row">Label</th><td><input type="text" name="label" id="label" class="regular-text" required></td></tr>
                <tr><th scope="row">Subject</th><td><input type="text" name="subject" id="subject" class="regular-text" required></td></tr>
                <tr><th scope="row">Body (HTML allowed)</th><td><textarea name="body" id="body" rows="8" class="large-text code" required></textarea></td></tr>
            </table>
            <p><button class="button button-primary">Save Template</button></p>
        </form>

        <hr>
        <h2>Saved Templates</h2>
        <table class="widefat">
            <thead><tr><th>Label</th><th>Subject</th><th>Actions</th></tr></thead>
            <tbody id="template-list">
                <?php foreach ($templates as $id => $tpl): ?>
                    <tr data-id="<?= esc_attr($id) ?>"
                        data-label="<?= esc_attr($tpl['label']) ?>"
                        data-subject="<?= esc_attr($tpl['subject']) ?>"
                        data-body="<?= esc_attr($tpl['body']) ?>">
                        <td><?= esc_html($tpl['label']) ?></td>
                        <td><?= esc_html($tpl['subject']) ?></td>
                        <td>
                            <button class="button edit-template">Edit</button>
                            <button class="button delete-template">Delete</button>
                            <button class="button preview-template">Preview</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div id="template-preview-modal" style="display:none;">
            <h3>Preview</h3>
            <div id="template-preview-content" style="padding:20px; background:#f9f9f9; border:1px solid #ccc;"></div>
        </div>
    </div>

    <script>
    jQuery(function($) {
        $('#hubspot-email-template-form').on('submit', function(e) {
            e.preventDefault();
            const data = {
                action: 'save_hubspot_email_template',
                security: $('#hubspot_nonce').val(),
                template_id: $('#template_id').val(),
                label: $('#label').val(),
                subject: $('#subject').val(),
                body: $('#body').val()
            };
            $.post(ajaxurl, data, () => location.reload());
        });

        $('.edit-template').on('click', function() {
            const row = $(this).closest('tr');
            $('#template_id').val(row.data('id'));
            $('#label').val(row.data('label'));
            $('#subject').val(row.data('subject'));
            $('#body').val(row.data('body'));
        });

        $('.delete-template').on('click', function() {
            if (!confirm('Delete this template?')) return;
            const id = $(this).closest('tr').data('id');
            $.post(ajaxurl, {
                action: 'delete_hubspot_email_template',
                security: $('#hubspot_nonce').val(),
                template_id: id
            }, () => location.reload());
        });

        $('.preview-template').on('click', function() {
            const row = $(this).closest('tr');
            const html = $('<div/>').html(row.data('body')).text();
            $('#template-preview-content').html(html);
            tb_show('Template Preview', '#TB_inline?inlineId=template-preview-modal');
        });
    });
    </script>
    <?php
}

/**
 * Save or update a template
 */
add_action('wp_ajax_save_hubspot_email_template', function () {
    check_ajax_referer('hubspot_email_template_nonce', 'security');
    $templates = get_hubspot_email_templates();
    $id = sanitize_text_field($_POST['template_id']) ?: uniqid('tpl_');
    $templates[$id] = [
        'label'   => sanitize_text_field($_POST['label']),
        'subject' => sanitize_text_field($_POST['subject']),
        'body'    => wp_kses_post($_POST['body']),
    ];
    save_hubspot_email_templates($templates);
    wp_send_json_success();
});

/**
 * Delete a template
 */
add_action('wp_ajax_delete_hubspot_email_template', function () {
    check_ajax_referer('hubspot_email_template_nonce', 'security');
    $templates = get_hubspot_email_templates();
    unset($templates[sanitize_text_field($_POST['template_id'])]);
    save_hubspot_email_templates($templates);
    wp_send_json_success();
});

function render_email_template_preview_page() {
    $templates = get_hubspot_email_templates();

    echo '<div class="wrap"><h1>Email Template Preview & Test</h1>';
    echo '<form method="post">';
    wp_nonce_field('preview_template_test');
    echo '<select name="template_id">';
    foreach ($templates as $id => $tpl) {
        echo '<option value="' . esc_attr($id) . '">' . esc_html($tpl['label']) . '</option>';
    }
    echo '</select> ';
    echo '<input type="email" name="test_email" placeholder="Send test to..." required>';
    echo ' <input type="submit" class="button button-primary" value="Send Test">';
    echo '</form>';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('preview_template_test')) {
        $tpl = $templates[$_POST['template_id']] ?? null;
        if ($tpl) {
            $restore_url = home_url('/checkout?restore_cart_id=TEST123');
            $subject = $tpl['subject'];
            $body = str_replace('{{restore_cart_url}}', $restore_url, $tpl['body']);
            $to = sanitize_email($_POST['test_email']);
            wp_mail($to, "[TEST] $subject", $body, ['Content-Type: text/html']);
            echo '<div class="notice notice-success"><p>Email sent to ' . esc_html($to) . '</p></div>';
        }
    }

    echo '<hr><h2>Preview</h2>';
    echo '<div style="background:#fff;padding:20px;border:1px solid #ccc">';
    echo wp_kses_post($tpl['body'] ?? '<em>Select a template above and send to preview</em>');
    echo '</div></div>';
}
