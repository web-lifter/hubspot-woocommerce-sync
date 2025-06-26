<?php
/**
 * HubSpot Util Functions
 *
 * @package Steelmark
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function hubwoo_order_type( WC_Order $order ) {
    return $order->get_meta( 'order_type' ) ?: 'online';
}

/**
 * Log messages conditionally when debugging is enabled.
 *
 * @param string $message Log message.
 * @param string $level   Log level: 'info' or 'error'.
 */
function hubwoo_log( $message, $level = 'info' ) {
    if ( 'error' === $level ) {
        error_log( $message );
        return;
    }

    if ( ( defined( 'HUBSPOT_WC_DEBUG' ) && HUBSPOT_WC_DEBUG ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
        error_log( $message );
    }
}

    exit;
}

function order_type(WC_Order $order) {
    return $order->get_meta('order_type') ?: 'online';
}

