<?php
/*
Plugin Name: HubSpot WooCommerce Sync
Plugin URI: https://github.com/weblifter/hubspot-woocommerce-sync
Description: Sync WooCommerce orders with HubSpot deals using a public HubSpot app.
Version: 1.0.0
Author: Weblifter
Author URI: https://weblifter.com.au
License: GPL-3.0
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin path constants
if ( ! defined( 'HUBSPOT_WC_SYNC_PATH' ) ) {
    define( 'HUBSPOT_WC_SYNC_PATH', plugin_dir_path( __FILE__ ) );
    define( 'HUBSPOT_WC_SYNC_URL', plugin_dir_url( __FILE__ ) );
}

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

