<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX: Import HubSpot Deal into Woo Order
 */
add_action('wp_ajax_import_hubspot_order', 'hubwoo_ajax_import_order');
function hubwoo_ajax_import_order() {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    if (!check_ajax_referer('import_hubspot_order_nonce', 'security', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
    }

    $deal_id = sanitize_text_field($_POST['deal_id'] ?? '');
    if (!$deal_id) {
        wp_send_json_error(['message' => 'Missing HubSpot Deal ID']);
    }

    $deal = fetch_hubspot_deal($deal_id);
    if (!$deal) {
        hubwoo_log("❌ Failed to fetch HubSpot deal ID: {$deal_id}");
        wp_send_json_error(['message' => 'HubSpot deal not found or API error']);
    }

    hubwoo_log("🔄 Importing HubSpot deal {$deal_id}");

    $token = manage_hubspot_access_token();
    $pipeline_id = $deal['pipeline'] ?? '';
    $stage_id    = $deal['dealstage'] ?? '';
    $pipeline_label = '';
    $stage_label = '';

    // Get label + stage label
    if ($pipeline_id) {
        $response = wp_remote_get("https://api.hubapi.com/crm/v3/pipelines/deals/{$pipeline_id}", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json',
            ]
        ]);
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $pipeline_label = $body['label'] ?? '';
            if (!empty($body['stages'])) {
                foreach ($body['stages'] as $s) {
                    if ($s['id'] === $stage_id) {
                        $stage_label = $s['label'];
                        break;
                    }
                }
            }
        }
    }

    // Check for existing
    $existing = wc_get_orders([
        'meta_key'   => 'hubspot_deal_id',
        'meta_value' => $deal_id,
        'limit'      => 1,
        'return'     => 'ids'
    ]);

    if ($existing) {
        $order = wc_get_order($existing[0]);
        $is_update = true;
        foreach ($order->get_items() as $id => $item) $order->remove_item($id);
        foreach ($order->get_items('shipping') as $id => $item) $order->remove_item($id);
        foreach ($order->get_items('fee') as $id => $item) $order->remove_item($id);
    } else {
        $order = wc_create_order();
        $is_update = false;
    }

    // Apply deal field mappings
    $deal_map = get_option('hubspot_deal_field_map', []);
    hubwoosync_apply_deal_to_order($deal, $order, $deal_map);

    if (!empty($deal['contacts'])) {
        $contact = fetch_hubspot_contact($deal['contacts'][0]);
        if ($contact) {
            $contact_map = get_option('hubspot_contact_field_map', []);
            hubwoosync_apply_deal_to_order($contact, $order, $contact_map);
        }
    }

    // Shipping mappings may set shipping fields above, but ensure defaults
    if (!empty($deal_map['address_line_1_shipping']) && empty($deal['address_line_1_shipping'])) {
        hubwoo_set_order_field_value($order, $deal_map['address_line_1_shipping'], $deal['address_line_1']);
    }
    if (!empty($deal_map['city_shipping']) && empty($deal['city_shipping'])) {
        hubwoo_set_order_field_value($order, $deal_map['city_shipping'], $deal['city']);
    }
    if (!empty($deal_map['postcode_shipping']) && empty($deal['postcode_shipping'])) {
        hubwoo_set_order_field_value($order, $deal_map['postcode_shipping'], $deal['postcode']);
    }
    if (!empty($deal_map['state_shipping']) && empty($deal['state_shipping'])) {
        hubwoo_set_order_field_value($order, $deal_map['state_shipping'], $deal['state']);
    }
    if (!empty($deal_map['country_region_shipping']) && empty($deal['country_region_shipping'])) {
        hubwoo_set_order_field_value($order, $deal_map['country_region_shipping'], $deal['country_region']);
    }
    if (!empty($deal_map['first_name_shipping']) && empty($deal['first_name_shipping'])) {
        hubwoo_set_order_field_value($order, $deal_map['first_name_shipping'], $order->get_billing_first_name());
    }
    if (!empty($deal_map['last_name_shipping']) && empty($deal['last_name_shipping'])) {
        hubwoo_set_order_field_value($order, $deal_map['last_name_shipping'], $order->get_billing_last_name());
    }
    if (!empty($deal_map['phone_shipping']) && empty($deal['phone_shipping'])) {
        hubwoo_set_order_field_value($order, $deal_map['phone_shipping'], $order->get_billing_phone());
    }

    if (!empty($deal['companies'])) {
        $company = fetch_hubspot_company($deal['companies'][0]);
        if ($company) {
            $company_map = get_option('hubspot_company_field_map', []);
            hubwoosync_apply_deal_to_order($company, $order, $company_map);
        }
    }

    $subtotal = 0;

    // Line items
    $line_item_ids = $deal['line_items'];

    if (empty($line_item_ids)) {
        $line_item_ids = hubwoosync_fetch_line_item_ids_fallback($deal_id);
    }

    if (empty($line_item_ids)) {
        hubwoo_log("[HubSpot] No line items returned for deal {$deal_id}", 'warning');
        $order->add_order_note("❌ No line items imported from HubSpot deal {$deal_id}");
    } else {
        foreach ($line_item_ids as $item_id) {
            $line_item = fetch_hubspot_line_item($item_id);
            if (!$line_item) continue;

        $product_id = wc_get_product_id_by_sku($line_item['sku']);
        $product    = $product_id ? wc_get_product($product_id) : false;

        $item = new WC_Order_Item_Product();
        $item->set_name($line_item['name']);
        $item->set_product_id($product_id ?: 0);
        $item->set_quantity($line_item['quantity']);
        $total = floatval($line_item['price']) * intval($line_item['quantity']);
        $item->set_total($total);
        $item->set_subtotal($total);
        $subtotal += $total;

        if (!$product) {
            $item->add_meta_data('Cost', $line_item['price']);
            $item->add_meta_data('SKU', $line_item['sku']);
        }

        $line_map = get_option('hubspot_line_item_field_map', []);
        foreach ($line_map as $prop => $field) {
            if (isset($line_item[$prop])) {
                hubwoo_set_object_field_value($item, $field, $line_item[$prop]);
            }
        }

        if ($product) {
            $product_map = get_option('hubspot_product_field_map', []);
            foreach ($product_map as $prop => $field) {
                if (isset($line_item[$prop])) {
                    hubwoo_set_object_field_value($product, $field, $line_item[$prop]);
                }
            }
            $product->save();
        }

        $order->add_item($item);
        }
    }

    // Shipping
    $shipping_total = floatval($deal['shipping'] ?? 0);
    if ($shipping_total > 0) {
        $shipping = new WC_Order_Item_Shipping();
        $shipping->set_method_title('Shipping');
        $shipping->set_method_id('flat_rate');
        $shipping->set_total($shipping_total);
        $order->add_item($shipping);
        $order->set_shipping_total($shipping_total);
    }

    // Optional fee
    if (!empty($deal['fees'])) {
        $fee_item = new WC_Order_Item_Fee();
        $fee_item->set_name('Additional Fee');
        $fee_item->set_amount(floatval($deal['fees']));
        $order->add_item($fee_item);
    }

    // Optional discount reconciliation
    $expected_total = floatval($deal['amount_total'] ?? 0);
    $current_total = $subtotal + $shipping_total;
    if ($expected_total > 0 && $current_total > $expected_total) {
        $diff = $current_total - $expected_total;
        $discount = new WC_Order_Item_Fee();
        $discount->set_name('Manual Discount');
        $discount->set_amount(-1 * $diff);
        $discount->set_total(-1 * $diff);
        $order->add_item($discount);
    }

    $order->calculate_totals();

    // Override pipeline and stage to manual settings
    $manual_pipeline_id = get_option('hubspot_pipeline_manual');
    $labels = get_option('hubspot_cached_pipelines', []);
    $manual_stage_map = get_option('hubspot-manual-mapping', []);
    $deal_status = $order->get_status();

    $manual_stage_id = $manual_stage_map[$deal_status] ?? hubspot_get_cached_first_stage_of_pipeline($manual_pipeline_id);
    $manual_stage_label = $labels[$manual_pipeline_id]['stages'][$manual_stage_id] ?? $manual_stage_id;

    $order->update_meta_data('hubspot_pipeline_id', $manual_pipeline_id);
    $order->update_meta_data('hubspot_pipeline', $labels[$manual_pipeline_id]['label'] ?? $manual_pipeline_id);
    $order->update_meta_data('hubspot_dealstage_id', $manual_stage_id);
    $order->update_meta_data('hubspot_dealstage', $manual_stage_label);
    $order->update_meta_data('order_type', 'manual');

    $order->update_meta_data('hubspot_deal_id', $deal_id);
    $order->save_meta_data();



    if (!empty($deal['deal_notes'])) {
        $order->set_customer_note($deal['deal_notes']);
    }

    $order->save();

    // Sync HubSpot Deal
    $status    = $order->get_status();
    $status_key = 'manual_wc-' . $status;
    $mapping   = get_option('hubspot-manual-mapping', []);
    $new_stage = $mapping[$status] ?? null;

    $payload = [
        'properties' => [
            'online_order_id' => (string) $order->get_order_number()
        ]
    ];
    if ($new_stage) {
        $payload['properties']['dealstage'] = $new_stage;
    }

    $res = wp_remote_request("https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}", [
        'method' => 'PATCH',
        'headers' => [
            'Authorization' => "Bearer {$token}",
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode($payload)
    ]);
    if (is_wp_error($res)) {
        hubwoo_log("❌ Failed to PATCH HubSpot deal ID {$deal_id}: " . $res->get_error_message());
    }

    $order->add_order_note(sprintf('Imported from HubSpot. Pipeline: %s | Stage: %s', $order->get_meta('hubspot_pipeline'), $order->get_meta('hubspot_dealstage')));

    wp_send_json_success([
        'redirect_url' => admin_url("post.php?post={$order->get_id()}&action=edit"),
        'message' => $is_update ? 'Order updated from HubSpot.' : 'New order created from HubSpot.'
    ]);
}


/**
 * Tag manually created orders
 */
add_action('woocommerce_new_order', function ($order_id) {
    $order = wc_get_order($order_id);
    if (is_admin() && !wp_doing_ajax()) {
        $order->set_created_via('admin');
        $order->save();
    }
});
