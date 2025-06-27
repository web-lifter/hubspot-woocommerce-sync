<?php
/**
 * Auto-sync WooCommerce Online Orders to HubSpot with Logging
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Set 'order_type' meta when a new order is created.
 * Admin and REST orders are marked as 'manual', all others as 'online'.
 */
add_action( 'woocommerce_new_order', 'hubwoosync_set_order_type_for_online_orders', 20, 2 );
function hubwoosync_set_order_type_for_online_orders( $order_id, $order ) {
    if ( ! is_a( $order, 'WC_Order' ) ) {
        $order = wc_get_order( $order_id );
    }

    $existing = $order->get_meta( 'order_type' );
    if ( strtolower( $existing ) === 'manual' ) {
        return; // Don't override if already marked manual
    }

    if ( is_admin() || defined( 'REST_REQUEST' ) || php_sapi_name() === 'cli' ) {
        $order->update_meta_data( 'order_type', 'manual' );
    } else {
        $order->update_meta_data( 'order_type', 'online' );
    }

    $order->save_meta_data();
}

/**
 * Ensure order meta keys are included in HPOS/DB lookup.
 */
add_filter( 'woocommerce_orders_table_meta_keys', function ( $keys ) {
    return array_merge( $keys, [
        'hubspot_deal_id',
        'hubspot_pipeline',
        'hubspot_dealstage',
        'hubspot_pipeline_id',
        'hubspot_dealstage_id',
        'invoice_status',
        'quote_status',
        'quote_last_sent',
        'order_type',
    ] );
} );

/**
 * Trigger HubSpot sync when an online order is paid.
 */
add_action( 'woocommerce_payment_complete', 'hubwoosync_auto_sync_online_order' );
function hubwoosync_auto_sync_online_order( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    // Respect manual orders
    if ( is_order_manual( $order ) ) return;

    if ( ! $order->get_meta( 'order_type' ) ) {
        $order->update_meta_data( 'order_type', 'online' );
        $order->save_meta_data();
    }

    // Skip if already linked to a HubSpot deal
    if ( $order->get_meta( 'hubspot_deal_id' ) ) return;

    $access_token = manage_hubspot_access_token();
    if ( ! $access_token ) return;

    // Create or fetch HubSpot contact
    $email = $order->get_billing_email();
    $contact_id = hubspot_get_or_create_contact( $order, $email, $access_token );
    if ( ! $contact_id ) return;

    // Create or fetch HubSpot company (optional)
    $company_id = null;
    $company_name = $order->get_billing_company();
    if ( ! empty( $company_name ) ) {
        $company_id = hubspot_get_or_create_company( $company_name, $access_token );
    }

    // Get pipeline and deal stage
    $pipeline_id = get_option( 'hubspot_pipeline_online' );
    if ( ! $pipeline_id ) return;

    $status = $order->get_status(); // e.g. 'processing'
    $status_key = "online_wc-{$status}";
    $mapping = get_option( 'hubspot_status_stage_mapping', [] );
    $deal_stage = $mapping[ $status_key ] ?? hubspot_get_first_stage_of_pipeline( $pipeline_id, $access_token );
    if ( ! $deal_stage ) return;

    // Create HubSpot deal
    $deal_id = hubspot_create_deal_from_order( $order, $pipeline_id, $deal_stage, $contact_id, $access_token );
    if ( ! $deal_id ) return;

    // Associate contact and company
    hubspot_associate_objects( 'deal', $deal_id, 'contact', $contact_id, $access_token );
    if ( $company_id ) {
        hubspot_associate_objects( 'deal', $deal_id, 'company', $company_id, $access_token );
    }

    // Add WooCommerce line items
    hubwoosync_add_line_items_to_deal( $order, $deal_id, $access_token );

    // Get readable labels
    $labels = get_hubspot_pipeline_and_stage_labels();
    $pipeline_label = $labels['pipelines'][ $pipeline_id ] ?? $pipeline_id;
    $stage_label    = $labels['stages'][ $deal_stage ] ?? $deal_stage;

    // Save metadata and note
    $order->update_meta_data( 'hubspot_deal_id', $deal_id );
    $order->update_meta_data( 'hubspot_pipeline_id', $pipeline_id );
    $order->update_meta_data( 'hubspot_pipeline', $pipeline_label );
    $order->update_meta_data( 'hubspot_dealstage_id', $deal_stage );
    $order->update_meta_data( 'hubspot_dealstage', $stage_label );
    $order->add_order_note( "✔️ HubSpot deal created. Deal ID: {$deal_id}" );
    $order->save();
}

/**
 * Add line items to the HubSpot deal and log associations.
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

/**
 * Create a line item in HubSpot
 */
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
        'body' => json_encode( $payload ),
    ] );

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    return $data['id'] ?? null;
}
