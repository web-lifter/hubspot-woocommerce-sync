<?php
/**
 * Auto-sync WooCommerce Online Orders to HubSpot with Logging
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mark orders created via the frontend as online and admin orders as manual.
 */
add_action( 'woocommerce_new_order', 'hubwoosync_set_order_type_for_online_orders', 20, 2 );
function hubwoosync_set_order_type_for_online_orders( $order_id, $order ) {
    if ( ! is_a( $order, 'WC_Order' ) ) {
        $order = wc_get_order( $order_id );
    }

    $existing = $order->get_meta( 'order_type' );

    // Admin/REST/CLI orders are treated as manual
    if ( is_admin() || defined( 'REST_REQUEST' ) || php_sapi_name() === 'cli' ) {
        if ( strtolower( $existing ) !== 'manual' ) {
            $order->update_meta_data( 'order_type', 'manual' );
    // Skip if already marked manual
    if ( strtolower( $existing ) === 'manual' ) {

    $customer_id = $order->get_customer_id();
    $is_guest    = $order->get_user_id() === 0;
    if ( $is_guest || $customer_id > 0 ) {
        $order->update_meta_data( 'order_type', 'online' );
        $order->save_meta_data();
    }
}

add_filter( 'woocommerce_orders_table_meta_keys', function ( $keys ) {
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
} );

/**
 * Entry point for HubSpot sync after WooCommerce order payment completion.
 */
add_action( 'woocommerce_payment_complete', 'hubwoosync_auto_sync_online_order', 10, 1 );
function hubwoosync_auto_sync_online_order( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || is_order_manual( $order ) ) {
    }

    if ( ! $order->get_meta( 'order_type' ) ) {
        $order->update_meta_data( 'order_type', 'online' );
        $order->save_meta_data();
    }

    if ( $order->get_meta( 'hubspot_deal_id' ) ) {

    $access_token = manage_hubspot_access_token();
    if ( ! $access_token ) {

    $email      = $order->get_billing_email();
    $contact_id = hubspot_get_or_create_contact( $order, $email, $access_token );
    if ( ! $contact_id ) {

    $company_id  = null;
    $company_name = $order->get_billing_company();
    if ( ! empty( $company_name ) ) {
        $company_id = hubspot_get_or_create_company( $company_name, $access_token );
    }

    $pipeline_id = get_option( 'hubspot_pipeline_online' );
    if ( ! $pipeline_id ) {

    $status     = $order->get_status();
    $status_key = "online_wc-{$status}";
    $mapping    = get_option( 'hubspot_status_stage_mapping', [] );
    $deal_stage = $mapping[ $status_key ] ?? '';
    if ( ! $deal_stage ) {
        $deal_stage = hubspot_get_first_stage_of_pipeline( $pipeline_id, $access_token );
    }

    $deal_id = hubspot_create_deal_from_order( $order, $pipeline_id, $deal_stage, $contact_id, $access_token );
    if ( ! $deal_id ) {

    hubspot_associate_objects( 'deal', $deal_id, 'contact', $contact_id, $access_token );
    if ( $company_id ) {
        hubspot_associate_objects( 'deal', $deal_id, 'company', $company_id, $access_token );
    }

    hubwoosync_add_line_items_to_deal( $order, $deal_id, $access_token );

    $labels         = get_hubspot_pipeline_and_stage_labels();
    $pipeline_label = $labels['pipelines'][ $pipeline_id ] ?? $pipeline_id;
    $stage_label    = $labels['stages'][ $deal_stage ] ?? $deal_stage;

    $order->update_meta_data( 'hubspot_deal_id', $deal_id );
    $order->update_meta_data( 'hubspot_pipeline_id', $pipeline_id );
    $order->update_meta_data( 'hubspot_pipeline', $pipeline_label );
    $order->update_meta_data( 'hubspot_dealstage_id', $deal_stage );
    $order->update_meta_data( 'hubspot_dealstage', $stage_label );
    $order->add_order_note( "✔️ HubSpot deal created. Deal ID: {$deal_id}" );
    $order->save();
}

/**
 * Add line items with logging.
 */
function hubwoosync_add_line_items_to_deal( $order, $deal_id, $access_token ) {
    foreach ( $order->get_items() as $item ) {
        $name     = $item->get_name();
        $qty      = $item->get_quantity();
        $subtotal = $item->get_subtotal();
        $price    = $qty > 0 ? $subtotal / $qty : 0;
        $sku      = $item->get_product() ? $item->get_product()->get_sku() : '';
        $gst      = round( $item->get_total_tax(), 2 );

        $line_item_id = hubwoosync_create_line_item( $name, $price, $qty, $sku, $gst, $access_token );
        if ( $line_item_id ) {
            hubspot_associate_objects( 'deal', $deal_id, 'line_item', $line_item_id, $access_token );
        }
    }
}

function hubwoosync_create_line_item( $name, $price, $quantity, $sku, $gst, $access_token ) {
    $payload = [
        'properties' => [
            'name'     => $name,
            'price'    => round( $price, 2 ),
            'quantity' => $quantity,
            'sku'      => $sku,
            'gst'      => $gst,
        ],
    ];

    $response = wp_remote_post( 'https://api.hubapi.com/crm/v3/objects/line_items', [
        'headers' => [
            'Authorization' => "Bearer {$access_token}",
            'Content-Type'  => 'application/json',
        ],
        'body'    => json_encode( $payload ),
    ] );

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    return $data['id'] ?? null;
}

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