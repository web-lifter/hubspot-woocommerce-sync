<?php

add_action('rest_api_init', function () {
    register_rest_route('hubspot/v1', '/update-order', [
        'methods'  => 'POST',
        'callback' => 'update_order_handler_woocommerce',
        'permission_callback' => '__return_true',
    ]);
});

function update_order_handler_woocommerce(WP_REST_Request $request) {
    $log_file = WP_CONTENT_DIR . '/crmcard.log';
    error_log("[DEBUG] 🔍 Received Order Update Request from Weblifter", 3, $log_file);

    // Decode request body
    $params = json_decode($request->get_body(), true);
    if (!$params) {
        error_log("[ERROR] ❌ Invalid request body", 3, $log_file);
        return new WP_REST_Response(['error' => 'Invalid request body'], 400);
    }

    $order_id = intval($params['orderId'] ?? 0);
    $deal_id = $params['dealId'] ?? null;

    if (!$order_id || !$deal_id) {
        error_log("[ERROR] ❌ Missing order ID or deal ID", 3, $log_file);
        return new WP_REST_Response(['error' => 'Missing order ID or deal ID'], 400);
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        error_log("[ERROR] ❌ Order not found", 3, $log_file);
        return new WP_REST_Response(['error' => 'Order not found'], 404);
    }

    // Fetch updated deal data from HubSpot
    error_log("[DEBUG] 🔍 Fetching updated deal data from HubSpot", 3, $log_file);
    $deal_data = get_hubspot_deal_details($deal_id);

    if (!$deal_data) {
        error_log("[ERROR] ❌ Failed to fetch deal data", 3, $log_file);
        return new WP_REST_Response(['error' => 'Failed to fetch deal data'], 500);
    }

    // Extract Contact & Company Info
    $company = $deal_data['associatedCompanies'][0] ?? [];
    $contact = $deal_data['associatedContacts'][0] ?? [];
    $line_items = $deal_data['associatedLineItems'] ?? [];
    $freight_cost = floatval($deal_data['freight'] ?? 0);

    // Update Billing & Shipping Info
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

    error_log("[DEBUG] 🔹 Updating Billing & Shipping Info", 3, $log_file);

    foreach ($billing_info as $key => $value) {
        $billing_method = "set_billing_$key";
        $shipping_method = "set_shipping_$key";

        if (method_exists($order, $billing_method)) {
            $order->$billing_method($value);
        }

        if (method_exists($order, $shipping_method)) {
            $order->$shipping_method($value);
        }
    }

    // Remove existing line items
    foreach ($order->get_items() as $item_id => $item) {
        $order->remove_item($item_id);
    }

    // Add updated line items
    error_log("[DEBUG] 🔹 Updating Line Items", 3, $log_file);

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

    // Remove existing shipping fees & add new freight cost
    foreach ($order->get_items('shipping') as $item_id => $shipping_item) {
        $order->remove_item($item_id);
    }

    if ($freight_cost > 0) {
        $shipping_item = new WC_Order_Item_Shipping();
        $shipping_item->set_method_title("Freight");
        $shipping_item->set_total($freight_cost);
        $order->add_item($shipping_item);
        error_log("[DEBUG] ✅ Updated Shipping Cost: $freight_cost", 3, $log_file);
    }

    // Apply WooCommerce’s Native Tax & Total Calculation
    $order->calculate_totals();
    $order->save();

    error_log("[SUCCESS] ✅ Order Updated: #{$order_id} | Totals Recalculated", 3, $log_file);

    return new WP_REST_Response(['orderId' => $order_id, 'status' => 'updated'], 200);
}

?>