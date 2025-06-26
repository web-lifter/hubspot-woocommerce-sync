<?php
/**
 * Plugin Name: HubSpot Order Management
 * Description: Adds a custom WooCommerce admin page to view HubSpot-synced orders and manually trigger deal sync.
 * Version: 1.1
 */

if (!defined('ABSPATH')) exit;

/**
 * Render HubSpot Order Management Table
 */
function render_hubspot_orders_page_table_only() {
    $nonces = [
        'quote'     => wp_create_nonce('send_quote_email_nonce'),
        'reset'     => wp_create_nonce('reset_quote_status_nonce'),
        'invoice'   => wp_create_nonce('send_invoice_email_nonce'),
        'sync'      => wp_create_nonce('manual_sync_hubspot_order_nonce'),
        'create_deal' => wp_create_nonce('create_hubspot_deal_nonce'),

    ];

    $paged  = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $limit  = 200;
    $offset = ($paged - 1) * $limit;    echo '<form method="get" style="margin-bottom:10px;">';
    echo '<input type="hidden" name="page" value="hubspot-order-management">';
    echo '<input type="search" name="search" placeholder="' . esc_attr__( 'Customer or Deal ID', 'hub-woo-sync' ) . '" value="' . esc_attr($search) . '" /> ';
    $status_options = [
        '' => __( 'All Statuses', 'hub-woo-sync' ),
        'pending' => __( 'Pending Payment', 'hub-woo-sync' ),
        'processing' => __( 'Processing', 'hub-woo-sync' ),
        'completed' => __( 'Completed', 'hub-woo-sync' ),
        'failed' => __( 'Failed', 'hub-woo-sync' ),
    ];
    echo '<button class="button">' . esc_html__( 'Filter', 'hub-woo-sync' ) . '</button>';
        <th><?php echo esc_html__( "Order #", "hub-woo-sync" ); ?></th>
        <th><?php echo esc_html__( "Customer", "hub-woo-sync" ); ?></th>
        <th><?php echo esc_html__( "Deal ID", "hub-woo-sync" ); ?></th>
        <th><?php echo esc_html__( "Order Type", "hub-woo-sync" ); ?></th>
        <th><?php echo esc_html__( "Total", "hub-woo-sync" ); ?></th>
        <th><?php echo esc_html__( "Status", "hub-woo-sync" ); ?></th>
        <th><?php echo esc_html__( "Deal Stage", "hub-woo-sync" ); ?></th>
        <th><?php echo esc_html__( "Quote Status", "hub-woo-sync" ); ?></th>
        <th><?php echo esc_html__( "Invoice Status", "hub-woo-sync" ); ?></th>
        <th><?php echo esc_html__( "Sync", "hub-woo-sync" ); ?></th>

    if ($status) {
        $query_args['status'] = $status;
    }

    if ($search) {
        $query_args['meta_query'] = [
            'relation' => 'OR',
            [
                'key'     => 'hubspot_deal_id',
                'value'   => $search,
                'compare' => 'LIKE',
            ],
            [
                'key'     => '_billing_first_name',
                'value'   => $search,
                'compare' => 'LIKE',
            ],
            [
                'key'     => '_billing_last_name',
                'value'   => $search,
                'compare' => 'LIKE',
            ],
        ];
    }

    $total_query_args       = $query_args;
    $total_query_args['limit']  = -1;
    $total_query_args['return'] = 'ids';

    $total_orders = wc_get_orders($total_query_args);
    $total_pages  = ceil(count($total_orders) / $limit);

    $orders = wc_get_orders($query_args);


    echo '<div class="wrap"><h1>HubSpot Orders</h1>';
    echo '<style>.completed-row{background-color:#d4ffd8;}</style>';

    echo '<form method="get" style="margin-bottom:10px;">';
    echo '<input type="hidden" name="page" value="hubspot-order-management">';
    echo '<input type="search" name="search" placeholder="Customer or Deal ID" value="' . esc_attr($search) . '" /> ';
    echo '<select name="status">';
    $status_options = [
        '' => 'All Statuses',
        'pending' => 'Pending Payment',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ];
    foreach ($status_options as $key => $label) {
        $selected = selected($status, $key, false);
        echo '<option value="' . esc_attr($key) . '"' . $selected . '>' . esc_html($label) . '</option>';
    }
    echo '</select> ';
    echo '<button class="button">Filter</button>';
    echo '</form>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>
        <th>Order #</th>
        <th>Customer</th>
        $(".send-quote").click(function() {
            const btn = $(this);
            postAction("send_quote_email", btn.data("order-id"), btn.data("nonce"), btn, "<?php echo esc_js( __( 'Quote sent!', 'hub-woo-sync' ) ); ?>", "<?php echo esc_js( __( 'Send failed', 'hub-woo-sync' ) ); ?>");
        });
            if (!confirm("<?php echo esc_js( __( 'Reset quote status for this order?', 'hub-woo-sync' ) ); ?>")) return;
            postAction("reset_quote_status", btn.data("order-id"), btn.data("nonce"), btn, "<?php echo esc_js( __( 'Quote status reset.', 'hub-woo-sync' ) ); ?>", "<?php echo esc_js( __( 'Reset failed', 'hub-woo-sync' ) ); ?>");
            postAction("send_invoice_email", btn.data("order-id"), btn.data("nonce"), btn, "<?php echo esc_js( __( 'Invoice sent!', 'hub-woo-sync' ) ); ?>", "<?php echo esc_js( __( 'Invoice failed', 'hub-woo-sync' ) ); ?>");
            postAction("manual_sync_hubspot_order", btn.data("order-id"), btn.data("nonce"), btn, "<?php echo esc_js( __( 'Order synced!', 'hub-woo-sync' ) ); ?>", "<?php echo esc_js( __( 'Sync failed', 'hub-woo-sync' ) ); ?>");
                btn,
                "<?php echo esc_js( __( 'Deal created successfully!', 'hub-woo-sync' ) ); ?>",
                "<?php echo esc_js( __( 'Deal creation failed', 'hub-woo-sync' ) ); ?>"
            );
        <th>Invoice Status</th>
        <th>Sync</th>
        <th>Actions</th>            postAction("hubwoosync_manual_sync_hubspot_order", btn.data("order-id"), btn.data("nonce"), btn, "Order synced!", "Sync failed");
                "hubwoosync_create_hubspot_deal_manual",


    foreach ($orders as $order) {
        $order_id = $order->get_id();
        $deal_id  = $order->get_meta('hubspot_deal_id') ?: '—';
        $labels = get_hubspot_pipeline_and_stage_labels();

        $pipeline_id    = $order->get_meta('hubspot_pipeline_id') ?: '—';
        $pipeline_label = $order->get_meta('hubspot_pipeline');
        if (!$pipeline_label) {
            $pipeline_label = $labels['pipelines'][$pipeline_id] ?? $pipeline_id;
        }

        $stage_id    = $order->get_meta('hubspot_dealstage_id') ?: '—';
        $stage_label = $order->get_meta('hubspot_dealstage');
        if (!$stage_label) {
            $stage_label = $labels['stages'][$stage_id] ?? $stage_id;
        }

        $name  = $order->get_formatted_billing_full_name();
        $total = $order->get_formatted_order_total();

        $quote_status = $order->get_meta('quote_status') ?: 'Quote Not Sent';
        $last_sent    = $order->get_meta('quote_last_sent');
        $modified     = $order->get_date_modified();
        $is_outdated  = $last_sent && $modified && strtotime($modified->date('Y-m-d H:i:s')) > strtotime($last_sent);
        $quote_display = esc_html($quote_status) . ($is_outdated ? ' <span style="color:#d63638;">(Outdated)</span>' : '');
        $send_quote_label = ($quote_status === 'Quote Sent' || $quote_status === 'Quote Not Accepted') ? 'Resend Quote' : 'Send Quote';

        $invoice_status = $order->get_meta('invoice_status') ?: 'Not Sent';

        $row_class = ($order->get_status() === 'completed') ? 'completed-row' : '';
        echo '<tr class="' . esc_attr($row_class) . '">';
        echo '<td><a href="' . esc_url(get_edit_post_link($order_id)) . '">#' . esc_html($order_id) . '</a></td>';
        echo '<td>' . esc_html($name) . '</td>';
        echo '<td>' . esc_html($deal_id) . '</td>';
        $order_type = $order->get_meta('order_type') ?: '—';
        echo '<td>' . esc_html(ucfirst($order_type)) . '</td>';
        $order_status = wc_get_order_status_name($order->get_status());
        echo '<td>' . $total . '</td>';
        echo '<td>' . esc_html($order_status) . '</td>';
        echo '<td>' . esc_html($stage_label) . '</td>';
        echo '<td>' . $quote_display . '</td>';
        echo '<td>' . esc_html($invoice_status) . '</td>';
        echo '<td><button class="button manual-sync" data-order-id="' . esc_attr($order_id) . '" data-nonce="' . esc_attr($nonces['sync']) . '">Sync</button></td>';
        echo '<td>
            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                <button class="button send-quote" data-order-id="' . esc_attr($order_id) . '" data-nonce="' . esc_attr($nonces['quote']) . '">' . esc_html($send_quote_label) . '</button>
                <button class="button reset-quote" data-order-id="' . esc_attr($order_id) . '" data-nonce="' . esc_attr($nonces['reset']) . '">Reset Quote</button>
                <button class="button send-invoice" data-order-id="' . esc_attr($order_id) . '" data-nonce="' . esc_attr($nonces['invoice']) . '">Send Invoice</button>';

                // ✅ Add Create Deal button if no deal ID
                if ($deal_id === '—') {
                    echo '<button class="button create-deal" data-order-id="' . esc_attr($order_id) . '" data-nonce="' . esc_attr($nonces['create_deal']) . '">Create Deal</button>';
                }

            echo '</div>
        </td>';

        echo '</tr>';
    }

    echo '</tbody></table>';

    if ($total_pages > 1) {
        $base_url = remove_query_arg('paged');
        $base_url = add_query_arg([
            'search' => $search,
            'status' => $status,
        ], $base_url);
        echo '<div class="tablenav"><div class="tablenav-pages"><span class="pagination-links">';
        if ($paged > 1) {
            echo '<a class="prev-page button" href="' . esc_url(add_query_arg('paged', $paged - 1, $base_url)) . '">&laquo;</a> ';
        }
        for ($i = 1; $i <= $total_pages; $i++) {
            $page_url = add_query_arg('paged', $i, $base_url);
            $class = ($i === $paged) ? 'button current' : 'button';
            echo '<a href="' . esc_url($page_url) . '" class="' . esc_attr($class) . '">' . esc_html($i) . '</a> ';
        }
        if ($paged < $total_pages) {
            echo '<a class="next-page button" href="' . esc_url(add_query_arg('paged', $paged + 1, $base_url)) . '">&raquo;</a>';
        }
        echo '</span></div></div>';
    }

    echo '</div>';

    // Inline JS
    echo '<script type="text/javascript">
    jQuery(function($){
        function postAction(action, orderId, nonce, button, successMsg, errorMsg) {
            button.text("Processing...");
            $.post(ajaxurl, {
                action: action,
                order_id: orderId,
                security: nonce
            }, function(response) {
                if (response.success) {
                    alert(successMsg);
                    location.reload();
                } else {
                    alert(errorMsg + ": " + response.data);
                    button.text(button.data("original-label"));
                }
            });
        }

        $(".send-quote, .reset-quote, .send-invoice, .manual-sync").each(function() {
            const btn = $(this);
            btn.data("original-label", btn.text());
        });

        $(".send-quote").click(function() {
            const btn = $(this);
            postAction("send_quote_email", btn.data("order-id"), btn.data("nonce"), btn, "Quote sent!", "Send failed");
        });

        $(".reset-quote").click(function() {
            const btn = $(this);
            if (!confirm("Reset quote status for this order?")) return;
            postAction("reset_quote_status", btn.data("order-id"), btn.data("nonce"), btn, "Quote status reset.", "Reset failed");
        });

        $(".send-invoice").click(function() {
            const btn = $(this);
            postAction("send_invoice_email", btn.data("order-id"), btn.data("nonce"), btn, "Invoice sent!", "Invoice failed");
        });

        $(".manual-sync").click(function() {
            const btn = $(this);
            postAction("manual_sync_hubspot_order", btn.data("order-id"), btn.data("nonce"), btn, "Order synced!", "Sync failed");
        });

        $(".create-deal").click(function () {
            const btn = $(this);
            postAction(
                "create_hubspot_deal_manual",
                btn.data("order-id"),
                btn.data("nonce"),
                btn,
                "Deal created successfully!",
                "Deal creation failed"
            );
        });

    });
    </script>';
}


function get_hubspot_pipeline_and_stage_labels() {
    $pipelines = get_option('hubspot_cached_pipelines');

    if (!is_array($pipelines)) {
        return [
            'pipelines' => [],
            'stages'    => []
        ];
    }

    $pipeline_labels = [];
    $stage_labels    = [];

    foreach ($pipelines as $pid => $pipeline) {
        $id    = $pipeline['id'] ?? $pid;
        $label = $pipeline['label'] ?? '';
        $pipeline_labels[$id] = $label;

        if (!empty($pipeline['stages']) && is_array($pipeline['stages'])) {
            foreach ($pipeline['stages'] as $stage_id => $stage_name) {
                $stage_labels[$stage_id] = $stage_name;
            }
        }
    }
    return [
        'pipelines' => $pipeline_labels,
        'stages'    => $stage_labels
    ];
}