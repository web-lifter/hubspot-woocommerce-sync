<?php
/**
 * Handles order type assignment and PayWay reference sync to HubSpot.
 */

if (!defined('ABSPATH')) exit;

/**
 * Sets the order_type meta to 'manual' or 'online' during order creation.
 * - Admin / REST / CLI = manual
 * - Frontend checkout = online
 */
add_action('woocommerce_new_order', 'hubwoosync_set_order_type_unified', 20, 2);
function hubwoosync_set_order_type_unified($order_id, $order) {
    if (!is_a($order, 'WC_Order')) {
        $order = wc_get_order($order_id);
    }

    $existing = $order->get_meta('order_type');
    if (strtolower($existing) === 'manual') return;

    $is_admin = is_admin();
    $is_rest = defined('REST_REQUEST') && REST_REQUEST;
    $is_cli = php_sapi_name() === 'cli';

    $order_type = ($is_admin || $is_rest || $is_cli) ? 'manual' : 'online';
    $order->update_meta_data('order_type', $order_type);
    $order->save_meta_data();

    error_log("[Order Type] Order #$order_id marked as {$order_type}.");
}

/**
 * Automatically sync PayWay order number to HubSpot after payment.
 */
add_action('woocommerce_payment_complete', 'hubwoosync_trigger_payway_reference_sync');
function hubwoosync_trigger_payway_reference_sync($order_id) {
    hubwoosync_sync_payway_order_number_to_hubspot($order_id);
}

/**
 * Sync PayWay order number to HubSpot deal if available.
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

// Set manual pipeline/stage meta when a manual order is created
add_action('woocommerce_new_order', 'hubwoosync_set_manual_pipeline_meta', 30, 2);
function hubwoosync_set_manual_pipeline_meta($order_id, $order){
    if (!is_a($order, 'WC_Order')) {
        $order = wc_get_order($order_id);
    }
    if (!is_order_manual($order)) {
        return;
    }

    if (!$order->get_meta('hubspot_pipeline_id')) {
        $pipeline_id = get_option('hubspot_pipeline_manual');
        $labels      = get_hubspot_pipeline_and_stage_labels();
        $status      = $order->get_status();
        $mapping     = get_option('hubspot-manual-mapping', []);
        $stage_id    = $mapping[$status] ?? hubspot_get_cached_first_stage_of_pipeline($pipeline_id);

        $order->update_meta_data('hubspot_pipeline_id', $pipeline_id);
        $order->update_meta_data('hubspot_pipeline', $labels['pipelines'][$pipeline_id] ?? $pipeline_id);
        $order->update_meta_data('hubspot_dealstage_id', $stage_id);
        $order->update_meta_data('hubspot_dealstage', $labels['stages'][$stage_id] ?? $stage_id);
        $order->save_meta_data();
    }
}
