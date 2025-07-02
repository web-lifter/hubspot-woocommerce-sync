<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cleanup old WooCommerce orders based on status and age.
 */
function hubwoosync_cleanup_orders() {
    $status = get_option('hubspot_order_cleanup_status');
    $days   = absint(get_option('hubspot_order_cleanup_days'));
    if (!$status || $days <= 0) {
        return;
    }
    $cutoff = strtotime("-{$days} days");
    $orders = wc_get_orders([
        'status'       => $status,
        'date_created' => '<' . gmdate('Y-m-d H:i:s', $cutoff),
        'limit'        => -1,
        'return'       => 'ids',
    ]);
    foreach ($orders as $id) {
        wc_delete_order($id);
    }
}

add_action('hubspot_order_cleanup_event', 'hubwoosync_cleanup_orders');

// Automatically mark paid online orders as completed if enabled
add_action('woocommerce_payment_complete', 'hubwoosync_autocomplete_online_order', 100);
function hubwoosync_autocomplete_online_order($order_id) {
    if (!get_option('hubspot_autocomplete_online_order')) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order || $order->get_meta('order_type') !== 'online') {
        return;
    }

    if ($order->has_status('processing')) {
        $order->update_status('completed', __('Automatically completed', 'hub-woo-sync'));
    }
}