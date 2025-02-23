<?php

add_action('rest_api_init', function () {
    register_rest_route('hubspot/v1', '/create-order', [
        'methods'  => 'POST',
        'callback' => 'create_order_handler_woocommerce',
        'permission_callback' => '__return_true',
    ]);
});

function create_order_handler_woocommerce(WP_REST_Request $request) {
    $log_file = WP_CONTENT_DIR . '/crmcard.log';
    error_log("[DEBUG] WooCommerce Create Order - Start", 3, $log_file);

    // Decode request body
    $params = json_decode($request->get_body(), true);
    error_log("[DEBUG] Received Request: " . json_encode($params, JSON_PRETTY_PRINT), 3, $log_file);

    $deal_id = $params['objectId'] ?? null;

    if (!$deal_id) {
        error_log("[ERROR] Missing deal ID", 3, $log_file);
        return new WP_REST_Response(['error' => 'Missing deal ID'], 400);
    }

    // Fetch HubSpot deal details
    error_log("[DEBUG] Fetching deal data from HubSpot", 3, $log_file);
    $deal_data = get_hubspot_deal_details($deal_id);

    if (!$deal_data) {
        error_log("[ERROR] Failed to fetch deal data from HubSpot", 3, $log_file);
        return new WP_REST_Response(['error' => 'Failed to fetch deal data'], 500);
    }

    // Extract necessary details
    $company = $deal_data['associatedCompanies'][0] ?? [];
    $contact = $deal_data['associatedContacts'][0] ?? [];
    $line_items = $deal_data['associatedLineItems'] ?? [];
    $freight_cost = floatval($deal_data['freight'] ?? 0);

    // Extract billing & shipping info
    $billing_info = [
        'first_name' => $contact['firstName'] ?? '',
        'last_name'  => $contact['lastName'] ?? '',
        'email'      => $contact['email'] ?? '',
        'phone'      => $contact['phone'] ?? '',
        'company'    => $company['name'] ?? '',
        'address_1'  => $contact['address'] ?? '',
        'address_2'  => $contact['address2'] ?? '',
        'city'       => $contact['city'] ?? '',
        'state'      => $contact['state'] ?? '',
        'postcode'   => $contact['zip'] ?? '',
        'country'    => $contact['country'] ?? ''
    ];

    error_log("[DEBUG] Billing Info: " . json_encode($billing_info, JSON_PRETTY_PRINT), 3, $log_file);

    // Create WooCommerce Order
    try {
        error_log("[DEBUG] Creating WooCommerce Order", 3, $log_file);
        $order = wc_create_order();

        // Set Billing & Shipping Details
        foreach ($billing_info as $key => $value) {
            $method = "set_billing_$key";
            if (method_exists($order, $method)) {
                $order->$method($value);
            }
        }

        foreach ($billing_info as $key => $value) {
            $method = "set_shipping_$key";
            if (method_exists($order, $method)) {
                $order->$method($value);
            }
        }

        // Add Line Items
        foreach ($line_items as $line_item) {
            $product_id = get_product_id_by_sku($line_item['sku'] ?? '') ?: 0;
            $quantity = intval($line_item['quantity'] ?? 1);
            $price = floatval($line_item['price'] ?? 0);
            $line_total = $price * $quantity;
            $product_name = $line_item['name'] ?? 'Unknown Product';

            if ($product_id) {
                $product = wc_get_product($product_id);
                $order_item = new WC_Order_Item_Product();
                $order_item->set_product($product);
                $order_item->set_quantity($quantity);
                $order_item->set_subtotal($line_total);
                $order_item->set_total($line_total);
                $order->add_item($order_item);
            } else {
                $custom_item = new WC_Order_Item_Product();
                $custom_item->set_name($product_name);
                $custom_item->set_quantity($quantity);
                $custom_item->set_subtotal($line_total);
                $custom_item->set_total($line_total);
                $custom_item->add_meta_data('manual_entry', true);
                $order->add_item($custom_item);
            }
        }

        // Add Shipping Fee
        if ($freight_cost > 0) {
            $shipping_item = new WC_Order_Item_Shipping();
            $shipping_item->set_method_title("Freight");
            $shipping_item->set_total($freight_cost);
            $order->add_item($shipping_item);
        }

        // Finalize Order
        $order->calculate_totals();
        $order->update_meta_data('hubspot_deal_id', $deal_id);
        $order->save();
        $order_id = $order->get_id();

        error_log("[SUCCESS] Order Created: #{$order_id}", 3, $log_file);
        return new WP_REST_Response(['orderId' => $order_id, 'status' => 'created'], 200);

    } catch (Exception $e) {
        error_log("[ERROR] Order Creation Failed: " . $e->getMessage(), 3, $log_file);
        return new WP_REST_Response(['error' => 'Order creation failed'], 500);
    }
}

function get_product_id_by_sku($sku) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s", $sku));
}

?>
