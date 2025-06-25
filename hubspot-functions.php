function hubwoo_render_combined_order_management_page() {
add_action('wp_ajax_import_hubspot_order', 'hubwoo_import_hubspot_order_ajax');
function hubwoo_import_hubspot_order_ajax() {

add_action('wp_ajax_import_hubspot_order', 'hubwoo_import_hubspot_order_ajax');
function hubwoo_import_hubspot_order_ajax() {
    exit;
}

/*
*
* Render Order Management Page
*
*/

function render_combined_order_management_page() {
    // === Import Form ===
    ?>
    <div class="wrap">
        <h1>HubSpot Order Management</h1>
        <h2>Import Order from HubSpot</h2>
add_action('wp_ajax_import_hubspot_order', 'import_hubspot_order_ajax');
function import_hubspot_order_ajax() {
    check_ajax_referer('import_hubspot_order_nonce', 'security');
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }

            <input type="submit" class="button button-primary" value="Import Order">
        </form>
        <hr>
    <?php

    // === Orders Table ===
    require_once plugin_dir_path(__FILE__) . 'hub-order-management.php';
    render_hubspot_orders_page_table_only(); // Move table rendering logic into this function

    ?>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#hubspot-import-form').on('submit', function(e) {
            e.preventDefault();
            var dealId = $('input[name="hubspot_deal_id"]').val();
            $.post(ajaxurl, {
                action: 'import_hubspot_order',
                deal_id: dealId,
                security: '<?php echo wp_create_nonce("import_hubspot_order_nonce"); ?>'
            }, function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.href = response.data.redirect_url;
                } else {
                    alert('Error: ' + response.data);
                }
            });
        });
    });
    </script>
    <?php
}

/*
*
* Import Manual Order Functions
*
*/

