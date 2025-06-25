<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function get_order_quote_status_info($order) {
    $status = $order->get_meta('quote_status') ?: 'Quote Not Sent';
    $last_sent = $order->get_meta('quote_last_sent');
    $last_modified = $order->get_date_modified();
    $is_outdated = false;

    if ($last_sent && $last_modified) {
        $is_outdated = strtotime($last_modified->date('Y-m-d H:i:s')) > strtotime($last_sent);
    }

    return [
        'status' => $status,
        'last_sent' => $last_sent,
        'is_outdated' => $is_outdated,
    ];
}

add_action('wp_ajax_send_quote_email', function () {
    check_ajax_referer('send_quote_email_nonce', 'security');

    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);
    if (!$order) wp_send_json_error('Invalid Order ID.');

    $email = $order->get_billing_email();
    if (!$email) wp_send_json_error('No customer email found.');

    $accept_url = site_url('/?accept_quote=yes&order_id=' . $order_id);add_action('init', 'hubwoo_handle_quote_acceptance');
function hubwoo_handle_quote_acceptance() {
        $manual = is_order_manual($order);
        $quote_accepted_stage_id = $manual
            ? get_option('hubspot_stage_quote_accepted_manual')
            : get_option('hubspot_stage_quote_accepted_online');
        if ($quote_accepted_stage_id) {
            update_hubspot_deal_stage($order_id, $quote_accepted_stage_id);
        }

        $order->save(); // <-- FIXED
        hubwoo_send_invoice($order_id);
            hubwoo_send_invoice($order);
function hubwoo_send_invoice($order_id) {

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Steelmark <website@steelmark.com.au>',
        'Reply-To: website@steelmark.com.au',
        'Return-Path: website@steelmark.com.au',
        'X-Mailer: PHP/' . phpversion(),
        'X-Priority: 3 (Normal)'
    ];

    $sent = wp_mail($email, $subject, $message, $headers);
    $now = current_time('mysql', true);

    $order->update_meta_data('quote_status', 'Quote Sent');
    $order->update_meta_data('quote_last_sent', $now);
    $order->save(); // <-- FIXED

    $manual = is_order_manual($order);
    $stage_option_key = $manual ? 'hubspot_stage_quote_sent_manual' : 'hubspot_stage_quote_sent_online';
    $stage_id = get_option($stage_option_key);

    update_hubspot_deal_stage($order_id, $stage_id); // <-- move this out of logging
    log_email_in_hubspot($order_id, 'quote');

    wp_send_json_success('Quote sent successfully.');
});




add_action('wp_ajax_reset_quote_status', function () {
    check_ajax_referer('reset_quote_status_nonce', 'security');

    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);
    if (!$order) wp_send_json_error('Invalid Order ID.');

    $order->delete_meta_data('quote_status');
    $order->delete_meta_data('quote_last_sent');
    $order->save();

    wp_send_json_success('Quote status reset.');
});




add_action('init', 'handle_quote_acceptance');

function handle_quote_acceptance() {
    if (!isset($_GET['accept_quote'], $_GET['order_id']) || sanitize_text_field($_GET['accept_quote']) !== 'yes') {
        return;
    }

    $order_id = absint($_GET['order_id']);
    $order = wc_get_order($order_id);
    if (!$order) {
        error_log("[ERROR] Invalid order ID in handle_quote_acceptance: {$order_id}");
        return;
    }

    $current_status = $order->get_meta('quote_status');
    if ($current_status !== 'Quote Accepted') {
        $order->update_meta_data('quote_status', 'Quote Accepted');

        if ($order->get_status() !== 'pending_payment') {
            $order->update_status('pending_payment', 'Quote accepted by customer');
        }

        $type = function_exists('order_type') ? order_type($order) : 'manual';
        $quote_accepted_stage_id = $type === 'manual'
            ? get_option('hubspot_stage_quote_accepted_manual')
            : get_option('hubspot_stage_quote_accepted_online');

        if ($quote_accepted_stage_id) {
            update_hubspot_deal_stage($order_id, $quote_accepted_stage_id);
        }

        $order->save(); // <-- FIXED

            // ✅ Update HubSpot deal stage for quote acceptance
        $manual = is_order_manual($order);
        $accepted_stage_option = $manual ? 'hubspot_stage_quote_accepted_manual' : 'hubspot_stage_quote_accepted_online';
        $accepted_stage_id = get_option($accepted_stage_option);
        update_hubspot_deal_stage($order_id, $accepted_stage_id);

        log_email_in_hubspot($order_id, 'quote_accepted');

        // ✅ Send invoice automatically
        send_invoice($order_id);

        // Trigger invoice send after acceptance
        add_action('template_redirect', function () use ($order) {
            send_invoice($order);
        }, 99);
    }

    wp_redirect(add_query_arg([
        'order_id' => $order_id,
        'key' => $order->get_order_key()
    ], site_url('/quote-accepted/')));
    exit;
}



