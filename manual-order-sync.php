        hubwoo_log("[HUBSPOT] ❌ Access token not available", 'error');
        hubwoo_log("[HUBSPOT] ❌ Failed to update PayWay number for Deal ID {$deal_id}. Response: " . $error_message, 'error');
        hubwoo_log("[HUBSPOT] ✅ PayWay order number '{$payway_order_number}' synced for Deal ID {$deal_id}");
        hubwoo_log("[HUBSPOT] ❌ No PayWay order number found for Order #{$order_id}", 'error');
        hubwoo_log("[HUBSPOT] ❌ Invalid or missing deal ID for Order #{$order_id}", 'error');
        hubwoo_log("[HUBSPOT] ❌ Access token not available", 'error');
        hubwoo_log("[HUBSPOT] ❌ Failed to update PayWay number for Deal ID {$deal_id}. Response: " . print_r($body, true), 'error');
        hubwoo_log("[HUBSPOT] ✅ PayWay order number '{$payway_order_number}' synced for Deal ID {$deal_id}");
        hubwoo_log("[Order Type] Order #$order_id created via REST or CLI — marked as manual.");
        $order->add_order_note('❌ HubSpot sync failed: ' . $error_message);
        return;
    }
    $response = wp_remote_request($update_url, [
        'method' => 'PATCH',
        'headers' => [
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($payload)
    ]);
        hubwoo_log("[HUBSPOT] ❌ Failed to update PayWay number for Deal ID {$deal_id}. Response: " . $error_message, 'error');
        hubwoo_log("[HUBSPOT] ✅ PayWay order number '{$payway_order_number}' synced for Deal ID {$deal_id}", 'error');
        hubwoo_log("[HUBSPOT] ❌ No PayWay order number found for Order #{$order_id}", 'error');
        hubwoo_log("[HUBSPOT] ❌ Invalid or missing deal ID for Order #{$order_id}", 'error');
        hubwoo_log("[HUBSPOT] ❌ Access token not available", 'error');
        hubwoo_log("[Order Type] Order #$order_id created in admin — marked as manual.", 'error');
        hubwoo_log("[HUBSPOT] ❌ Failed to update PayWay number for Deal ID {$deal_id}. Response: " . print_r($body, true), 'error');
        hubwoo_log("[HUBSPOT] ✅ PayWay order number '{$payway_order_number}' synced for Deal ID {$deal_id}", 'error');
        hubwoo_log("[Order Type] Order #$order_id created via REST or CLI — marked as manual.", 'error');
        error_log("[HUBSPOT] ✅ PayWay order number '{$payway_order_number}' synced for Deal ID {$deal_id}");
    }
    }

    // Get PayWay order number
    $payway_order_number = $order->get_meta('_payway_api_order_number');
    if (!$payway_order_number) {
        error_log("[HUBSPOT] ❌ No PayWay order number found for Order #{$order_id}");
        return;
    }

    // Get HubSpot deal ID
add_action('woocommerce_new_order', 'hubwoosync_set_manual_order_type_for_rest_api', 30, 2);
function hubwoosync_set_manual_order_type_for_rest_api($order_id, $order) {
    if (!$deal_id || !is_numeric($deal_id)) {
        error_log("[HUBSPOT] ❌ Invalid or missing deal ID for Order #{$order_id}");
        return;
    }

    // Get token
    $token = manage_hubspot_access_token();
    if (!$token) {
        error_log("[HUBSPOT] ❌ Access token not available");
        return;
    }
add_action('woocommerce_new_order', 'set_manual_order_type_for_admin', 5, 2);

function set_manual_order_type_for_admin($order_id, $order) {
    if (!is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || php_sapi_name() === 'cli') {
        return;
    }

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

    // Send update to HubSpot
    $update_url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}";
    $payload = [
        'properties' => [
            'payway_order_number' => $payway_order_number
        ]
    ];

    $response = wp_remote_request($update_url, [
        'method' => 'PATCH',
        'headers' => [
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($payload)
    ]);

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (is_wp_error($response) || !empty($body['status'])) {
        error_log("[HUBSPOT] ❌ Failed to update PayWay number for Deal ID {$deal_id}. Response: " . print_r($body, true));
    } else {
        error_log("[HUBSPOT] ✅ PayWay order number '{$payway_order_number}' synced for Deal ID {$deal_id}");
    }
}

add_action('woocommerce_new_order', 'set_manual_order_type_for_rest_api', 30, 2);

function set_manual_order_type_for_rest_api($order_id, $order) {
    if (!is_a($order, 'WC_Order')) {
        $order = wc_get_order($order_id);
    }

    // Check if already set to manual — do not override
    $existing = $order->get_meta('order_type');
    if (strtolower($existing) === 'manual') {
        return;
    }

    // Detect REST API or CLI
    $is_rest = defined('REST_REQUEST') && REST_REQUEST;
    $is_cli  = php_sapi_name() === 'cli';

    // Zapier typically uses REST API to create orders
    if ($is_rest || $is_cli) {
        $order->update_meta_data('order_type', 'manual');
        $order->save_meta_data();

        error_log("[Order Type] Order #$order_id created via REST or CLI — marked as manual.");
    }
}
