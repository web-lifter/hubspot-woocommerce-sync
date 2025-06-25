<?php
/**
 * Abandoned Cart Tracker
 * Tracks identifiable carts, stores them in DB, and prepares restore links
 */

if (!defined('ABSPATH')) exit;

// Capture carts when updated and identifiable
add_action('woocommerce_cart_updated', 'hubspot_capture_abandoned_cart');
function hubspot_capture_abandoned_cart() {
    if (is_admin() || wp_doing_ajax()) return;

    $cart = WC()->cart;
    if (!$cart || $cart->is_empty()) return;

    $user_id = get_current_user_id();
    $email = WC()->customer->get_billing_email();

    if (!$user_id && !is_email($email)) return; // anonymous and unidentifiable

    $cart_items = [];
    foreach ($cart->get_cart() as $item) {
        $product = $item['data'];
        $cart_items[] = [
            'product_id' => $product->get_id(),
            'name'       => $product->get_name(),
            'quantity'   => $item['quantity'],
            'price'      => $product->get_price(),
        ];
    }

    $restore_token = md5(serialize($cart_items) . microtime(true) . rand());
    $identifier    = $user_id ? "user_{$user_id}" : 'guest_' . md5($email);
    $restore_url   = add_query_arg('restore_cart', $restore_token, wc_get_checkout_url());

    global $wpdb;
    $table = $wpdb->prefix . 'hubspot_abandoned_carts';

    $wpdb->replace($table, [
        'cart_key'       => $identifier,
        'user_id'        => $user_id ?: null,
        'email'          => $email,
        'cart_data'      => wp_json_encode($cart_items),
        'total'          => $cart->get_total('edit'),
        'shipping_total' => $cart->get_shipping_total(),
        'restore_token'  => $restore_token,
        'restore_url'    => $restore_url,
        'recovered'      => 0,
        'created_at'     => current_time('mysql'),
        'last_updated'   => current_time('mysql'),
    ]);

    // Optionally queue to HubSpot abandoned cart sync here
    do_action('hubspot_abandoned_cart_recorded', $identifier);
}

// Mark abandoned cart as recovered when payment is completed
add_action('woocommerce_thankyou', 'hubspot_flag_cart_recovered');
function hubspot_flag_cart_recovered($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $email = $order->get_billing_email();
    $user_id = $order->get_user_id();

    global $wpdb;
    $table = $wpdb->prefix . 'hubspot_abandoned_carts';

    $identifier = $user_id ? "user_{$user_id}" : 'guest_' . md5($email);
    $wpdb->update($table, ['recovered' => 1, 'recovered_at' => current_time('mysql')], ['cart_key' => $identifier]);

    do_action('hubspot_abandoned_cart_recovered', $identifier, $order_id);
}
