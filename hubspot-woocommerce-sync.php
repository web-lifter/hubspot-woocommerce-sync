<?php
/**
 * Plugin Name: HubSpot WooCommerce Sync
 * Description: Integrates WooCommerce with HubSpot (deals, contacts, and pipelines sync).
 * Version: 1.0.0
 * Author: Web Lifter
 */
if (!defined('ABSPATH')) exit;

// Basic HubSpot app credentials. Replace with real values or move to settings.
define('HUBSPOT_CLIENT_ID', 'your-client-id');
define('HUBSPOT_CLIENT_SECRET', 'your-client-secret');
define('HUBSPOT_REDIRECT_URI', site_url('/wp-json/hubspot/v1/oauth/callback'));
define('HUBSPOT_SCOPES', 'crm.objects.line_items.read crm.objects.line_items.write oauth conversations.read conversations.write crm.objects.contacts.write e-commerce sales-email-read crm.objects.companies.write crm.objects.companies.read crm.objects.deals.read crm.objects.deals.write crm.objects.contacts.read');

require_once plugin_dir_path(__FILE__) . 'includes/class-hubspot-wc-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/hubspot-auth.php';
require_once plugin_dir_path(__FILE__) . 'includes/online-order-sync.php';
require_once plugin_dir_path(__FILE__) . 'includes/manual-order-sync.php';
require_once plugin_dir_path(__FILE__) . 'includes/manual-actions.php';
require_once plugin_dir_path(__FILE__) . 'includes/hubspot-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/hubspot-init.php';
require_once plugin_dir_path(__FILE__) . 'includes/hubspot-pipelines.php';
require_once plugin_dir_path(__FILE__) . 'includes/hub-order-management.php';
require_once plugin_dir_path(__FILE__) . 'includes/fetch-object.php';
require_once plugin_dir_path(__FILE__) . 'includes/object-associations.php';
require_once plugin_dir_path(__FILE__) . 'includes/utils.php';

// Activation and deactivation hooks
register_activation_hook(__FILE__, 'hubwoo_activation');
function hubwoo_activation() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hubspot_tokens';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        portal_id BIGINT PRIMARY KEY,
        access_token TEXT NOT NULL,
        refresh_token TEXT NOT NULL,
        expires_at BIGINT NOT NULL
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    if (!wp_next_scheduled('hubspot_token_refresh_event')) {
        wp_schedule_event(time(), 'ten_minutes', 'hubspot_token_refresh_event');
    }
}

register_deactivation_hook(__FILE__, 'hubwoo_deactivation');
function hubwoo_deactivation() {
    wp_clear_scheduled_hook('hubspot_token_refresh_event');
}

HubSpot_WC_Settings::init();
