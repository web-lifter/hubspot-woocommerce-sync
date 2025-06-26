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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Allow verbose logging when enabled.
if ( ! defined( 'HUBSPOT_WC_DEBUG' ) ) {
    define( 'HUBSPOT_WC_DEBUG', false );
}

// Define plugin path constants
if ( ! defined( 'HUBSPOT_WC_SYNC_PATH' ) ) {
    define( 'HUBSPOT_WC_SYNC_PATH', plugin_dir_path( __FILE__ ) );
    define( 'HUBSPOT_WC_SYNC_URL', plugin_dir_url( __FILE__ ) );
}

// Load translations
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'hubspot-woocommerce-sync', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// Define HubSpot OAuth constants from saved options
if ( ! defined( 'HUBSPOT_CLIENT_ID' ) ) {
    define( 'HUBSPOT_CLIENT_ID', get_option( 'hubspot_client_id', '' ) );
}
if ( ! defined( 'HUBSPOT_CLIENT_SECRET' ) ) {
    define( 'HUBSPOT_CLIENT_SECRET', get_option( 'hubspot_client_secret', '' ) );
}
if ( ! defined( 'HUBSPOT_REDIRECT_URI' ) ) {
    define( 'HUBSPOT_REDIRECT_URI', site_url( '/wp-json/hubspot/v1/oauth/callback' ) );
}

// Include core files
global $hubspot_config;
$hubspot_config = [
    'client_id'     => HUBSPOT_CLIENT_ID,
    'client_secret' => HUBSPOT_CLIENT_SECRET,
    'redirect_uri'  => HUBSPOT_REDIRECT_URI,
    // Scopes needed for the integration
    'scopes'        => 'crm.objects.line_items.read crm.objects.line_items.write oauth conversations.read conversations.write crm.objects.contacts.write e-commerce sales-email-read crm.objects.companies.write crm.objects.companies.read crm.objects.deals.read crm.objects.deals.write crm.objects.contacts.read',
];

require_once HUBSPOT_WC_SYNC_PATH . 'utils.php';
require_once HUBSPOT_WC_SYNC_PATH . 'hubspot-auth.php';
require_once HUBSPOT_WC_SYNC_PATH . 'hubspot-pipelines.php';
require_once HUBSPOT_WC_SYNC_PATH . 'hubspot-functions.php';
require_once HUBSPOT_WC_SYNC_PATH . 'hubspot-init.php';
require_once HUBSPOT_WC_SYNC_PATH . 'hubspot-settings.php';
require_once HUBSPOT_WC_SYNC_PATH . 'manual-actions.php';
require_once HUBSPOT_WC_SYNC_PATH . 'manual-order-sync.php';
require_once HUBSPOT_WC_SYNC_PATH . 'object-associations.php';
require_once HUBSPOT_WC_SYNC_PATH . 'online-order-sync.php';
require_once HUBSPOT_WC_SYNC_PATH . 'send-quote.php';
require_once HUBSPOT_WC_SYNC_PATH . 'create-object.php';
require_once HUBSPOT_WC_SYNC_PATH . 'fetch-object.php';
require_once HUBSPOT_WC_SYNC_PATH . 'hub-order-management.php';

// Kick off the settings hooks
HubSpot_WC_Settings::init();

register_activation_hook( __FILE__, 'hubwoo_activation' );
register_deactivation_hook( __FILE__, 'hubwoo_deactivation' );

/**
 * Plugin activation routine.
 * Creates the hubspot_tokens table and schedules the token refresh event.
 */
function hubwoo_activation() {
    global $wpdb;

    $table_name      = $wpdb->prefix . 'hubspot_tokens';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        portal_id BIGINT(20) UNSIGNED NOT NULL,
        access_token TEXT NOT NULL,
        refresh_token TEXT NOT NULL,
        expires_at BIGINT(20) UNSIGNED NOT NULL,
        PRIMARY KEY  (portal_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    if ( ! wp_next_scheduled( 'hubspot_token_refresh_event' ) ) {
        wp_schedule_event( time(), 'thirty_minutes', 'hubspot_token_refresh_event' );
    }
}

/**
 * Plugin deactivation routine.
 * Clears the scheduled token refresh event.
 */
function hubwoo_deactivation() {
    wp_clear_scheduled_hook( 'hubspot_token_refresh_event' );
}

