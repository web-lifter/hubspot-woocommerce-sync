<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


function process_abandoned_cart_email_queue() {
    global $wpdb;

    $now = current_time('timestamp');
    $table = $wpdb->prefix . 'hubspot_abandoned_carts';

    $carts = $wpdb->get_results("SELECT * FROM {$table} WHERE status = 'active'", ARRAY_A);

    foreach ($carts as $cart) {
        $cart_id = $cart['id'];
        $abandoned_at = strtotime($cart['abandoned_at']);
        $sequence = json_decode($cart['sequence'], true);
        $emails_sent = json_decode($cart['emails_sent'], true) ?: [];

        foreach ($sequence as $step) {
            $subject = $step['subject'] ?? '';
            $delay = isset($step['delay_minutes']) ? intval($step['delay_minutes']) : 0;
            $send_time = $abandoned_at + ($delay * 60);

            if ($now >= $send_time && !in_array($subject, $emails_sent, true)) {
                $success = send_abandoned_cart_email($cart, $subject);

                if ($success) {
                    $emails_sent[] = $subject;

                    $wpdb->update($table, [
                        'emails_sent' => json_encode($emails_sent),
                        'updated_at' => current_time('mysql'),
                    ], ['id' => $cart_id]);

                    log_abandoned_cart_email($cart_id, $subject, $cart['email']);

                    break; // Only send one email per cron run
                }
            }
        }
    }
}

function send_abandoned_cart_email($cart, $subject) {
    $to = $cart['email'];
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $message = generate_abandoned_cart_email_body($cart);

    return wp_mail($to, $subject, $message, $headers);
}

function generate_abandoned_cart_email_body($cart) {
    $restore_url = esc_url(add_query_arg([
        'restore_cart' => $cart['id'],
        '_nonce' => wp_create_nonce('restore_cart_' . $cart['id']),
    ], home_url('/checkout/')));

    return '<h2>Your cart is still waiting</h2><p><a href="' . $restore_url . '">Click here to finish your order</a></p>';
}

function log_abandoned_cart_email($cart_id, $subject, $email) {
    $log_dir = WP_CONTENT_DIR . '/uploads/hubspot-abandoned-cart-log.txt';
    $entry = sprintf("[%s] Email sent for cart %d (%s) — Subject: %s\n", date('Y-m-d H:i:s'), $cart_id, $email, $subject);
    file_put_contents($log_dir, $entry, FILE_APPEND);
}
