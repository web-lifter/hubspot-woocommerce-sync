add_action('woocommerce_new_order', 'hubwoosync_set_order_type_for_online_orders', 20, 2);
add_action('woocommerce_new_order', 'hubwoosync_set_order_type_for_online_orders', 20, 2);
function hubwoosync_set_order_type_for_online_orders($order_id, $order) {

add_action('woocommerce_new_order', 'hubwoosync_set_order_type_for_online_orders', 20, 2);
add_action('woocommerce_payment_complete', 'hubwoosync_auto_sync_online_order', 10, 1);
add_action('woocommerce_new_order', 'hubwoosync_set_order_type_for_online_orders', 20, 2);
add_action('woocommerce_payment_complete', 'hubwoosync_auto_sync_online_order', 10, 1);
function hubwoosync_set_order_type_for_online_orders($order_id, $order) {

if (!defined('ABSPATH')) exit;

/*
*
    // Detect if order was created from admin, REST API, or CLI
    if (is_admin() || defined('REST_REQUEST') || (php_sapi_name() === 'cli')) {
        if (strtolower($existing_order_type) !== 'manual') {
            $order->update_meta_data('order_type', 'manual');
            $order->save_meta_data();
        }
        return;
    }

    // Skip if already marked as 'manual'
    if (strtolower($existing_order_type) === 'manual') {
        return;
    }

add_action('woocommerce_new_order', 'set_order_type_for_online_orders', 20, 2);
    // Detect if order was created from admin, CLI, or API (e.g. Zapier)
    if (is_admin() || defined('REST_REQUEST') || (php_sapi_name() === 'cli')) {
        if (strtolower($existing_order_type) !== 'manual') {
            $order->update_meta_data('order_type', 'manual');
            $order->save_meta_data();
        }
        return;
    }
    if (!is_a($order, 'WC_Order')) {
        $order = wc_get_order($order_id);
    }

    $existing_order_type = $order->get_meta('order_type');

    if (is_admin() || strtolower($existing_order_type) === 'manual') {
        return;
    }
 * Entry point for HubSpot sync after order payment completion
// Trigger deal creation after successful payment
add_action('woocommerce_payment_complete', 'hubspot_auto_sync_online_order', 10, 1);
    $order->update_meta_data('order_type', 'online');
    $order->save_meta_data();
}
*/

add_action('woocommerce_new_order', 'set_order_type_for_online_orders', 20, 2);
// Trigger deal creation after successful payment
add_action('woocommerce_payment_complete', 'hubspot_auto_sync_online_order', 10, 1);

function set_order_type_for_online_orders($order_id, $order) {
    if (!is_a($order, 'WC_Order')) {
        $order = wc_get_order($order_id);
    }

    $existing_order_type = $order->get_meta('order_type');

    // Skip if already marked as 'manual'
    $access_token = manage_hubspot_access_token();
    if (is_wp_error($access_token) || !$access_token) {
        $error_message = is_wp_error($access_token) ? $access_token->get_error_message() : 'Access token not available';
        $order->add_order_note('❌ HubSpot sync failed: ' . $error_message);
        return;
    }
add_action('woocommerce_checkout_order_processed', 'hubwoosync_auto_sync_online_order', 20, 1);
function hubwoosync_auto_sync_online_order($order_id) {
        $error_message = is_wp_error($contact_id) ? $contact_id->get_error_message() : 'Unable to create or fetch contact';
        $order->add_order_note('❌ HubSpot sync failed: ' . $error_message);
        return;
    }
    $pipeline_id = get_option('hubspot_pipeline_online');
    if (!$pipeline_id) {
        $order->add_order_note('❌ HubSpot sync failed: HubSpot pipeline not configured.');
        return;
    }
    $deal_id = hubspot_create_deal_from_order($order, $pipeline_id, $deal_stage, $contact_id, $access_token);
    if (is_wp_error($deal_id) || !$deal_id) {
        $error_message = is_wp_error($deal_id) ? $deal_id->get_error_message() : 'Deal creation failed';
        $order->add_order_note('❌ HubSpot sync failed: ' . $error_message);
        return;
    }
    // Detect if order was created from admin, CLI, or API (e.g. Zapier)
    if (is_admin() || defined('REST_REQUEST') || (php_sapi_name() === 'cli')) {
        return;
    }

    // Only continue if customer ID is set (logged-in customer) or user is guest
    $customer_id = $order->get_customer_id();
    // Create the deal
    $deal_id = hubspot_create_deal_from_order($order, $pipeline_id, $deal_stage, $contact_id, $access_token);
    if (!$deal_id) {
        $order->add_order_note('❌ HubSpot deal creation failed.');
        return;
    }

    // If it's either a guest or a customer (i.e. not system generated), set type to 'online'
    if ($is_guest || $customer_id > 0) {
        $order->update_meta_data('order_type', 'online');
        $order->save_meta_data();
    }
}


add_filter('woocommerce_orders_table_meta_keys', function($keys) {
    $keys[] = 'hubspot_deal_id';
    $keys[] = 'hubspot_pipeline';
    $keys[] = 'hubspot_dealstage';
    $keys[] = 'hubspot_pipeline_id';
    $keys[] = 'hubspot_dealstage_id';
    $keys[] = 'invoice_status';
    $keys[] = 'quote_status';
    $keys[] = 'quote_last_sent';
    $keys[] = 'order_type';
    return $keys;
});

/**
 * Entry point for HubSpot sync after WooCommerce order
 */

add_action('woocommerce_checkout_order_processed', 'hubspot_auto_sync_online_order', 20, 1);

function hubspot_auto_sync_online_order($order_id) {
    $order = wc_get_order($order_id);
    if (!$order || is_order_manual($order)) return;

    // Ensure order_type is tagged
    if (!$order->get_meta('order_type')) {
        $order->update_meta_data('order_type', 'online');
        $order->save_meta_data();
    }

    // Prevent duplicate deal creation
    if ($order->get_meta('hubspot_deal_id')) return;

    $access_token = manage_hubspot_access_token();
    if (!$access_token) return;

    // Create or fetch HubSpot contact
    $email = $order->get_billing_email();
    $contact_id = hubspot_get_or_create_contact($order, $email, $access_token);
    if (!$contact_id) return;

    // Create or fetch HubSpot company (optional)
    $company_id = null;
    $company_name = $order->get_billing_company();
    if (!empty($company_name)) {
        $company_id = hubspot_get_or_create_company($company_name, $access_token);
    }

    // Get pipeline ID from options
    $pipeline_id = get_option('hubspot_pipeline_online');
    if (!$pipeline_id) return;

    // Map WooCommerce status to HubSpot deal stage
    $status = $order->get_status(); // 'pending', 'processing', etc.
    $status_key = "online_wc-{$status}";
    $mapping = get_option('hubspot_status_stage_mapping', []);
    $deal_stage = $mapping[$status_key] ?? '';

    // Fallback to first stage if not mapped
    if (!$deal_stage) {
        $deal_stage = hubspot_get_first_stage_of_pipeline($pipeline_id, $access_token);
    }

    // Create the deal
    $deal_id = hubspot_create_deal_from_order($order, $pipeline_id, $deal_stage, $contact_id, $access_token);
    if (!$deal_id) return;

    // Associate contact and company with the deal
    hubspot_associate_objects('deal', $deal_id, 'contact', $contact_id, $access_token);
    if ($company_id) {
        hubspot_associate_objects('deal', $deal_id, 'company', $company_id, $access_token);
    }

    // Add WooCommerce line items to the HubSpot deal
    hubspot_add_line_items_to_deal($order, $deal_id, $access_token);

    // Get human-readable pipeline/stage labels
    $labels = get_hubspot_pipeline_and_stage_labels();
    $pipeline_label = $labels['pipelines'][$pipeline_id] ?? $pipeline_id;
    $stage_label    = $labels['stages'][$deal_stage] ?? $deal_stage;

    // Store metadata on the order
    $order->update_meta_data('hubspot_deal_id', $deal_id);
    $order->update_meta_data('hubspot_pipeline_id', $pipeline_id);
    $order->update_meta_data('hubspot_pipeline', $pipeline_label);
    $order->update_meta_data('hubspot_dealstage_id', $deal_stage);
    $order->update_meta_data('hubspot_dealstage', $stage_label);
    $order->add_order_note("✔️ HubSpot deal created. Deal ID: {$deal_id}");
    $order->save();
}



/**
 * Add line items with logging
 */
function hubspot_add_line_items_to_deal($order, $deal_id, $access_token) {
    foreach ($order->get_items() as $item) {
        $name = $item->get_name();
        $qty = $item->get_quantity();
        $subtotal = $item->get_subtotal();
        $price = $qty > 0 ? $subtotal / $qty : 0;
        $sku = $item->get_product() ? $item->get_product()->get_sku() : '';
        $gst = round($item->get_total_tax(), 2);

        $line_item_id = hubspot_create_line_item($name, $price, $qty, $sku, $gst, $access_token);

        if ($line_item_id) {
            hubspot_associate_objects('deal', $deal_id, 'line_item', $line_item_id, $access_token);
        }
    }
}

function hubspot_create_line_item($name, $price, $quantity, $sku, $gst, $access_token) {
    $payload = [
        'properties' => [
            'name' => $name,
            'price' => round($price, 2),
            'quantity' => $quantity,
            'sku' => $sku,
            'gst' => $gst
        ]
    ];

    $response = wp_remote_post("https://api.hubapi.com/crm/v3/objects/line_items", [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($payload)
    ]);

    $data = json_decode(wp_remote_retrieve_body($response), true);
    return $data['id'] ?? null;
}