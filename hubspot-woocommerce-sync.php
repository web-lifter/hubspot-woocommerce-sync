<?php
/*
Plugin Name: HubSpot WooCommerce Sync
Plugin URI: https://github.com/weblifter/hubspot-woocommerce-sync
Description: Sync WooCommerce orders with HubSpot deals using a public HubSpot app.
Version: 1.0.0
Author: Weblifter
Author URI: https://weblifter.com.au
License: GPL-3.0
Text Domain: hubspot-woocommerce-sync
*/

if (!defined('ABSPATH')) {
    exit;
}

// Enable verbose logging if defined
if (!defined('HUBSPOT_WC_DEBUG')) {
    define('HUBSPOT_WC_DEBUG', false);
}

// Define plugin path constants
if (!defined('HUBSPOT_WC_SYNC_PATH')) {
    define('HUBSPOT_WC_SYNC_PATH', plugin_dir_path(__FILE__));
    define('HUBSPOT_WC_SYNC_URL', plugin_dir_url(__FILE__));
}

// Define plugin slug
if (!defined('HWS_PLUGIN_SLUG')) {
    define('HWS_PLUGIN_SLUG', 'hubspot-woocommerce-sync');
}

// Load plugin translations
add_action('plugins_loaded', function () {
    load_plugin_textdomain('hubspot-woocommerce-sync', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Define HubSpot OAuth constants from options
if (!defined('HUBSPOT_CLIENT_ID')) {
    define('HUBSPOT_CLIENT_ID', get_option('hubspot_client_id', ''));
}
if (!defined('HUBSPOT_CLIENT_SECRET')) {
    define('HUBSPOT_CLIENT_SECRET', get_option('hubspot_client_secret', ''));
}
if (!defined('HUBSPOT_REDIRECT_URI')) {
    define('HUBSPOT_REDIRECT_URI', site_url('/wp-json/hubspot/v1/oauth/callback'));
}

// Prepare configuration for global access
global $hubspot_config;
$hubspot_config = [
    'client_id'     => HUBSPOT_CLIENT_ID,
    'client_secret' => HUBSPOT_CLIENT_SECRET,
    'redirect_uri'  => HUBSPOT_REDIRECT_URI,
    'scopes'        => implode(' ', [
        'crm.objects.line_items.read',
        'crm.objects.line_items.write',
        'oauth',
        'conversations.read',
        'conversations.write',
        'crm.objects.contacts.write',
        'e-commerce',
        'sales-email-read',
        'crm.objects.companies.write',
        'crm.objects.companies.read',
        'crm.objects.deals.read',
        'crm.objects.deals.write',
        'crm.objects.contacts.read',
    ]),
];

// Load includes
require_once HUBSPOT_WC_SYNC_PATH . 'includes/utils.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/hubspot-auth.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/hubspot-pipelines.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/hubspot-functions.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/hubspot-settings.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/manual-actions.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/manual-order-sync.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/object-associations.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/online-order-sync.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/send-quote.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/create-object.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/fetch-object.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/hub-order-management.php';

// Initialize plugin settings
HubSpot_WC_Settings::init();

// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'hubwoo_activation');
register_deactivation_hook(__FILE__, 'hubwoo_deactivation');

/**
 * Plugin activation callback.
 * Creates the `hubspot_tokens` table and schedules a token refresh cron.
 */
function hubwoo_activation() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'hubspot_tokens';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        portal_id BIGINT(20) UNSIGNED NOT NULL,
        access_token TEXT NOT NULL,
        refresh_token TEXT NOT NULL,
        expires_at BIGINT(20) UNSIGNED NOT NULL,
        PRIMARY KEY (portal_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    if (!wp_next_scheduled('hubspot_token_refresh_event')) {
        wp_schedule_event(time(), 'thirty_minutes', 'hubspot_token_refresh_event');
    }
}

/**
 * Plugin deactivation callback.
 * Clears the scheduled token refresh cron.
 */
function hubwoo_deactivation() {
    wp_clear_scheduled_hook('hubspot_token_refresh_event');
}
