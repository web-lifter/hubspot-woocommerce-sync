<?php

if (!defined('ABSPATH')) exit;

/**
 * Send a quote email and update HubSpot stage.
 *
 * @param int $order_id Order identifier.
 */
function send_quote($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $email = $order->get_billing_email();
    if (!$email) {
        return;
    }

    $accept_url = add_query_arg([
        'accept_quote' => 'yes',
        'order_id'     => $order_id,
        'key'          => $order->get_order_key(),
    ], site_url('/'));

    $subject = sprintf('[Your Store Quote] Order #%s', $order->get_order_number());

    ob_start();
    wc_get_template('emails/email-header.php', [], '', get_stylesheet_directory() . '/woocommerce/');
    wc_get_template(
        'emails/customer-quote.php',
        ['order' => $order, 'accept_url' => $accept_url],
        '',
        get_stylesheet_directory() . '/woocommerce/'
    );
    wc_get_template('emails/email-footer.php', [], '', get_stylesheet_directory() . '/woocommerce/');
    $message = ob_get_clean();

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Your Store <noreply@example.com>',
        'Reply-To: noreply@example.com',
        'Return-Path: noreply@example.com',
        'X-Mailer: PHP/' . phpversion(),
        'X-Priority: 3 (Normal)',
    ];

    $html_callback = fn() => 'text/html';
    add_filter('wp_mail_content_type', $html_callback);
    wp_mail($email, $subject, $message, $headers);
    remove_filter('wp_mail_content_type', $html_callback);

    $order->update_meta_data('quote_status', 'Quote Sent');
    $order->update_meta_data('quote_last_sent', current_time('mysql', true));

    $manual       = is_order_manual($order);
    $stage_option = $manual ? 'hubspot_stage_quote_sent_manual' : 'hubspot_stage_quote_sent_online';
    $stage_id     = get_option($stage_option);

    if ($stage_id && $order->get_meta('hubspot_deal_id')) {
        update_hubspot_deal_stage($order_id, $stage_id);
        $order->update_meta_data('hubspot_dealstage_id', $stage_id);
        log_email_in_hubspot($order_id, 'quote');
    } else {
        error_log('[HubSpot] ⚠️ Skipping HubSpot sync: No deal ID or stage set.');
    }

    $order->save();
}


/**
 * Sends invoice email and updates HubSpot stage.
 */
function send_invoice($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $email = $order->get_billing_email();
    if (!$email) return;

    $order_key = $order->get_order_key();
    $payment_url = site_url("/checkout/order-pay/{$order_id}/?key={$order_key}");

    $subject = sprintf('[Your Store Invoice] Order #%s is ready for payment', $order->get_order_number());

    ob_start();
    wc_get_template('emails/email-header.php', [], '', get_stylesheet_directory() . '/woocommerce/');
    wc_get_template('emails/customer-invoice.php', ['order' => $order, 'payment_url' => $payment_url], '', get_stylesheet_directory() . '/woocommerce/');
    wc_get_template('emails/email-footer.php', [], '', get_stylesheet_directory() . '/woocommerce/');
    $message = ob_get_clean();

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Your Store <noreply@example.com>',
        'Reply-To: noreply@example.com',
        'Return-Path: noreply@example.com',
        'X-Mailer: PHP/' . phpversion(),
        'X-Priority: 3 (Normal)'
    ];

    // Set mail content type to HTML just for this email
    $html_callback = fn() => 'text/html';
    add_filter('wp_mail_content_type', $html_callback);
    wp_mail($email, $subject, $message, $headers);
    remove_filter('wp_mail_content_type', $html_callback);

    $order->update_meta_data('invoice_last_sent', current_time('mysql', true));
    $order->update_meta_data('invoice_status', 'Invoice Sent');

    $manual = is_order_manual($order);
    $stage_option = $manual ? 'hubspot_stage_invoice_sent_manual' : 'hubspot_stage_invoice_sent_online';
    $stage_id = get_option($stage_option);

    if ($stage_id && $order->get_meta('hubspot_deal_id')) {
        update_hubspot_deal_stage($order_id, $stage_id);
        $order->update_meta_data('hubspot_dealstage_id', $stage_id);
        log_email_in_hubspot($order_id, 'invoice');
    } else {
        error_log("[HubSpot] ⚠️ Skipping HubSpot sync: No deal ID or stage set.");
    }

    $order->save();
}


/**
 * Updates the HubSpot deal stage for the order.
 */
function update_hubspot_deal_stage($order_id, $stage_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $deal_id = $order->get_meta('hubspot_deal_id');
    if (!$deal_id) {
        error_log("[ERROR] Missing hubspot_deal_id for Order #{$order_id}");
        return;
    }

    $access_token = manage_hubspot_access_token();
    if (!$access_token) {
        error_log("[ERROR] Could not retrieve HubSpot access token");
        return;
    }

    $url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}";
    $body = json_encode(['properties' => ['dealstage' => $stage_id]]);

    $response = wp_remote_request($url, [
        'method' => 'PATCH',
        'headers' => [
            'Authorization' => "Bearer {$access_token}",
            'Content-Type'  => 'application/json',
        ],
        'body' => $body,
    ]);

    $status = wp_remote_retrieve_response_code($response);
    if ($status !== 200) {
        $error_body = wp_remote_retrieve_body($response);
        error_log("[ERROR] HubSpot stage update failed with status {$status} for deal {$deal_id}. Response: {$error_body}");
        return;
    }

    error_log("[DEBUG] HubSpot deal {$deal_id} stage updated to {$stage_id}");
}


