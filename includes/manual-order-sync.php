<?php
/**
 * Sync PayWay order numbers to HubSpot and set manual order types.
 */

if (!defined('ABSPATH')) exit;

/**
 * Sets manual order type if created in admin (non-REST, non-CLI).
 */
add_action('woocommerce_new_order', 'hubwoosync_set_manual_order_type_for_admin', 5, 2);
function hubwoosync_set_manual_order_type_for_admin($order_id, $order) {
    if (!is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || php_sapi_name() === 'cli') return;

    if (!is_a($order, 'WC_Order')) {
        $order = wc_get_order($order_id);
    }

    $existing = $order->get_meta('order_type');
    if (strtolower($existing) !== 'manual') {
        $order->update_meta_data('order_type', 'manual');
        $order->save_meta_data();
        error_log("[Order Type] Order #$order_id created in admin — marked as manual.");
    }
}

/**
 * Sets manual order type if created via REST API or CLI.
 */
add_action('woocommerce_new_order', 'hubwoosync_set_manual_order_type_for_rest_api', 30, 2);
function hubwoosync_set_manual_order_type_for_rest_api($order_id, $order) {
    if (!is_a($order, 'WC_Order')) {
        $order = wc_get_order($order_id);
    }

    $existing = $order->get_meta('order_type');
    if (strtolower($existing) === 'manual') return;

    $is_rest = defined('REST_REQUEST') && REST_REQUEST;
    $is_cli  = php_sapi_name() === 'cli';

    if ($is_rest || $is_cli) {
        $order->update_meta_data('order_type', 'manual');
        $order->save_meta_data();
        error_log("[Order Type] Order #$order_id created via REST or CLI — marked as manual.");
    }
}

/**
 * Sync PayWay order number to HubSpot deal.
 * Call this function manually after creating an order.
 */
function hubwoosync_sync_payway_order_number_to_hubspot($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $payway_order_number = $order->get_meta('_payway_api_order_number');
    if (!$payway_order_number) {
        error_log("[HUBSPOT] ❌ No PayWay order number found for Order #{$order_id}");
        return;
    }

    $deal_id = $order->get_meta('hubspot_deal_id');
    if (!$deal_id || !is_numeric($deal_id)) {
        error_log("[HUBSPOT] ❌ Invalid or missing deal ID for Order #{$order_id}");
        return;
    }

    $token = manage_hubspot_access_token();
    if (!$token) {
        error_log("[HUBSPOT] ❌ Access token not available");
        return;
    }

    $update_url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}";
    $payload = [
        'properties' => [
            'payway_order_number' => $payway_order_number
        ]
    ];

    $response = wp_remote_request($update_url, [
        'method'  => 'PATCH',
        'headers' => [
            'Authorization' => "Bearer {$token}",
            'Content-Type'  => 'application/json'
        ],
        'body'    => json_encode($payload)
    ]);

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (is_wp_error($response) || !empty($body['status'])) {
        error_log("[HUBSPOT] ❌ Failed to update PayWay number for Deal ID {$deal_id}. Response: " . print_r($body, true));
    } else {
        error_log("[HUBSPOT] ✅ PayWay order number '{$payway_order_number}' synced for Deal ID {$deal_id}");
    }
}