function send_invoice($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $email = $order->get_billing_email();
    if (!$email) return;

    $order_key = $order->get_order_key();
    $payment_url = site_url("/checkout/order-pay/{$order_id}/?key={$order_key}");

    // === Prepare Email ===
    $subject = sprintf('[Steelmark Invoice] Order #%s is ready for payment', $order->get_order_number());

    ob_start();
    wc_get_template('emails/email-header.php', [], '', get_stylesheet_directory() . '/woocommerce/');
    wc_get_template('emails/customer-invoice.php', ['order' => $order, 'payment_url' => $payment_url], '', get_stylesheet_directory() . '/woocommerce/');
    wc_get_template('emails/email-footer.php', [], '', get_stylesheet_directory() . '/woocommerce/');
    $message = ob_get_clean();

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Steelmark <website@steelmark.com.au>',
        'Reply-To: website@steelmark.com.au',
        'Return-Path: website@steelmark.com.au',
        'X-Mailer: PHP/' . phpversion(),
        'X-Priority: 3 (Normal)'
    ];

    wp_mail($email, $subject, $message, $headers);

    // === Update order meta
    $order->update_meta_data('invoice_last_sent', current_time('mysql', true));
    $order->update_meta_data('quote_status', 'Invoice Sent');

    // === Determine invoice stage
    $manual = is_order_manual($order);
    $stage_option = $manual ? 'hubspot_stage_invoice_sent_manual' : 'hubspot_stage_invoice_sent_online';
    $stage_id = get_option($stage_option);

    // After wp_mail() is called
    log_email_in_hubspot($order->get_id(), 'invoice');
    update_hubspot_deal_stage($order->get_id(), $stage_id); // ← THIS MUST BE CALLED

    $order->update_meta_data('hubspot_dealstage_id', $stage_id);
    $order->update_meta_data('invoice_status', 'Invoice Sent');
    $order->save();

}





function update_hubspot_deal_stage($order_id, $stage_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        error_log("[ERROR] Invalid order in update_hubspot_deal_stage(): {$order_id}");
        return;
    }

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
    $body = json_encode([
        'properties' => [
            'dealstage' => $stage_id
        ]
    ]);

    $response = wp_remote_request($url, [
        'method'    => 'PATCH', // <-- FIXED
        'headers'   => [
            'Authorization' => "Bearer {$access_token}",
            'Content-Type'  => 'application/json',
        ],
        'body'      => $body,
    ]);

    $status = wp_remote_retrieve_response_code($response);
    if ($status !== 200) {
        $error_body = wp_remote_retrieve_body($response);
        error_log("[ERROR] HubSpot stage update failed with status {$status} for deal {$deal_id}. Response: {$error_body}");
        return;
    }

    error_log("[DEBUG] HubSpot deal {$deal_id} stage updated to {$stage_id}");
}



function log_email_in_hubspot($order_id, $email_type = 'general', $subject_override = null, $body_override = null) {
    $access_token = manage_hubspot_access_token();
    $order = wc_get_order($order_id);
    if (!$order) {
        error_log("[HubSpot][ERROR] Invalid order object in log_email_in_hubspot().");
        return;
    }

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

    $order_key = $order->get_order_key();
    $payment_url = get_site_url() . "/checkout/order-pay/{$order_id}/?key={$order_key}";

    // === Email Subject ===
    if (!$subject_override) {
        $subject_override = match($email_type) {
            'quote' => "Quote Sent for Order #{$order_id}",
            'invoice' => "Invoice Sent for Order #{$order_id}",
            'quote_accepted' => "Quote Accepted for Order #{$order_id}",
            default => "Email Sent for Order #{$order_id}"
        };
    }

    // === Email Body ===
    if (!$body_override) {
        $body_override = match($email_type) {
            'quote' => "A quote has been sent for Order #{$order_id}. The customer received an email with a link to accept the quote.\n\nOrder Details:\nOrder ID: {$order_id}\nPayment Link: {$payment_url}",
            'invoice' => "An invoice has been sent for Order #{$order_id}. The customer can use the link below to complete the payment.\n\nOrder Details:\nOrder ID: {$order_id}\nPayment Link: {$payment_url}",
            'quote_accepted' => "The quote for Order #{$order_id} has been accepted by the customer and the order is now pending payment.\n\nPayment Link: {$payment_url}",
            default => "An email has been sent for Order #{$order_id}.\n\nOrder Details:\nOrder ID: {$order_id}\nPayment Link: {$payment_url}"
        };
    }

    // === Step 1: Create Email Record ===
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
    if (empty($email_response_body['id'])) {
        error_log("[HubSpot][ERROR] Failed to create email object: " . print_r($email_response_body, true));
        return;
    }

    $email_id = $email_response_body['id'];
    error_log("[HubSpot][DEBUG] Created email ID {$email_id} for Order #{$order_id}");

    // === Step 2: Associate Email with Deal ===
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

    $association_response_body = json_decode(wp_remote_retrieve_body($association_response), true);
    if (!empty($association_response_body['status']) && $association_response_body['status'] === "error") {
        error_log("[HubSpot][ERROR] Association error: " . print_r($association_response_body, true));
    } else {
        error_log("[HubSpot][DEBUG] Email associated with Deal ID {$deal_id}");
    }
}


add_filter('wp_mail_content_type', function() {
    return 'text/html';
});
