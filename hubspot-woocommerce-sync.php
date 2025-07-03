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

// Data retention note: plugin deactivation or update does not remove any
// HubSpot options or the `hubspot_tokens` table. Those are only deleted when
// uninstall.php runs after the plugin is deleted.

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

// Load OAuth credentials from variables.php
$vars_path = HUBSPOT_WC_SYNC_PATH . 'variables.php';
$hubspot_vars = file_exists($vars_path) ? include $vars_path : [];

if (!defined('HUBSPOT_CLIENT_ID')) {
    define('HUBSPOT_CLIENT_ID', $hubspot_vars['client_id'] ?? '');
}
if (!defined('HUBSPOT_CLIENT_SECRET')) {
    define('HUBSPOT_CLIENT_SECRET', $hubspot_vars['client_secret'] ?? '');
}
if (!defined('HUBSPOT_REDIRECT_URI')) {
    define('HUBSPOT_REDIRECT_URI', $hubspot_vars['redirect_uri'] ?? site_url('/wp-json/hubspot/v1/oauth/callback'));
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
require_once HUBSPOT_WC_SYNC_PATH . 'includes/hubspot-properties.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/import-hubspot-deal.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/hubspot-settings.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/manual-actions.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/manual-order-sync.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/object-associations.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/online-order-sync.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/send-quote.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/create-object.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/fetch-object.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/hub-order-management.php';
require_once HUBSPOT_WC_SYNC_PATH . 'includes/order-cleanup.php';

// Initialize plugin settings
HubSpot_WC_Settings::init();

// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'hubwoo_activation');
register_deactivation_hook(__FILE__, 'hubwoo_deactivation');

/**
 * Plugin activation callback.
 * Creates the `hubspot_tokens` table and schedules a token refresh cron.
 * No data is removed during activation or deactivation; stored tokens and
 * options remain intact.
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

    // Ensure legacy stage mapping is migrated to the new option names
    hubwoo_migrate_legacy_stage_mapping();

    if (!wp_next_scheduled('hubspot_token_refresh_event')) {
        wp_schedule_event(time(), 'thirty_minutes', 'hubspot_token_refresh_event');
    }

    if (!wp_next_scheduled('hubspot_order_cleanup_event')) {
        wp_schedule_event(time(), 'daily', 'hubspot_order_cleanup_event');
    }
}

/**
 * Plugin deactivation callback.
 * Clears scheduled cron events. Data and tables are preserved so the
 * plugin can be reactivated without reconnecting.
 */
function hubwoo_deactivation() {
    wp_clear_scheduled_hook('hubspot_token_refresh_event');
    wp_clear_scheduled_hook('hubspot_order_cleanup_event');
}

/**
 * Migrate legacy pipeline stage mapping option to new mapping options.
 */
function hubwoo_migrate_legacy_stage_mapping() {
    $legacy = get_option('hubspot_status_stage_mapping');
    if ($legacy && is_array($legacy)) {
        $online_map = get_option('hubspot-online-mapping', []);
        $manual_map = get_option('hubspot-manual-mapping', []);

        foreach ($legacy as $key => $stage_id) {
            if (strpos($key, 'online_wc-') === 0) {
                $status              = substr($key, strlen('online_wc-'));
                $online_map[$status] = $stage_id;
            } elseif (strpos($key, 'manual_wc-') === 0) {
                $status              = substr($key, strlen('manual_wc-'));
                $manual_map[$status] = $stage_id;
            }
        }

        update_option('hubspot-online-mapping', $online_map);
        update_option('hubspot-manual-mapping', $manual_map);
        delete_option('hubspot_status_stage_mapping');
    }
}

// Trigger migration on each page load in case the plugin was updated without reactivation
add_action('plugins_loaded', 'hubwoo_migrate_legacy_stage_mapping');

/**
 * Initialize default field mappings on first install or upgrade.
 */
function hubwoo_init_default_field_mappings() {
    $deal_defaults = [
        'shipping'                => 'shipping_total',
        'deal_notes'              => 'customer_note',
        'address_line_1'          => 'billing_address_1',
        'city'                    => 'billing_city',
        'postcode'                => 'billing_postcode',
        'state'                   => 'billing_state',
        'country_region'          => 'billing_country',
        'address_line_1_shipping' => 'shipping_address_1',
        'city_shipping'           => 'shipping_city',
        'postcode_shipping'       => 'shipping_postcode',
        'state_shipping'          => 'shipping_state',
        'country_region_shipping' => 'shipping_country',
        'first_name_shipping'     => 'shipping_first_name',
        'last_name_shipping'      => 'shipping_last_name',
        'payway_order_number'     => '_payway_api_order_number',
        'phone_shipping'          => 'shipping_phone',
    ];

    $contact_defaults = [
        'email'     => 'billing_email',
        'firstname' => 'billing_first_name',
        'lastname'  => 'billing_last_name',
        'phone'     => 'billing_phone',
    ];

    $company_defaults = [
        'name' => 'billing_company',
    ];

    if (!get_option('hubspot_deal_field_map')) {
        update_option('hubspot_deal_field_map', $deal_defaults);
    }
    if (!get_option('hubspot_contact_field_map')) {
        update_option('hubspot_contact_field_map', $contact_defaults);
    }
    if (!get_option('hubspot_company_field_map')) {
        update_option('hubspot_company_field_map', $company_defaults);
    }
}
add_action('plugins_loaded', 'hubwoo_init_default_field_mappings');
