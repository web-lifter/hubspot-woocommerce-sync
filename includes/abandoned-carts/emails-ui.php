<?php

function render_abandoned_cart_emails_page() {
    global $wpdb;

    $table = $wpdb->prefix . 'hubspot_abandoned_emails';
    $emails = $wpdb->get_results("SELECT * FROM $table ORDER BY send_time ASC LIMIT 100", ARRAY_A);

    echo '<div class="wrap">';
    echo '<h1>Abandoned Cart Email Queue</h1>';

    echo '<p>This table lists upcoming or recently sent abandoned cart recovery emails. You can trigger them manually for testing or troubleshooting.</p>';

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>
        <th>ID</th>
        <th>Cart ID</th>
        <th>Email</th>
        <th>Template</th>
        <th>Status</th>
        <th>Scheduled</th>
        <th>Sent</th>
        <th>Actions</th>
    </tr></thead><tbody>';

    if (empty($emails)) {
        echo '<tr><td colspan="8">No scheduled emails found.</td></tr>';
    } else {
        foreach ($emails as $email) {
            echo '<tr>';
            echo '<td>' . esc_html($email['id']) . '</td>';
            echo '<td>' . esc_html($email['cart_id']) . '</td>';
            echo '<td>' . esc_html($email['recipient_email']) . '</td>';
            echo '<td>' . esc_html($email['template_key']) . '</td>';
            echo '<td>' . esc_html($email['status']) . '</td>';
            echo '<td>' . esc_html($email['send_time']) . '</td>';
            echo '<td>' . esc_html($email['sent_time'] ?: '—') . '</td>';
            echo '<td>';
            if ($email['status'] === 'pending') {
                echo '<button class="button trigger-email-now" data-id="' . esc_attr($email['id']) . '">Send Now</button>';
            } else {
                echo '—';
            }
            echo '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    echo '</div>';

    ?>
    <script>
    jQuery(function($) {
        $('.trigger-email-now').on('click', function() {
            const id = $(this).data('id');
            const btn = $(this);
            btn.text('Sending...');

            $.post(ajaxurl, {
                action: 'manually_trigger_abandoned_email',
                email_id: id
            }, function(response) {
                if (response.success) {
                    alert('Email sent.');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    btn.text('Send Now');
                }
            });
        });
    });
    </script>
    <?php
}
