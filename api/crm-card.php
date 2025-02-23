<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('rest_api_init', function () {
    register_rest_route('hubspot/v1', '/crm-card', [
        'methods'  => ['GET', 'POST'],
        'callback' => 'crm_card_handler_woocommerce',
        'permission_callback' => '__return_true',
    ]);
});

function crm_card_handler_woocommerce(WP_REST_Request $request) {
    $log_file = WP_CONTENT_DIR . '/crmcard.log';
    error_log("[WooCommerce CRM Card] 🔍 Received request: " . json_encode($request->get_params(), JSON_PRETTY_PRINT), 3, $log_file);

    // Extract Parameters
    $params = json_decode($request->get_body(), true);
    $deal_id = $params['objectId'] ?? null;
    $portal_id = $params['portalId'] ?? null;

    if (!$deal_id || !$portal_id) {
        error_log("[WooCommerce CRM Card] ❌ Missing deal ID or portal ID", 3, $log_file);
        return new WP_REST_Response(['error' => 'Missing deal ID or portal ID'], 400);
    }

    // Fetch Deal & WooCommerce Order Data
    $deal_data = fetch_deal_from_woocommerce($deal_id);

    if (!isset($deal_data['woocommerceOrder'])) {
        error_log("[WooCommerce CRM Card] ❌ Failed to fetch deal data", 3, $log_file);
        return new WP_REST_Response(['error' => 'Failed to fetch deal data'], 500);
    }

    // Extract WooCommerce Order Details
    $order = $deal_data['woocommerceOrder'] ?? [];
    $order_status = $order['status'] ?? 'Not Created';
    $order_id = $order['orderId'] ?? null;
    $order_amount = $order['total'] ?? $deal_data['dealAmount'] ?? 0;

    // Determine Order Status Type for UI display
    $status_mapping = [
        "completed" => "SUCCESS",
        "processing" => "INFO",
        "on-hold" => "WARNING",
        "pending" => "WARNING",
        "awaiting-payment" => "WARNING",
        "refunded" => "DANGER",
        "cancelled" => "DANGER",
        "failed" => "DANGER",
        "Not Created" => "DEFAULT"
    ];
    $status_type = $status_mapping[strtolower($order_status)] ?? "DEFAULT";

    // Define CRM Card Properties
    $properties = [
        [
            "label" => "Order Status",
            "dataType" => "STATUS",
            "value" => ucwords($order_status),
            "optionType" => $status_type
        ],
        [
            "label" => "Order Amount",
            "dataType" => "CURRENCY",
            "value" => $order_amount,
            "currencyCode" => "AUD"
        ],
        [
            "label" => "WooCommerce Order",
            "dataType" => "LINK",
            "value" => $order_id ? get_site_url() . "/wp-admin/post.php?post={$order_id}&action=edit" : "",
            "linkLabel" => $order_id ? "View Order #{$order_id}" : "No Order Found"
        ]
    ];

    // Define CRM Card Actions
    $actions = [];
    $plugin_base_url = get_site_url() . "/wp-json/hubspot/v1";

    if ($order_status === "Not Created") {
        $actions[] = [
            "type" => "ACTION_HOOK",
            "httpMethod" => "POST",
            "label" => "Create Order",
            "uri" => "{$plugin_base_url}/create-order"
        ];
    } elseif (!empty($order)) {
        $actions[] = [
            "type" => "ACTION_HOOK",
            "httpMethod" => "POST",
            "label" => "Update Order",
            "uri" => "{$plugin_base_url}/update-order"
        ];
        $actions[] = [
            "type" => "CONFIRMATION_ACTION_HOOK",
            "httpMethod" => "DELETE",
            "label" => "Delete Order",
            "uri" => "{$plugin_base_url}/delete-order",
            "confirmationMessage" => "Are you sure you want to delete this order?",
            "confirmButtonText" => "Yes",
            "cancelButtonText" => "No",
            "headers" => ["Content-Type" => "application/json"],
            "requestBodyTemplate" => ["associatedObjectId" => "{{objectId}}", "portalId" => "{{portalId}}"]
        ];
    }

    // Construct Final Response
    $response_data = [
        "results" => [[
            "objectId" => (string) $deal_id,
            "title" => "WooCommerce Order Status",
            "properties" => $properties,
            "actions" => $actions
        ]]
    ];

    error_log("[WooCommerce CRM Card] ✅ Final Response: " . json_encode($response_data, JSON_PRETTY_PRINT), 3, $log_file);
    return new WP_REST_Response($response_data, 200);
}

?>
