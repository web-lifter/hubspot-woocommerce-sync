    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }
    $dealstage_id = $deal['dealstage'] ?? '';

    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'send_invoice_email_nonce')) {
        wp_send_json_error('Invalid security token.');
    }
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }
function hubwoo_manual_sync_hubspot_order() {
    check_ajax_referer('manual_sync_hubspot_order_nonce', 'security');
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }

    $pipeline_label = $labels['pipelines'][$pipeline_id] ?? $pipeline_id;    $type = hubwoo_order_type($order);

    $type = hubwoo_order_type($order);
add_action('wp_ajax_send_invoice_email', 'hubwoo_send_invoice_email_ajax');
add_action('wp_ajax_manual_sync_hubspot_order', 'hubwoo_manual_sync_hubspot_order');
function hubwoo_manual_sync_hubspot_order() {
function hubwoo_create_hubspot_deal_manual() {

    $pipeline_label = $labels['pipelines'][$pipeline_id] ?? $pipeline_id;    $type = order_type($order);
    $invoice_stage_id = $type === 'manual'
        ? get_option('hubspot_stage_invoice_sent_manual')
        : get_option('hubspot_stage_invoice_sent_online');

    if ($invoice_stage_id) {
        update_hubspot_deal_stage($order_id, $invoice_stage_id);
    }

    log_email_in_hubspot($order_id, 'invoice');

    // Validate request
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'send_invoice_email_nonce')) {
        wp_send_json_error('Invalid security token.');
    }

    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $order = wc_get_order($order_id);

    if (!$order) {
        wp_send_json_error('Invalid Order ID.');
    }

    $email = $order->get_billing_email();
    if (!$email) {
        wp_send_json_error('Customer email not found.');
    }

    // Trigger WooCommerce invoice email
    WC()->mailer()->emails['WC_Email_Customer_Invoice']->trigger($order_id);

    // Update invoice status in meta
    $order->update_meta_data('invoice_status', 'Invoice Sent');
    $order->save();

    $type = order_type($order);
    $invoice_stage_id = $type === 'manual'
        ? get_option('hubspot_stage_invoice_sent_manual')
        : get_option('hubspot_stage_invoice_sent_online');

    if ($invoice_stage_id) {
        log_email_in_hubspot($order_id, 'invoice', $invoice_stage_id);
    } else {
        log_email_in_hubspot($order_id, 'invoice');
    }

    // Log locally
    log_email_activity($order_id, 'invoice', $email, 'Success');

    wp_send_json_success('Invoice sent successfully.');
}
add_action('wp_ajax_send_invoice_email', 'send_invoice_email_ajax');