/**
 * Logs a HubSpot email activity and associates with the deal.
 */
function log_email_in_hubspot($order_id, $email_type = 'general', $subject_override = null, $body_override = null) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $deal_id = $order->get_meta('hubspot_deal_id');
    if (!$deal_id || !is_string($deal_id)) {
        error_log("[HubSpot][ERROR] No valid HubSpot Deal ID found for Order #{$order_id}");
        return;
    }

    $customer_email = $order->get_billing_email();
    if (!$customer_email) {
        error_log("[HubSpot][ERROR] No customer email found for Order #{$order_id}");
        return;
    }

    $access_token = manage_hubspot_access_token();
    if (!$access_token) return;

    $order_key = $order->get_order_key();
    $payment_url = get_site_url() . "/checkout/order-pay/{$order_id}/?key={$order_key}";

    $subject_override ??= match($email_type) {
        'quote' => "Quote Sent for Order #{$order_id}",
        'invoice' => "Invoice Sent for Order #{$order_id}",
        'quote_accepted' => "Quote Accepted for Order #{$order_id}",
        default => "Email Sent for Order #{$order_id}"
    };

    $body_override ??= match($email_type) {
        'quote' => "A quote has been sent for Order #{$order_id}.\nPayment Link: {$payment_url}",
        'invoice' => "An invoice has been sent for Order #{$order_id}.\nPayment Link: {$payment_url}",
        'quote_accepted' => "The quote for Order #{$order_id} has been accepted.\nPayment Link: {$payment_url}",
        default => "Email activity for Order #{$order_id}.\nPayment Link: {$payment_url}"
    };

    $email_payload = [
        "properties" => [
            "hs_email_subject" => $subject_override,
            "hs_email_text" => $body_override,
            "hs_email_direction" => "EMAIL",
            "hs_email_status" => "SENT",
            "hs_timestamp" => time() * 1000
        ]
    ];

    $email_url = "https://api.hubapi.com/crm/v3/objects/emails";
    $email_response = wp_remote_post($email_url, [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($email_payload)
    ]);

    if (is_wp_error($email_response)) {
        error_log("[HubSpot][ERROR] Email creation failed: " . $email_response->get_error_message());
        return;
    }

    $email_response_body = json_decode(wp_remote_retrieve_body($email_response), true);
    $email_id = $email_response_body['id'] ?? null;

    if (!$email_id) {
        error_log("[HubSpot][ERROR] Failed to create email object: " . print_r($email_response_body, true));
        return;
    }

    $association_payload = [
        "inputs" => [[
            "types" => [[
                "associationCategory" => "HUBSPOT_DEFINED",
                "associationTypeId" => 210
            ]],
            "from" => ["id" => $email_id],
            "to" => ["id" => $deal_id]
        ]]
    ];

    $association_url = "https://api.hubapi.com/crm/v4/associations/email/deal/batch/create";
    $association_response = wp_remote_post($association_url, [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($association_payload)
    ]);

    if (is_wp_error($association_response)) {
        error_log("[HubSpot][ERROR] Email association failed: " . $association_response->get_error_message());
        return;
    }

    $result = json_decode(wp_remote_retrieve_body($association_response), true);
    error_log("[HubSpot][DEBUG] Email ID {$email_id} associated with Deal ID {$deal_id}");
}


/**
 * Handles quote acceptance from email link.
 */
add_action('init', 'handle_quote_acceptance');
function handle_quote_acceptance() {
    if (!isset($_GET['accept_quote'], $_GET['order_id']) || $_GET['accept_quote'] !== 'yes') {
        return;
    }

    $order_id = absint($_GET['order_id']);
    $order = wc_get_order($order_id);
    if (!$order) return;

    if ($order->get_meta('quote_status') === 'Quote Accepted') return;

    $order->update_meta_data('quote_status', 'Quote Accepted');

    $manual = is_order_manual($order);
    $stage_option = $manual ? 'hubspot_stage_quote_accepted_manual' : 'hubspot_stage_quote_accepted_online';
    $accepted_stage_id = get_option($stage_option);

    if ($accepted_stage_id && $order->get_meta('hubspot_deal_id')) {
        update_hubspot_deal_stage($order_id, $accepted_stage_id);
    }

    log_email_in_hubspot($order_id, 'quote_accepted');
    send_invoice($order_id);

    wp_redirect(add_query_arg([
        'order_id' => $order_id,
        'key' => $order->get_order_key()
    ], site_url('/quote-accepted/')));
    exit;
}


/**
 * AJAX handler to reset quote status.
 */
add_action('wp_ajax_reset_quote_status', function () {
    check_ajax_referer('reset_quote_status_nonce', 'security');

    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Invalid Order ID.');
    }

    $order->delete_meta_data('quote_status');
    $order->delete_meta_data('quote_last_sent');
    $order->save();

    wp_send_json_success('Quote status reset.');
});
