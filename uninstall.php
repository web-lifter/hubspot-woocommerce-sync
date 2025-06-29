<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'hubspot_tokens';

// This file runs only when the plugin is deleted. Drop the tokens table
// so all stored OAuth data is removed.
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

?>
