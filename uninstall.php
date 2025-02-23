<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'hubspot_tokens';

// Delete stored OAuth tokens
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

?>
