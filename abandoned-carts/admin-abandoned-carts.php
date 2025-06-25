<?php
if (!defined('ABSPATH')) exit;

/**
 * Render Abandoned Carts Admin Page with Analytics
 */
function render_abandoned_cart_admin_view() {
    global $wpdb;
    $table = $wpdb->prefix . 'hubspot_abandoned_carts';

    // Cart counts and revenue
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $abandoned = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active'");
    $recovered = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'recovered'");
    $ignored = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'ignored'");
    $recovered_value = (float) $wpdb->get_var("SELECT SUM(total) FROM $table WHERE status = 'recovered'");

    $recovery_rate = $total > 0 ? round(($recovered / $total) * 100, 2) : 0;
    $abandonment_rate = $total > 0 ? round(($abandoned / $total) * 100, 2) : 0;

    // Filter
    $status_filter = $_GET['status'] ?? 'active';
    $query = $wpdb->prepare("SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC", $status_filter);
    $carts = $wpdb->get_results($query, ARRAY_A);

    echo '<div class="wrap">';
    echo '<h1>Abandoned Carts</h1>';

    // Analytics Summary
    echo '<div style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">';
    echo '<h2 style="margin-top: 0;">Analytics Summary</h2>';
    echo '<ul style="list-style: none; padding-left: 0;">';
    echo '<li><strong>Total Carts:</strong> ' . number_format($total) . '</li>';
    echo '<li><strong>Recovered Carts:</strong> ' . number_format($recovered) . ' (' . $recovery_rate . '%)</li>';
    echo '<li><strong>Abandoned Carts:</strong> ' . number_format($abandoned) . ' (' . $abandonment_rate . '%)</li>';
    echo '<li><strong>Ignored Carts:</strong> ' . number_format($ignored) . '</li>';
    echo '<li><strong>Revenue Recovered:</strong> $' . number_format($recovered_value, 2) . '</li>';
    echo '</ul>';
    echo '</div>';

    // Filter Tabs
    echo '<ul class="subsubsub">';
    foreach (['active' => 'Active', 'recovered' => 'Recovered', 'ignored' => 'Ignored'] as $key => $label) {
        $current = $key === $status_filter ? 'class="current"' : '';
        echo "<li><a href='?page=hubspot-abandoned-carts&status={$key}' $current>$label</a></li> | ";
    }
    echo '</ul><br>';

    // Cart Table
    if (!$carts) {
        echo '<p>No abandoned carts found for this filter.</p></div>';
add_action('wp_ajax_update_abandoned_cart_status', function () {
    check_ajax_referer('hubspot_admin_nonce', 'security');
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }
    }

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>
        <th>Customer</th>
        <th>Email</th>
        <th>Items</th>
        <th>Total</th>
        <th>Shipping</th>
        <th>Created</th>
        <th>Status</th>
        <th>Actions</th>
    </tr></thead><tbody>';

    foreach ($carts as $cart) {
        $items = json_decode($cart['cart_data'], true);
        $item_summary = '';
        foreach ($items as $item) {
            $item_summary .= esc_html($item['name']) . ' × ' . intval($item['quantity']) . '<br>';
        }

        echo '<tr>';
        echo '<td>' . ($cart['user_id'] ? 'User #' . esc_html($cart['user_id']) : 'Guest') . '</td>';
        echo '<td>' . esc_html($cart['email']) . '</td>';
        echo '<td>' . $item_summary . '</td>';
        echo '<td>$' . number_format($cart['total'], 2) . '</td>';
        echo '<td>$' . number_format($cart['shipping_total'], 2) . '</td>';
        echo '<td>' . esc_html($cart['created_at'] ?: $cart['last_updated']) . '</td>';
        echo '<td>' . ucfirst($cart['status']) . '</td>';
        echo '<td>
            <button class="button recover-cart" data-id="' . esc_attr($cart['id']) . '">Mark Recovered</button>
            <button class="button ignore-cart" data-id="' . esc_attr($cart['id']) . '">Ignore</button>
        </td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';

    // Inline JS
    echo <<<JS
<script>
jQuery(function($) {
    function updateCartStatus(id, action, label) {
        $.post(ajaxurl, {
            action: 'update_abandoned_cart_status',
            cart_id: id,
            new_status: action,
            security: hubspot_admin_vars.nonce
        }, function(response) {
            if (response.success) {
                alert(label + " successful.");
                location.reload();
            } else {
                alert("Error: " + response.data);
            }
        });
    }

    $('.recover-cart').click(function() {
        if (confirm("Mark this cart as recovered?")) {
            updateCartStatus($(this).data('id'), 'recovered', 'Recovery');
        }
    });

    $('.ignore-cart').click(function() {
        if (confirm("Ignore this cart permanently?")) {
            updateCartStatus($(this).data('id'), 'ignored', 'Ignore');
        }
    });
});
</script>
JS;
}

// AJAX for cart status update
add_action('wp_ajax_update_abandoned_cart_status', function () {
    check_ajax_referer('hubspot_admin_nonce', 'security');

    $cart_id = absint($_POST['cart_id']);
    $new_status = sanitize_text_field($_POST['new_status']);
    if (!in_array($new_status, ['recovered', 'ignored'])) {
        wp_send_json_error('Invalid status.');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'hubspot_abandoned_carts';
    $updated = $wpdb->update(
        $table,
        ['status' => $new_status],
        ['id' => $cart_id],
        ['%s'],
        ['%d']
    );

    if ($updated !== false) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to update.');
    }
});

// Admin script vars
add_action('admin_enqueue_scripts', function () {
    wp_localize_script('jquery', 'hubspot_admin_vars', [
        'nonce' => wp_create_nonce('hubspot_admin_nonce')
    ]);
});
