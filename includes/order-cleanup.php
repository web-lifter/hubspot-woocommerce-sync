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

