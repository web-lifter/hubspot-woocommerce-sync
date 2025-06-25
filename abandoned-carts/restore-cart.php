<?php
/**
 * Restore Abandoned Cart from Email Link
 * URL format: https://example.com/?restore_cart=KEY
 */
add_action('init', function () {
    if (!isset($_GET['restore_cart'])) return;

    $key = sanitize_text_field($_GET['restore_cart']);
    if (!$key) return;

    global $wpdb;
    $table = $wpdb->prefix . 'hubspot_abandoned_carts';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE restore_key = %s", $key), ARRAY_A);

    if (!$row || $row['status'] !== 'active') {
        wp_redirect(home_url()); // Invalid or already recovered
        exit;
    }

    // Decode cart data
    $items = json_decode($row['cart_data'], true);
    if (!$items) {
        wp_redirect(home_url());
        exit;
    }

    // Empty current cart and populate with saved items
    WC()->cart->empty_cart();
    foreach ($items as $item) {
        $product_id = $item['product_id'];
        $quantity   = $item['quantity'];
        WC()->cart->add_to_cart($product_id, $quantity);
    }

    // Flag this cart as restored
    $wpdb->update($table, ['status' => 'restored', 'restored_at' => current_time('mysql')], ['id' => $row['id']]);

    // Redirect to checkout
    wp_redirect(wc_get_checkout_url());
    exit;
});

add_action('woocommerce_thankyou', function ($order_id) {
    if (!isset($_COOKIE['restored_cart_id'])) return;

    $restored_id = absint($_COOKIE['restored_cart_id']);
    global $wpdb;
    $table = $wpdb->prefix . 'hubspot_abandoned_carts';

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $restored_id), ARRAY_A);
    if (!$row || $row['status'] !== 'restored') return;

    // Mark cart as converted
    $wpdb->update($table, ['status' => 'converted', 'converted_at' => current_time('mysql')], ['id' => $restored_id]);

    // Update HubSpot deal stage
    $deal_id = $row['hubspot_deal_id'];
    if ($deal_id) {
        $stage = get_option('hubspot_stage_cart_recovered');
        if ($stage) {
            $token = manage_hubspot_access_token();
            wp_remote_patch("https://api.hubapi.com/crm/v3/objects/deals/$deal_id", [
                'headers' => [
                    'Authorization' => "Bearer $token",
                    'Content-Type'  => 'application/json',
                ],
                'body' => json_encode(['properties' => ['dealstage' => $stage]]),
            ]);
        }
    }

    // Clear cookie
    setcookie('restored_cart_id', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
}, 10, 1);
