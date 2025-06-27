<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function hubwoosync_order_type( WC_Order $order ) {
    return $order->get_meta( 'order_type' ) ?: 'online';
}

/**
 * Determine if an order is admin/manual
 */
function is_order_manual($order) {
    $order_type = $order->get_meta('order_type');
    return $order_type === 'manual';
}

function hubwoo_log( $message, $level = 'info' ) {
    $debug_enabled = ( defined( 'HUBSPOT_WC_DEBUG' ) && HUBSPOT_WC_DEBUG ) ||
        ( defined( 'WP_DEBUG' ) && WP_DEBUG );

    if ( $debug_enabled ) {
        error_log( $message );
    }
}
