<?php
/**
 * Manual HubSpot Sync Actions
 *
 * @package Steelmark
 */

if (!defined('ABSPATH')) exit;

/**
 * Send quote email and update HubSpot deal stage.
 */
add_action('wp_ajax_send_quote_email', 'hubwoosync_send_quote_email');
function hubwoosync_send_quote_email() {
    check_ajax_referer('send_quote_email_nonce', 'security');

    if (! current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized', 403);
    }

    $order_id = absint($_POST['order_id']);
    $order    = wc_get_order($order_id);
    if (! $order) {
        wp_send_json_error('Invalid Order ID.');
    }

    send_quote($order_id);

    wp_send_json_success('Quote sent successfully.');
}

/**
 * Send invoice email and update HubSpot deal
 */
add_action('wp_ajax_hubwoosync_send_invoice_email', 'hubwoosync_send_invoice_email');
function ubwoosync_send_invoice_email() {
    check_ajax_referer('send_invoice_email_nonce', 'security');

    if (! current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized', 403);
    }

    $order_id = absint($_POST['order_id']);
    $order = wc_get_order($order_id);
    if (!$order) wp_send_json_error('Invalid Order ID.');

    $email = $order->get_billing_email();
    if (!$email) wp_send_json_error('Customer email not found.');

    // Trigger WooCommerce invoice email
    WC()->mailer()->emails['WC_Email_Customer_Invoice']->trigger($order_id);

    // Update invoice status
    $order->update_meta_data('invoice_status', 'Invoice Sent');
    $order->save();

    // Use correct order type check
    $is_manual = is_order_manual($order);
    $invoice_stage_id = $is_manual
        ? get_option('hubspot_stage_invoice_sent_manual')
        : get_option('hubspot_stage_invoice_sent_online');

    // Update HubSpot stage first
    if ($invoice_stage_id) {
        update_hubspot_deal_stage($order_id, $invoice_stage_id);
    }

    log_email_in_hubspot($order_id, 'invoice');

    wp_send_json_success('Invoice sent successfully.');
}


/**
 * Manual sync of WooCommerce order from HubSpot deal
 */
add_action('wp_ajax_hubwoosync_manual_sync_hubspot_order', 'hubwoosync_manual_sync_hubspot_order');
function ubwoosync_manual_sync_hubspot_order() {
    check_ajax_referer('hubwoosync_manual_sync_hubspot_order_nonce', 'security');

    $order_id = absint($_POST['order_id']);
    $order = wc_get_order($order_id);
    if (!$order) wp_send_json_error('Invalid Order ID.');

    $deal_id = $order->get_meta('hubspot_deal_id');
    if (!$deal_id) wp_send_json_error('Order not linked to a HubSpot deal.');

    $deal = fetch_hubspot_deal($deal_id);
    if (!$deal) wp_send_json_error('Failed to fetch deal from HubSpot.');

    $labels = get_hubspot_pipeline_and_stage_labels();
    $pipeline_id = $deal['pipeline'] ?? '';
    $dealstage_id = $deal['dealstage'] ?? '';
    $pipeline_label = $labels['pipelines'][$pipeline_id] ?? $pipeline_id;
    $dealstage_label = $labels['stages'][$dealstage_id] ?? $dealstage_id;

    foreach ($order->get_items() as $id => $item) $order->remove_item($id);
    foreach ($order->get_items('shipping') as $id => $item) $order->remove_item($id);

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
    $order->update_meta_data('_shipping_phone', $deal['phone_shipping'] ?: $order->get_billing_phone());

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

    if (!empty($deal['shipping'])) {
        $shipping = new WC_Order_Item_Shipping();
        $shipping->set_method_title('Shipping');
        $shipping->set_method_id('flat_rate');
        $shipping->set_total(floatval($deal['shipping']));
        $order->add_item($shipping);
        $order->set_shipping_total(floatval($deal['shipping']));
    }

    $order->calculate_totals();

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


/**
 * Create new HubSpot deal from existing WooCommerce order
 */
add_action('wp_ajax_create_hubspot_deal_manual', 'hubwoosync_create_hubspot_deal_manual');
function hubwoosync_create_hubspot_deal_manual() {
    check_ajax_referer('create_hubspot_deal_manual_nonce', 'security');

    $order_id = absint($_POST['order_id']);
    $order = wc_get_order($order_id);
    if (!$order) wp_send_json_error('Invalid Order ID.');

    if ($order->get_meta('hubspot_deal_id')) {
        wp_send_json_error('Deal already exists for this order.');
    }

    $pipeline_id = get_option('hubspot_pipeline_manual');
    if (!$pipeline_id) {
        wp_send_json_error('Manual pipeline not configured.');
    }

    $token = manage_hubspot_access_token();
    $status_key = 'manual_wc-' . $order->get_status();
    $mapping = get_option('hubspot_status_stage_mapping', []);
    $stage_id = $mapping[$status_key] ?? hubspot_get_cached_first_stage_of_pipeline($pipeline_id);

    $contact_id = hubspot_get_or_create_contact_id_from_order($order, $token);
    $deal_id = hubspot_create_deal_from_order($order, $pipeline_id, $stage_id, $contact_id, $token);

    if (!$deal_id) {
        wp_send_json_error('Failed to create HubSpot deal.');
    }

    $order->update_meta_data('hubspot_deal_id', $deal_id);
    $order->update_meta_data('hubspot_pipeline_id', $pipeline_id);
    $order->update_meta_data('hubspot_dealstage_id', $stage_id);
    $order->update_meta_data('order_type', 'manual');
    $order->save();

    // Associate the new deal with the contact in HubSpot
    hubspot_associate_objects('deal', $deal_id, 'contact', $contact_id, $token);

    $order->add_order_note("✅ Created HubSpot deal #{$deal_id} (Manual pipeline)");

    wp_send_json_success('Deal successfully created.');
}
