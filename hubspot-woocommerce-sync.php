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

// Include core files
global $hubspot_config;
$hubspot_config = require HUBSPOT_WC_SYNC_PATH . 'variables.php';

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