add_action('wp_ajax_import_hubspot_order', 'import_hubspot_order_ajax');
function import_hubspot_order_ajax() {
    check_ajax_referer('import_hubspot_order_nonce', 'security');
    $deal_id = sanitize_text_field($_POST['deal_id'] ?? '');
    if (!$deal_id) wp_send_json_error('Missing HubSpot Deal ID.');

    $deal = fetch_hubspot_deal($deal_id);
    if (!$deal) wp_send_json_error('Failed to fetch deal data.');

    error_log("[IMPORT] Syncing deal ID $deal_id");

    $pipeline_id = $deal['pipeline'] ?? '';
    $stage_id = $deal['dealstage'] ?? '';
    $pipeline_label = '';
    $stage_label = '';
    $token = manage_hubspot_access_token();

    if ($pipeline_id) {
        $pipeline_response = wp_remote_get("https://api.hubapi.com/crm/v3/pipelines/deals/{$pipeline_id}", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json'
            ]
        ]);

        if (!is_wp_error($pipeline_response)) {
            $pipeline_data = json_decode(wp_remote_retrieve_body($pipeline_response), true);
            $pipeline_label = $pipeline_data['label'] ?? '';

            if (isset($pipeline_data['stages']) && is_array($pipeline_data['stages'])) {
                foreach ($pipeline_data['stages'] as $stage) {
                    if (!isset($stage['id'], $stage['label'])) continue;
                    if ($stage['id'] === $stage_id) {
                        $stage_label = $stage['label'];
                        break;
                    }
                }
            } else {
                error_log("[IMPORT] ⚠️ No stages found for pipeline ID {$pipeline_id}.");
            }
        } else {
            error_log("[IMPORT] ❌ Failed to fetch pipeline stages: " . $pipeline_response->get_error_message());
        }
    }

    // Check for existing order
    $existing = wc_get_orders([
        'limit' => 1,
        'meta_key' => 'hubspot_deal_id',
        'meta_value' => $deal_id,
        'return' => 'ids'
    ]);

    if ($existing) {
        $order = wc_get_order($existing[0]);
        $is_update = true;
        foreach ($order->get_items() as $id => $item) $order->remove_item($id);
        foreach ($order->get_items('shipping') as $id => $item) $order->remove_item($id);
    } else {
        $order = wc_create_order();
        $is_update = false;
    }

    // Address and contact setup (same as original)
    $order->set_billing_address_1($deal['address_line_1']);
    $order->set_billing_city($deal['city']);
    $order->set_billing_postcode($deal['postcode']);
    $order->set_billing_state($deal['state']);
    $order->set_billing_country($deal['country_region']);

    if (!empty($deal['contacts'])) {
        $contact = fetch_hubspot_contact($deal['contacts'][0]);
        if ($contact) {
            $order->set_billing_first_name($contact['firstname'] ?? '');
            $order->set_billing_last_name($contact['lastname'] ?? '');
            $order->set_billing_email($contact['email'] ?? '');
            $order->set_billing_phone($contact['phone'] ?? '');
        }
    }

    $order->set_shipping_address_1($deal['address_line_1_shipping'] ?: $deal['address_line_1']);
    $order->set_shipping_city($deal['city_shipping'] ?: $deal['city']);
    $order->set_shipping_postcode($deal['postcode_shipping'] ?: $deal['postcode']);
    $order->set_shipping_state($deal['state_shipping'] ?: $deal['state']);
    $order->set_shipping_country($deal['country_region_shipping'] ?: $deal['country_region']);
    $order->set_shipping_first_name($deal['first_name_shipping'] ?: $order->get_billing_first_name());
    $order->set_shipping_last_name($deal['last_name_shipping'] ?: $order->get_billing_last_name());
    $order->set_meta_data('_shipping_phone', $deal['phone_shipping'] ?: $order->get_billing_phone());

    if (!empty($deal['companies'])) {
        $company = fetch_hubspot_company($deal['companies'][0]);
        if ($company) {
            $order->set_billing_company($company['name'] ?? '');
        }
    }

    foreach ($deal['line_items'] as $item_id) {
        $line_item = fetch_hubspot_line_item($item_id);
        if (!$line_item) continue;

        $product_id = wc_get_product_id_by_sku($line_item['sku']);
        $product = $product_id ? wc_get_product($product_id) : false;

        $item = new WC_Order_Item_Product();
        $item->set_name($line_item['name']);
        $item->set_product_id($product_id ?: 0);
        $item->set_quantity($line_item['quantity']);
        $total = $line_item['price'] * $line_item['quantity'];
        $item->set_total($total);
        $item->set_subtotal($total);

        if (!$product) {
            $item->add_meta_data('Cost', $line_item['price']);
            $item->add_meta_data('SKU', $line_item['sku']);
        }

        $order->add_item($item);
    }

    if (!empty($deal['shipping'])) {
        $shipping = new WC_Order_Item_Shipping();
        $shipping->set_method_title('Shipping');
        $shipping->set_method_id('flat_rate');
        $shipping->set_total(floatval($deal['shipping']));
        $order->add_item($shipping);
        $order->set_shipping_total(floatval($deal['shipping']));
    }

    $order->calculate_totals();

    // Save meta
    $order->update_meta_data('hubspot_deal_id', $deal_id);
    $order->update_meta_data('hubspot_pipeline_id', $pipeline_id);
    $order->update_meta_data('hubspot_pipeline', $pipeline_label);
    $order->update_meta_data('hubspot_dealstage_id', $stage_id);
    $order->update_meta_data('hubspot_dealstage', $stage_label);
    $order->update_meta_data('order_type', 'manual');
    $order->save_meta_data();

    if (!empty($deal['deal_notes'])) {
        $order->set_customer_note($deal['deal_notes']);
    }

    $order->save();

    // Update HubSpot deal with order number
    $order_number = $order->get_order_number();
    $status = $order->get_status(); // e.g. 'pending'
    $status_key = "manual_wc-{$status}";

    $mapping = get_option('hubspot_status_stage_mapping', []);
    $new_stage = $mapping[$status_key] ?? null;

    if ($new_stage) {
        $deal_update_payload = [
            'properties' => [
                'dealstage' => $new_stage,
                'online_order_id' => (string) $order_number
            ]
        ];
        $deal_update_response = wp_remote_request("https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}", [
            'method' => 'PATCH',
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($deal_update_payload)
        ]);

        $update_body = json_decode(wp_remote_retrieve_body($deal_update_response), true);

        if (is_wp_error($deal_update_response) || !empty($update_body['status'])) {
            error_log("[IMPORT] ❌ Failed to update deal stage/order ID for deal #{$deal_id}. Response: " . print_r($update_body, true));
        } else {
            error_log("[IMPORT] ✅ Deal #{$deal_id} updated to stage '{$new_stage}' and order ID '{$order_number}'.");
        }
    } else {
        error_log("[IMPORT] ⚠️ No stage mapping found for order status '{$status_key}'");
    }

    $order->add_order_note(sprintf('Imported from HubSpot. Pipeline: %s | Stage: %s', $pipeline_label ?: $pipeline_id, $stage_label ?: $stage_id));

    wp_send_json_success([
        'redirect_url' => admin_url("post.php?post={$order->get_id()}&action=edit"),
        'message' => $is_update ? 'Order exists — updating existing order.' : 'New order created from HubSpot.'
    ]);
}


// Mark orders created via admin as 'manual' (for sync logic)
add_action('woocommerce_new_order', function($order_id) {
    $order = wc_get_order($order_id);
    if (is_admin() && !wp_doing_ajax()) {
        $order->set_created_via('admin'); // Use official API instead of raw meta
        $order->save();
    }
});