add_action('wp_ajax_manual_sync_hubspot_order', 'manual_sync_hubspot_order');
function manual_sync_hubspot_order() {
    check_ajax_referer('manual_sync_hubspot_order_nonce', 'security');

    $order_id = absint($_POST['order_id']);
    $order = wc_get_order($order_id);
    if (!$order) wp_send_json_error('Invalid Order ID.');

    $deal_id = $order->get_meta('hubspot_deal_id');
    if (!$deal_id) wp_send_json_error('Order not linked to a HubSpot deal.');

    $deal = fetch_hubspot_deal($deal_id);
    if (!$deal) wp_send_json_error('Failed to fetch deal from HubSpot.');

    // ✅ Get cached labels
    $labels = get_hubspot_pipeline_and_stage_labels();
    $pipeline_id = $deal['pipeline'] ?? '';
    $dealstage_id = $deal['dealstage'] ?? '';add_action('wp_ajax_create_hubspot_deal_manual', 'hubwoo_create_hubspot_deal_manual');
function hubwoo_create_hubspot_deal_manual() {

    $dealstage_label = $labels['stages'][$dealstage_id] ?? $dealstage_id;

    // 🔄 Clear existing line and shipping items
function create_hubspot_deal_manual() {
    check_ajax_referer('create_hubspot_deal_nonce', 'security');
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }
    foreach ($order->get_items('shipping') as $id => $item) $order->remove_item($id);
add_action('wp_ajax_create_hubspot_deal_manual', 'hubwoo_create_hubspot_deal_manual');
function hubwoo_create_hubspot_deal_manual() {
    // 📦 Billing
    $order->set_billing_address_1($deal['address_line_1']);
    $order->set_billing_city($deal['city']);
    $order->set_billing_postcode($deal['postcode']);
    $order->set_billing_state($deal['state']);
    $order->set_billing_country($deal['country_region']);

    // ✍️ Contact
    $status = $order->get_status();
    $status_key = "manual_wc-{$status}";

        $contact = fetch_hubspot_contact($deal['contacts'][0]);
    $pipeline_id = get_option('hubspot_pipeline_manual');
    $status     = $order->get_status();
    $status_key = "manual_wc-{$status}";
    $mapping    = get_option('hubspot_status_stage_mapping', []);
    $deal_stage = $mapping[$status_key] ?? hubspot_get_first_stage_of_pipeline($pipeline_id, $access_token);
            $order->set_billing_last_name($contact['lastname'] ?? '');
            $order->set_billing_email($contact['email'] ?? '');
            $order->set_billing_phone($contact['phone'] ?? '');
        }
    }

    // 🚚 Shipping (fallbacks to billing)
    $order->set_shipping_address_1($deal['address_line_1_shipping'] ?: $deal['address_line_1']);
    $order->set_shipping_city($deal['city_shipping'] ?: $deal['city']);
    $order->set_shipping_postcode($deal['postcode_shipping'] ?: $deal['postcode']);
    $order->set_shipping_state($deal['state_shipping'] ?: $deal['state']);
    $order->set_shipping_country($deal['country_region_shipping'] ?: $deal['country_region']);
    $order->set_shipping_first_name($deal['first_name_shipping'] ?: $order->get_billing_first_name());
    $order->set_shipping_last_name($deal['last_name_shipping'] ?: $order->get_billing_last_name());
    $order->update_meta_data('_shipping_phone', $deal['phone_shipping'] ?: $order->get_billing_phone());

    // 🏢 Company
    if (!empty($deal['companies'])) {
        $company = fetch_hubspot_company($deal['companies'][0]);
        if ($company) {}
            $order->set_billing_company($company['name'] ?? '');
        }
    }

    // 🛒 Line Items
    foreach ($deal['line_items'] as $item_id) {
        $line_item = fetch_hubspot_line_item($item_id);
        if (!$line_item) continue;

        $product_id = wc_get_product_id_by_sku($line_item['sku']);
        $item = new WC_Order_Item_Product();
        $item->set_name($line_item['name']);
        $item->set_product_id($product_id ?: 0);
        $item->set_quantity($line_item['quantity']);
        $total = $line_item['price'] * $line_item['quantity'];
        $item->set_total($total);
        $item->set_subtotal($total);
        $item->add_meta_data('Cost', $line_item['price']);
        $item->add_meta_data('SKU', $line_item['sku']);
        $order->add_item($item);
    }

    // 🚚 Shipping item
    if (!empty($deal['shipping'])) {
        $shipping = new WC_Order_Item_Shipping();
        $shipping->set_method_title('Shipping');
        $shipping->set_method_id('flat_rate');
        $shipping->set_total(floatval($deal['shipping']));
        $order->add_item($shipping);
        $order->set_shipping_total(floatval($deal['shipping']));
    }

    $order->calculate_totals();

    // 🧠 Save all metadata
    $order->update_meta_data('hubspot_pipeline_id', $pipeline_id);
    $order->update_meta_data('hubspot_pipeline', $pipeline_label);
    $order->update_meta_data('hubspot_dealstage_id', $dealstage_id);
    $order->update_meta_data('hubspot_dealstage', $dealstage_label);
    $order->update_meta_data('hubspot_imported', '1');
    $order->update_meta_data('order_type', 'manual');
    $order->save_meta_data();

    if (!empty($deal['deal_notes'])) {
        $order->set_customer_note($deal['deal_notes']);
    }

    $order->save();
    $order->add_order_note(sprintf('🔄 Fully re-synced with HubSpot. Pipeline: %s | Stage: %s', $pipeline_label, $dealstage_label));

    wp_send_json_success('Order updated with latest HubSpot deal info.');
}




