<?php

add_action('wp_ajax_manually_trigger_abandoned_email', function () {
    global $wpdb;

    $email_id = absint($_POST['email_id']);
    if (!$email_id) wp_send_json_error('Missing ID.');

    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}hubspot_abandoned_emails WHERE id = %d", $email_id),
        ARRAY_A
    );

    if (!$row || $row['status'] !== 'pending') {
        wp_send_json_error('Email not found or already sent.');
    }

    // Send the email
    $success = hubspot_send_abandoned_cart_email($row);

    if ($success) {
        $wpdb->update(
            "{$wpdb->prefix}hubspot_abandoned_emails",
            ['status' => 'sent', 'sent_time' => current_time('mysql')],
            ['id' => $email_id]
        );
        wp_send_json_success();
    } else {
        wp_send_json_error('Send failed.');
    }
});
