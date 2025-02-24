<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class HubSpot_WC_Auth {

    private static $table_name = 'hubspot_tokens';

    /**
     * Create database table for storing OAuth tokens
     */
    public static function install() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table_name;

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id INT NOT NULL AUTO_INCREMENT,
            store_url VARCHAR(255) NOT NULL,
            access_token TEXT NOT NULL,
            refresh_token TEXT NOT NULL,
            expires_at BIGINT NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY store_url (store_url)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Get stored OAuth token for a specific WooCommerce store
     */
    public static function get_token($store_url) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table_name;

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE store_url = %s LIMIT 1", $store_url), ARRAY_A);
    }

    /**
     * Update or insert OAuth token for a store
     */
    public static function update_token($store_url, $access_token, $refresh_token, $expires_in) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table_name;

        error_log("[HubSpot OAuth] 🔄 Storing token for Store: " . $store_url);

        $expires_at = time() + $expires_in;
        $existing = self::get_token($store_url);

        if ($existing) {
            $result = $wpdb->update($table_name, [
                'access_token' => $access_token,
                'refresh_token' => $refresh_token,
                'expires_at' => $expires_at,
            ], ['store_url' => $store_url]);
        } else {
            $result = $wpdb->insert($table_name, [
                'store_url' => $store_url,
                'access_token' => $access_token,
                'refresh_token' => $refresh_token,
                'expires_at' => $expires_at,
            ]);
        }

        if ($result === false) {
            error_log("[HubSpot OAuth] ❌ Database error: " . $wpdb->last_error);
        } else {
            error_log("[HubSpot OAuth] ✅ Token stored successfully for Store: " . $store_url);
        }
    }

    /**
     * Check if stored token is expired
     */
    public static function is_token_expired($store_url) {
        $token = self::get_token($store_url);
        return !$token || time() >= $token['expires_at'];
    }

    /**
     * Refresh OAuth token if expired
     */
    public static function refresh_token($store_url) {
        $token = self::get_token($store_url);
        if (!$token || empty($token['refresh_token'])) {
            return false;
        }

        $vars = include HUBSPOT_WC_SYNC_PATH . 'includes/variables.php';

        $response = wp_remote_post('https://api.hubapi.com/oauth/v1/token', [
            'body' => [
                'grant_type' => 'refresh_token',
                'client_id' => $vars['client_id'],
                'client_secret' => $vars['client_secret'],
                'refresh_token' => $token['refresh_token'],
            ]
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['access_token']) && isset($body['refresh_token'])) {
            self::update_token($store_url, $body['access_token'], $body['refresh_token'], $body['expires_in']);
            return $body['access_token'];
        }

        return false;
    }

    /**
     * Get valid OAuth access token for a store
     */
    public static function get_access_token($store_url) {
        if (self::is_token_expired($store_url)) {
            return self::refresh_token($store_url);
        }

        $token = self::get_token($store_url);
        return $token ? $token['access_token'] : null;
    }
    
    public static function start_hubspot_auth() {
        $store_url = get_site_url(); // Get WooCommerce store URL

        $auth_url = "https://weblifter.com.au/wp-json/hubspot/v1/start-auth?store_url=" . urlencode($store_url);

        wp_redirect($auth_url);
        exit;
    }
}

/**
 * Store OAuth tokens received from Weblifter
 */
function hubspot_store_tokens(WP_REST_Request $request) {
    $params = json_decode($request->get_body(), true);

    error_log("[HubSpot OAuth] 🔄 Receiving token request: " . json_encode($params));

    $access_token = $params['access_token'] ?? null;
    $refresh_token = $params['refresh_token'] ?? null;
    $expires_at = $params['expires_at'] ?? null;
    $portal_id = $params['portal_id'] ?? null;
    $store_url = get_site_url(); // Store URL for reference

    if (!$access_token || !$refresh_token || !$expires_at || !$portal_id) {
        error_log("[HubSpot OAuth] ❌ Missing parameters in /store-token request");
        return new WP_REST_Response(['error' => 'Missing parameters'], 400);
    }

    // Store tokens in WooCommerce database
    HubSpot_WC_Auth::update_token($store_url, $access_token, $refresh_token, $expires_at);

    // Mark connection as successful
    update_option('hubspot_connected', 'yes');

    error_log("[HubSpot OAuth] ✅ Tokens successfully stored in WooCommerce for Portal ID: " . $portal_id);

    return new WP_REST_Response(['message' => 'Tokens stored successfully'], 200);
}


// Register REST API route for token storage
add_action('rest_api_init', function () {
    register_rest_route('hubspot/v1', '/store-token', [
        'methods'  => 'POST',
        'callback' => 'hubspot_store_tokens',
        'permission_callback' => '__return_true',
    ]);
});

?>
