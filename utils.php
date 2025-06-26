<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function hubwoosync_order_type( WC_Order $order ) {
    return $order->get_meta( 'order_type' ) ?: 'online';
}

function hubwoo_log( $message, $level = 'info' ) {
    if ( 'error' === $level ) {
        error_log( $message );
        return;
    }

    if ( ( defined( 'HUBSPOT_WC_DEBUG' ) && HUBSPOT_WC_DEBUG ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
        error_log( $message );
    }
}
