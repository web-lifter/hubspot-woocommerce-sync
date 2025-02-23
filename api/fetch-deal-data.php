<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('rest_api_init', function () {
    register_rest_route('hubspot/v1', '/fetch-deal', [
        'methods'  => 'GET',
        'callback' => 'fetch_deal_from_woocommerce',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Fetch WooCommerce Order associated with a HubSpot Deal.
 */
function fetch_deal_from_woocommerce(WP_REST_Request $request) {
    $log_file = WP_CONTENT_DIR . '/fetchdeal.log';

    // Get Deal ID from request
    $deal_id = $request->get_param('dealId');

    if (!$deal_id) {
        error_log("[ERROR] Missing deal ID", 3, $log_file);
        return new WP_REST_Response(['error' => 'Missing deal ID'], 400);
    }

    error_log("[DEBUG] Fetching deal for ID: $deal_id", 3, $log_file);

    $store_url = get_site_url();
    $order_details = get_woocommerce_order_by_deal_id($deal_id);
    $hubspot_deal = HubSpot_WC_API::get_deal($store_url, $deal_id);

    if (!$hubspot_deal) {
        error_log("[ERROR] HubSpot deal not found", 3, $log_file);
        return new WP_REST_Response(['error' => 'HubSpot deal not found'], 404);
    }

    $sync_status = determine_sync_status($order_details, $hubspot_deal);

    $response_data = [
        'dealId'             => $deal_id,
        'dealAmount'         => $hubspot_deal['amount'] ?? 0,
        'freight'            => $hubspot_deal['freight'] ?? 0,
        'associatedContacts' => $hubspot_deal['associatedContacts'] ?? [],
        'associatedCompanies'=> $hubspot_deal['associatedCompanies'] ?? [],
        'associatedLineItems'=> $hubspot_deal['associatedLineItems'] ?? [],
        'syncStatus'         => $sync_status,
        'woocommerceOrder'   => $order_details ?: []
    ];

    error_log("[DEBUG] Response Data: " . print_r($response_data, true), 3, $log_file);

    return new WP_REST_Response($response_data, 200);
}

/**
 * Get WooCommerce Order linked to a HubSpot deal.
 */
function get_woocommerce_order_by_deal_id($deal_id) {
    $log_file = WP_CONTENT_DIR . '/fetchdeal.log';

    $args = [
        'meta_key'   => 'hubspot_deal_id',
        'meta_value' => $deal_id,
        'post_type'  => 'shop_order',
        'post_status'=> 'any',
        'numberposts'=> 1
    ];

    $orders = get_posts($args);

    if (empty($orders)) {
        error_log("[DEBUG] No order found for deal ID: $deal_id", 3, $log_file);
        return false;
    }

    $order_id = $orders[0]->ID;
    $order = wc_get_order($order_id);

    if (!$order) {
        error_log("[ERROR] Order could not be loaded for deal ID: $deal_id", 3, $log_file);
        return false;
    }

    return [
        'orderId'   => $order_id,
        'status'    => $order->get_status(),
        'total'     => $order->get_total(),
        'lastSync'  => get_post_meta($order_id, 'hubspot_last_sync', true) ?: 'Never',
        'contactId' => get_post_meta($order_id, 'hubspot_contact_id', true) ?: null,
    ];
}

/**
 * Determine if WooCommerce order is in sync with HubSpot deal.
 */
function determine_sync_status($order_details, $hubspot_deal) {
    if (!$order_details) {
        return 'Not Created'; // No WooCommerce order exists
    }

    if ($order_details['total'] != ($hubspot_deal['amount'] ?? 0)) {
        return 'Needs Update';
    }

    return 'Created'; // Order exists and matches the deal
}

?>
