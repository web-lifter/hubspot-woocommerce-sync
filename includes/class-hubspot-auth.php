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

        // Logging for debugging
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

    /**
     * Get OAuth authorization URL
     */
    public static function get_oauth_url($store_url) {
        $vars = include HUBSPOT_WC_SYNC_PATH . 'includes/variables.php';
        return "https://app-ap1.hubspot.com/oauth/authorize?client_id=" . $vars['client_id'] .
               "&redirect_uri=" . urlencode($vars['redirect_uri']) .
               "&scope=" . urlencode($vars['scopes']) .
               "&state=" . urlencode($store_url);
    }

    /**
     * Handle OAuth callback from HubSpot
     */
    public static function handle_oauth_callback() {
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            return new WP_REST_Response(['error' => 'Missing OAuth code or state'], 400);
        }

        $code = sanitize_text_field($_GET['code']);
        $store_url = esc_url_raw($_GET['state']);

        $vars = include HUBSPOT_WC_SYNC_PATH . 'includes/variables.php';

        $response = wp_remote_post('https://api.hubapi.com/oauth/v1/token', [
            'body' => [
                'grant_type' => 'authorization_code',
                'client_id' => $vars['client_id'],
                'client_secret' => $vars['client_secret'],
                'redirect_uri' => $vars['redirect_uri'],
                'code' => $code
            ]
        ]);

        if (is_wp_error($response)) {
            error_log("[HubSpot OAuth] ❌ OAuth request failed: " . $response->get_error_message());
            return new WP_REST_Response(['error' => 'OAuth request failed'], 500);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['access_token']) || !isset($body['refresh_token'])) {
            error_log("[HubSpot OAuth] ❌ Invalid OAuth response: " . print_r($body, true));
            return new WP_REST_Response(['error' => 'Invalid OAuth response'], 500);
        }

        // Store token
        self::update_token($store_url, $body['access_token'], $body['refresh_token'], $body['expires_in']);

        return new WP_REST_Response(['message' => 'OAuth authentication successful'], 200);
    }
}

// Register REST API route for OAuth callback
add_action('rest_api_init', function () {
    register_rest_route('hubspot/v1', '/oauth/callback', [
        'methods' => 'GET',
        'callback' => ['HubSpot_WC_Auth', 'handle_oauth_callback'],
        'permission_callback' => '__return_true'
    ]);
});

?>
