<?php
/**
 * Hubspot Util Functions
 *
 * @package Steelmark
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function order_type(WC_Order $order) {
    return $order->get_meta('order_type') ?: 'online';
}

