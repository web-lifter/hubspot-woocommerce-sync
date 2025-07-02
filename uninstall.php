<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'hubspot_tokens';

// This file runs only when the plugin is deleted. Drop the tokens table
// so all stored OAuth data is removed.
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Delete plugin options
$options = [
    'hubspot_connected',
    'hubspot_auto_create_deal',
    'hubspot_pipeline_online',
    'hubspot_pipeline_manual',
    'hubspot_pipeline_sync_enabled',
    'hubspot-online-mapping',
    'hubspot-manual-mapping',
    'hubspot-online-deal-stages',
    'hubspot-manual-deal-stages',
    'hubspot_stage_quote_sent_manual',
    'hubspot_stage_quote_sent_online',
    'hubspot_stage_quote_accepted_manual',
    'hubspot_stage_quote_accepted_online',
    'hubspot_stage_invoice_sent_manual',
    'hubspot_stage_invoice_sent_online',
    'hubspot_autocomplete_online_order',
    'hubspot_order_cleanup_status',
    'hubspot_order_cleanup_days',
    'hubspot_cached_pipelines',
    'hubspot_oauth_state',
    'hubspot_status_stage_mapping',
];

foreach ($options as $option) {
    delete_option($option);
}

// Clear scheduled events
wp_clear_scheduled_hook('hubspot_token_refresh_event');
wp_clear_scheduled_hook('hubspot_order_cleanup_event');

?>
