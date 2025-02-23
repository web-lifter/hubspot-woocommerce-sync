<?php

add_action('rest_api_init', function () {
    register_rest_route('hubspot/v1', '/delete-order', [
        'methods'  => 'DELETE',
        'callback' => 'delete_order_handler_woocommerce',
        'permission_callback' => '__return_true',
    ]);
});

function delete_order_handler_woocommerce(WP_REST_Request $request) {
    $log_file = WP_CONTENT_DIR . '/crmcard.log';
    error_log("[DEBUG] 🔍 Received Delete Order Request", 3, $log_file);

    // Decode request body
    $raw_body = $request->get_body();
    error_log("[DEBUG] 🔍 Raw Request Body: " . print_r($raw_body, true), 3, $log_file);

    // Detect content type
    $content_type = $request->get_header('Content-Type');
    error_log("[DEBUG] 🔍 Content-Type: " . $content_type, 3, $log_file);

    // Handle Different Content Types
    if (strpos($content_type, 'application/json') !== false) {
        $params = json_decode($raw_body, true);
    } elseif (strpos($content_type, 'application/x-www-form-urlencoded') !== false) {
        parse_str($raw_body, $params);
    } else {
        error_log("[ERROR] ❌ Unsupported Content-Type: " . $content_type, 3, $log_file);
        return new WP_REST_Response(['error' => 'Unsupported Content-Type'], 415);
    }

    // Extract deal ID
    $deal_id = $params['associatedObjectId'] ?? null;

    if (!$deal_id) {
        error_log("[ERROR] ❌ Missing deal ID", 3, $log_file);
        return new WP_REST_Response(['error' => 'Missing deal ID'], 400);
    }

    error_log("[DEBUG] ✅ Deal ID Received: " . $deal_id, 3, $log_file);

    // Fetch Order ID from WooCommerce using the HubSpot deal ID
    $query = new WC_Order_Query([
        'limit'        => 1,
        'meta_key'     => 'hubspot_deal_id',
        'meta_value'   => $deal_id,
        'meta_compare' => '='
    ]);
    $orders = $query->get_orders();
    $order = !empty($orders) ? reset($orders) : null;

    if (!$order) {
        error_log("[ERROR] ❌ No order found for HubSpot Deal ID: $deal_id", 3, $log_file);
        return new WP_REST_Response(['error' => 'Order not found'], 404);
    }

    $order_id = $order->get_id();
    error_log("[DEBUG] ✅ Found Order ID: $order_id for Deal ID: $deal_id", 3, $log_file);

    // Delete Order and Metadata
    try {
        global $wpdb;

        // Delete all meta data related to the order
        $wpdb->delete("{$wpdb->prefix}postmeta", ['post_id' => $order_id]);
        error_log("[DEBUG] ✅ Deleted metadata for Order #{$order_id}", 3, $log_file);

        // Permanently delete order from WooCommerce
        wp_delete_post($order_id, true);
        error_log("[SUCCESS] ✅ Order #{$order_id} deleted successfully", 3, $log_file);

        return new WP_REST_Response(['orderId' => $order_id, 'status' => 'deleted'], 200);
    } catch (Exception $e) {
        error_log("[ERROR] ❌ Order Deletion Failed: " . $e->getMessage(), 3, $log_file);
        return new WP_REST_Response(['error' => 'Order deletion failed'], 500);
    }
}

?>