add_action('wp_ajax_create_hubspot_deal_manual', 'create_hubspot_deal_manual');
function create_hubspot_deal_manual() {
    check_ajax_referer('create_hubspot_deal_nonce', 'security');

    $order_id = absint($_POST['order_id']);
    $order = wc_get_order($order_id);
    if (!$order) wp_send_json_error('Invalid Order ID.');

    // Prevent duplicate sync
    if ($order->get_meta('hubspot_deal_id')) {
        wp_send_json_error('Order already has a HubSpot deal.');
    }

    $access_token = manage_hubspot_access_token();
    if (!$access_token) {
        wp_send_json_error('No HubSpot access token available.');
    }

    // Step 1: Get or create contact
    $email = $order->get_billing_email();
    $contact_id = hubspot_get_or_create_contact($order, $email, $access_token);
    if (!$contact_id) {
        hubspot_log("[ERROR] Contact creation failed for order #{$order_id}");
        wp_send_json_error('Contact creation failed.');
    }

    // Step 2: Get or create company (optional)
    $company_id = null;
    $company_name = $order->get_billing_company();
    if (!empty($company_name)) {
        $company_id = hubspot_get_or_create_company($company_name, $access_token);
    }

    // Step 3: Determine pipeline and deal stage
    $pipeline_id = get_option('hubspot_pipeline_manual');
    $status_key = "online_wc-processing";
    $mapping = get_option('hubspot_status_stage_mapping', []);
    $deal_stage = $mapping[$status_key] ?? hubspot_get_first_stage_of_pipeline($pipeline_id, $access_token);

    $labels = get_hubspot_pipeline_and_stage_labels();
    $pipeline_label = $labels['pipelines'][$pipeline_id] ?? $pipeline_id;
    $dealstage_label = $labels['stages'][$deal_stage] ?? $deal_stage;

    hubspot_log("[DEBUG] Creating deal for order #{$order_id} using pipeline '{$pipeline_id}' and stage '{$deal_stage}'");

    // Step 4: Create the deal
    $deal_id = hubspot_create_deal_from_order($order, $pipeline_id, $deal_stage, $contact_id, $access_token);
    if (!$deal_id) {
        hubspot_log("[ERROR] Deal creation failed for order #{$order_id}");
        wp_send_json_error('Deal creation failed. Check hubspot-sync.log for details.');
    }
    hubspot_log("[SUCCESS] Deal #{$deal_id} created for order #{$order_id}");

    // Step 5: Associate contact and company
    hubspot_associate_objects('deal', $deal_id, 'contact', $contact_id, $access_token);
    if ($company_id) {
        hubspot_associate_objects('deal', $deal_id, 'company', $company_id, $access_token);
    }

    // Step 6: Add line items
    hubspot_add_line_items_to_deal($order, $deal_id, $access_token);

    $deal_id = (string) $deal_id;

    $order->update_meta_data('hubspot_deal_id', $deal_id);
    $order->update_meta_data('hubspot_pipeline_id', $pipeline_id);
    $order->update_meta_data('hubspot_pipeline', $pipeline_label);
    $order->update_meta_data('hubspot_dealstage_id', $deal_stage);
    $order->update_meta_data('hubspot_dealstage', $dealstage_label);
    $order->add_order_note(sprintf('✅ HubSpot deal created manually. Pipeline: %s | Stage: %s | Deal ID: %s', $pipeline_label, $dealstage_label, $deal_id));
    $order->save();

    wc_delete_order_transients($order);
    hubspot_log("[DEBUG] Saved metadata and flushed transients for order #{$order_id}");

    wp_send_json_success('HubSpot deal created successfully.');
}