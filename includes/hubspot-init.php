<?php

class HubSpot_WooCommerce_Sync {

    public static function init() {
        // Register menu items
        add_action('admin_menu', [__CLASS__, 'register_menu']);

        // Register settings
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_init', [__CLASS__, 'maybe_refresh_cache_on_save']);

        // AJAX hook for HubSpot connection check
        add_action('wp_ajax_hubspot_check_connection', [__CLASS__, 'hubspot_check_connection']);

        // WooCommerce hook for status change
        add_action('woocommerce_order_status_changed', [__CLASS__, 'handle_order_status_change'], 10, 3);
    }

    public static function register_settings() {
        register_setting('hubspot_wc_settings', 'hubspot_client_id');
        register_setting('hubspot_wc_settings', 'hubspot_client_secret');
        register_setting('hubspot_wc_settings', 'hubspot_connected');
        register_setting('hubspot_wc_settings', 'hubspot_auto_create_deal');
        register_setting('hubspot_wc_settings', 'hubspot_pipeline_online');
        register_setting('hubspot_wc_settings', 'hubspot_pipeline_manual');
        register_setting('hubspot_wc_settings', 'hubspot_pipeline_sync_enabled');
        register_setting('hubspot_wc_settings', 'hubspot_status_stage_mapping');
        register_setting('hubspot_wc_settings', 'hubspot_stage_quote_sent_manual');
        register_setting('hubspot_wc_settings', 'hubspot_stage_quote_sent_online');
        register_setting('hubspot_wc_settings', 'hubspot_stage_quote_accepted_manual');
        register_setting('hubspot_wc_settings', 'hubspot_stage_quote_accepted_online');
        register_setting('hubspot_wc_settings', 'hubspot_stage_invoice_sent_manual');
        register_setting('hubspot_wc_settings', 'hubspot_stage_invoice_sent_online');
    }

    public static function register_menu() {
        // Main HubSpot menu with Settings as default page
        add_menu_page(
            __('HubSpot Settings', 'hubspot-woocommerce-sync'),
            __('HubSpot', 'hubspot-woocommerce-sync'),
            'manage_options',
            'hubspot-woocommerce-sync',
            [__CLASS__, 'render_settings_page'],
            'dashicons-admin-generic',
            56
        );

        // Submenu: Settings
        add_submenu_page(
            'hubspot-woocommerce-sync',
            __('Settings', 'hubspot-woocommerce-sync'),
            __('Settings', 'hubspot-woocommerce-sync'),
            'manage_options',
            'hubspot-woocommerce-sync',
            [__CLASS__, 'render_settings_page']
        );

        // Submenu: Order Management
        add_submenu_page(
            'hubspot-woocommerce-sync',
            __('Order Management', 'hubspot-woocommerce-sync'),
            __('Order Management', 'hubspot-woocommerce-sync'),
            'manage_woocommerce',
            'hubspot-order-management',
            [__CLASS__, 'render_order_management_page']
        );
    }
}

// Initialize the plugin
HubSpot_WooCommerce_Sync::init();
