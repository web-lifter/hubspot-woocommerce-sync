<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class HubSpot_WC_Sync {

    /**
     * Initialize automatic deal creation for WooCommerce orders
     */
    public static function init() {
        add_action('woocommerce_thankyou', [__CLASS__, 'create_hubspot_deal_from_order'], 10, 1);
    }

    /**
     * Create a WooCommerce order from a HubSpot deal
     */
    public static function create_order($store_url, $deal_id) {
        $deal_data = HubSpot_WC_API::get_deal($store_url, $deal_id);
        if (!$deal_data) {
            return new WP_Error('no_deal', 'Failed to fetch HubSpot deal data', ['status' => 500]);
        }

        $contact = $deal_data['associations']['contacts'][0] ?? [];
        $company = $deal_data['associations']['companies'][0] ?? [];
        $line_items = $deal_data['associations']['line_items'] ?? [];

        $order = wc_create_order();
        $order->set_billing_email($contact['email'] ?? '');
        $order->set_billing_first_name($contact['firstName'] ?? '');
        $order->set_billing_last_name($contact['lastName'] ?? '');
        $order->set_billing_company($company['name'] ?? '');
        
        foreach ($line_items as $item) {
            $product_id = self::get_product_by_sku($item['sku'] ?? '');
            if ($product_id) {
                $product = wc_get_product($product_id);
                $order->add_product($product, $item['quantity'] ?? 1);
            }
        }

        $order->update_meta_data('hubspot_deal_id', $deal_id);
        $order->save();
        return $order->get_id();
    }

    /**
     * Update an existing WooCommerce order from a HubSpot deal
     */
    public static function update_order($store_url, $order_id, $deal_id) {
        $deal_data = HubSpot_WC_API::get_deal($store_url, $deal_id);
        if (!$deal_data) {
            return new WP_Error('no_deal', 'Failed to fetch HubSpot deal data', ['status' => 500]);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', 'Order not found', ['status' => 404]);
        }

        $contact = $deal_data['associations']['contacts'][0] ?? [];
        $order->set_billing_email($contact['email'] ?? '');
        $order->save();

        return $order->get_id();
    }

    /**
     * Delete a WooCommerce order linked to a HubSpot deal
     */
    public static function delete_order($store_url, $deal_id) {
        $query = new WC_Order_Query([
            'limit'        => 1,
            'meta_key'     => 'hubspot_deal_id',
            'meta_value'   => $deal_id,
            'meta_compare' => '='
        ]);

        $orders = $query->get_orders();
        $order = !empty($orders) ? reset($orders) : null;

        if (!$order) {
            return new WP_Error('no_order', 'Order not found', ['status' => 404]);
        }

        wp_delete_post($order->get_id(), true);
        return true;
    }

    /**
     * Automatically create a HubSpot deal when a new WooCommerce order is placed
     */
    public static function create_hubspot_deal_from_order($order_id) {
        if (!$order_id || get_option('hubspot_auto_create_deal') !== 'yes') {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $store_url = get_site_url();
        $access_token = HubSpot_WC_Auth::get_access_token($store_url);
        if (!$access_token) {
            return;
        }

        // Retrieve the selected pipeline from settings
        $pipeline_id = get_option('hubspot_pipeline_id', 'default');

        // Prepare deal data
        $deal_data = [
            'properties' => [
                'dealname'       => 'Order #' . $order_id,
                'amount'         => $order->get_total(),
                'pipeline'       => $pipeline_id,
                'dealstage'      => 'appointmentscheduled', // Default deal stage (can be customized)
                'order_number'   => (string) $order_id,
                'order_status'   => $order->get_status(),
                'hubspot_owner_id' => '', // Optionally assign an owner
            ],
            'associations' => [
                'associatedCompanyIds' => [], // Add company associations if needed
                'associatedContactIds' => [], // Add contact associations if needed
            ]
        ];

        // Create deal in HubSpot
        $response = wp_remote_post('https://api.hubapi.com/crm/v3/objects/deals', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode($deal_data),
        ]);

        if (is_wp_error($response)) {
            error_log("[HubSpot Sync] ❌ Failed to create HubSpot deal: " . $response->get_error_message());
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['id'])) {
            update_post_meta($order_id, 'hubspot_deal_id', $body['id']);
            error_log("[HubSpot Sync] ✅ Deal created successfully: Deal ID " . $body['id']);
        } else {
            error_log("[HubSpot Sync] ❌ HubSpot response did not return a deal ID");
        }
    }

    /**
     * Find a WooCommerce product by SKU
     */
    private static function get_product_by_sku($sku) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s", $sku));
    }
}

// Initialize WooCommerce deal sync
HubSpot_WC_Sync::init();

?>
