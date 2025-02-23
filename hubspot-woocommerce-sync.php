<?php
/*
Plugin Name: HubSpot WooCommerce Sync
Plugin URI: https://github.com/weblifter/hubspot-woocommerce-sync
Description: Sync WooCommerce orders with HubSpot deals using a public HubSpot app.
Version: 1.0.0
Author: Weblifter
Author URI: https://weblifter.com.au
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define constants for paths
define('HUBSPOT_WC_SYNC_PATH', plugin_dir_path(__FILE__));
define('HUBSPOT_WC_SYNC_URL', plugin_dir_url(__FILE__));

// Include required files
require_once HUBSPOT_WC_SYNC_PATH . 'includes/class-hubspot-auth.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/class-hubspot-api.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/class-wc-sync.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/variables.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/class-hubspot-settings.php';

// Include GitHub Updater
require_once HUBSPOT_WC_SYNC_PATH . 'includes/class-github-updater.php';

// Register REST API routes
add_action('rest_api_init', function () {
    require_once HUBSPOT_WC_SYNC_PATH . 'api/create-order.php';
    require_once HUBSPOT_WC_SYNC_PATH . 'api/update-order.php';
    require_once HUBSPOT_WC_SYNC_PATH . 'api/delete-order.php';
    require_once HUBSPOT_WC_SYNC_PATH . 'api/get-wc-order.php';
    require_once HUBSPOT_WC_SYNC_PATH . 'api/crm-card.php'; // Integrated CRM Card
    require_once HUBSPOT_WC_SYNC_PATH . 'api/fetch-deal-data.php'; // Integrated Fetch Deal API
});


/**
 * Enqueue Admin Scripts & Styles for HubSpot Settings Page
 */
function hubspot_wc_enqueue_admin_assets($hook) {
    if ($hook !== 'toplevel_page_hubspot-woocommerce-sync') {
        return;
    }

    wp_enqueue_style('hubspot-wc-settings-css', HUBSPOT_WC_SYNC_URL . 'assets/css/hubspot-settings.css', [], '1.0.0');
    wp_enqueue_script('hubspot-wc-settings-js', HUBSPOT_WC_SYNC_URL . 'assets/js/hubspot-settings.js', ['jquery'], '1.0.0', true);
}

add_action('admin_enqueue_scripts', 'hubspot_wc_enqueue_admin_assets');

// Plugin activation hook: Creates the necessary database tables
function hubspot_wc_sync_activate() {
    HubSpot_WC_Auth::install();
}
register_activation_hook(__FILE__, 'hubspot_wc_sync_activate');

?>
