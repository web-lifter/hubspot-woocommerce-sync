<?php
/**
 * Abandoned Cart Email Queue Processor
 * Runs via WP-Cron or manual URL trigger
 */

if (!defined('ABSPATH')) exit;

add_action('hubspot_process_abandoned_queue', 'hubspot_process_abandoned_email_queue');

function hubspot_process_abandoned_email_queue() {
        hubwoo_log("[ABANDONED EMAIL] Sent '{$subject}' to {$email} [Queue ID: {$entry->id}]");


    $now = current_time('timestamp');

    $sequence = get_option('hubspot_abandoned_sequence', []);
    if (empty($sequence)) return;

    $table = $wpdb->prefix . 'hubspot_abandoned_queue';
    $templates = get_hubspot_email_templates();

    // Process all entries not yet sent and with due time reached
    $entries = $wpdb->get_results("
        SELECT * FROM {$table}
        WHERE sent = 0 AND scheduled_time <= FROM_UNIXTIME({$now})
    ");

    foreach ($entries as $entry) {
        $cart = maybe_unserialize($entry->cart_data);
        $template_id = $entry->template_id;

        if (!isset($templates[$template_id])) continue;

        $template = $templates[$template_id];
        $restore_url = home_url("/checkout?restore_cart_id={$entry->cart_token}");

        $email = $entry->customer_email;
        $subject = $template['subject'];
        $body = str_replace(['{{restore_cart_url}}'], [$restore_url], $template['body']);

        // Send email
        wp_mail($email, $subject, $body, ['Content-Type: text/html']);

        // Mark as sent
        $wpdb->update($table, ['sent' => 1, 'sent_at' => current_time('mysql')], ['id' => $entry->id]);

        // Log for debug
        error_log("[ABANDONED EMAIL] Sent '{$subject}' to {$email} [Queue ID: {$entry->id}]");
    }
}

add_filter('cron_schedules', function ($schedules) {
    $schedules['every_15_minutes'] = [
        'interval' => 900,
        'display' => __('Every 15 Minutes')
    ];
    return $schedules;
});

