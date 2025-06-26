add_action('wp_ajax_manually_trigger_abandoned_email', function () {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'hubspot-woocommerce-sync' ), 403 );
    }
    global $wpdb;
    if ( ! $email_id ) {
        wp_send_json_error( __( 'Missing ID.', 'hubspot-woocommerce-sync' ) );
    }
    if ( ! $row || $row['status'] !== 'pending' ) {
        wp_send_json_error( __( 'Email not found or already sent.', 'hubspot-woocommerce-sync' ) );
    }
        wp_send_json_error( __( 'Send failed.', 'hubspot-woocommerce-sync' ) );
    }

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
