<?php

/**
 * Register REST API routes for HubSpot OAuth.
 */
add_action('rest_api_init', function () {
    register_rest_route('hubspot/v1', '/start-auth', [
        'methods'             => 'GET',
        'callback'            => 'steelmark_start_hubspot_auth',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);

    register_rest_route('hubspot/v1', '/oauth/callback', [
        'methods'             => 'GET',
        'callback'            => 'steelmark_handle_oauth_callback',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);

    register_rest_route('hubspot/v1', '/get-token', [
        'methods'             => 'GET',
        'callback'            => 'steelmark_get_stored_token',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);
});

/**
 * Register custom cron schedule for 30-minute intervals.
 */
add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['thirty_minutes'])) {
        $schedules['thirty_minutes'] = [
            'interval' => 1800,
            'display'  => __('Every 30 Minutes'),
        ];
    }
    return $schedules;
});

/**
 * Schedule HubSpot token refresh cron event.
 */
function schedule_hubspot_token_refresh() {
    if (!wp_next_scheduled('hubspot_token_refresh_event')) {
        wp_schedule_event(time(), 'thirty_minutes', 'hubspot_token_refresh_event');
    }
}
add_action('wp', 'schedule_hubspot_token_refresh');

/**
 * Hook cron event to token refresh function.
 */
add_action('hubspot_token_refresh_event', 'check_and_refresh_hubspot_token');

/**
 * Checks and refreshes the HubSpot token if needed.
 */
function check_and_refresh_hubspot_token(): bool {
    global $wpdb;
    $table_name = $wpdb->prefix . "hubspot_tokens";

    $token_data = $wpdb->get_row("SELECT * FROM {$table_name} LIMIT 1", ARRAY_A);
    if (!$token_data || empty($token_data['access_token']) || empty($token_data['refresh_token'])) {
        error_log("[HubSpot OAuth] ❌ No valid tokens found in database.");
        return false;
    }

    $expires_at    = $token_data['expires_at'];
    $refresh_token = $token_data['refresh_token'];
    $portal_id     = $token_data['portal_id'];

    if (time() >= ($expires_at - 300)) {
        error_log("[HubSpot OAuth] 🔄 Token is expired or about to expire. Refreshing...");
        $new_access_token = refresh_hubspot_access_token($portal_id, $refresh_token);
        if ($new_access_token) {
            error_log("[HubSpot OAuth] ✅ Token successfully refreshed.");
            return true;
        } else {
            error_log("[HubSpot OAuth] ❌ Failed to refresh access token.");
            return false;
        }
    }

    error_log("[HubSpot OAuth] ✅ Access token is still valid.");
    return true;
}

/**
 * Refreshes the HubSpot access token.
 */
function refresh_hubspot_access_token($portal_id, $refresh_token) {
    global $wpdb, $hubspot_config;
    $table_name = $wpdb->prefix . "hubspot_tokens";

    if (empty($hubspot_config['client_id']) || empty($hubspot_config['client_secret'])) {
        error_log("[HubSpot OAuth] ❌ Missing HubSpot Client ID or Secret in \$hubspot_config.");
        return false;
    }

    error_log("[HubSpot OAuth] 🔄 Refreshing access token for portal: " . $portal_id);

    $response = wp_remote_post("https://api.hubapi.com/oauth/v1/token", [
        'body' => [
            "grant_type"    => "refresh_token",
            "client_id"     => $hubspot_config['client_id'],
            "client_secret" => $hubspot_config['client_secret'],
            "refresh_token" => $refresh_token
        ],
        'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
    ]);

    if (is_wp_error($response)) {
        error_log("[HubSpot OAuth] ❌ Error refreshing token: " . $response->get_error_message());
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($body['access_token'], $body['refresh_token'], $body['expires_in'])) {
        error_log("[HubSpot OAuth] ❌ Failed to retrieve new access token. API Response: " . print_r($body, true));
        return false;
    }

    $new_access_token  = sanitize_text_field($body['access_token']);
    $new_refresh_token = sanitize_text_field($body['refresh_token']);
    $expires_at        = time() + (int) $body['expires_in'];

    $update_result = $wpdb->update(
        $table_name,
        [
            'access_token'  => $new_access_token,
            'refresh_token' => $new_refresh_token,
            'expires_at'    => $expires_at,
        ],
        ['portal_id' => $portal_id],
        ['%s', '%s', '%d'],
        ['%s']
    );

    if ($update_result === false) {
        error_log("[HubSpot OAuth] ❌ Failed to update new token in database.");
        return false;
    }

    error_log("[HubSpot OAuth] ✅ Token successfully refreshed. New expiration time: " . date("Y-m-d H:i:s", $expires_at));
    return $new_access_token;
}

/**
 * Central utility to get a valid token: refresh if needed.
 */
function manage_hubspot_access_token() {
    check_and_refresh_hubspot_token();
    return get_hubspot_access_token();
}

/**
 * Start HubSpot auth flow by redirecting to the authorization URL.
 */
function steelmark_start_hubspot_auth(WP_REST_Request $request) {
    global $hubspot_config;

    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce) {
        $nonce = $request->get_param('_wpnonce');
    }

    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_REST_Response(['error' => 'Invalid nonce'], 403);
    }

    error_log("[HubSpot OAuth] Debugging HubSpot Config: " . print_r($hubspot_config, true));

    if (empty($hubspot_config['client_id']) || empty($hubspot_config['redirect_uri']) || empty($hubspot_config['scopes'])) {
        return new WP_REST_Response(['error' => 'Missing OAuth parameters in hubspot_config'], 400);
    }

    $auth_url = "https://app-ap1.hubspot.com/oauth/authorize?" . http_build_query([
        'client_id'    => $hubspot_config['client_id'],
        'redirect_uri' => $hubspot_config['redirect_uri'],
        'scope'        => $hubspot_config['scopes'],
    ]);

    error_log("[HubSpot OAuth] Redirecting to: " . $auth_url);
    wp_redirect($auth_url);
    exit;
}

/**
 * Handle the OAuth callback from HubSpot.
 */
function steelmark_handle_oauth_callback(WP_REST_Request $request) {
    global $wpdb, $hubspot_config;
    $table_name = $wpdb->prefix . "hubspot_tokens";

    $code = $request->get_param('code');
    if (!$code) {
        return new WP_REST_Response(['error' => 'Missing OAuth code'], 400);
    }

    $response = wp_remote_post('https://api.hubapi.com/oauth/v1/token', [
        'body' => [
            'grant_type'    => 'authorization_code',
            'client_id'     => $hubspot_config['client_id'],
            'client_secret' => $hubspot_config['client_secret'],
            'redirect_uri'  => $hubspot_config['redirect_uri'],
            'code'          => $code
        ],
        'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        'timeout' => 15
    ]);

    if (is_wp_error($response)) {
        return new WP_REST_Response(['error' => 'OAuth request failed'], 500);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body['access_token']) || empty($body['refresh_token']) || empty($body['expires_in'])) {
        return new WP_REST_Response(['error' => 'Failed to obtain access token'], 400);
    }

    $portal_id = get_hubspot_portal_id($body['access_token']);
    if (!$portal_id) {
        return new WP_REST_Response(['error' => 'Failed to determine HubSpot Portal ID'], 400);
    }

    $wpdb->replace($table_name, [
        'portal_id'     => $portal_id,
        'access_token'  => sanitize_text_field($body['access_token']),
        'refresh_token' => sanitize_text_field($body['refresh_token']),
        'expires_at'    => time() + intval($body['expires_in'])
    ], ['%d', '%s', '%s', '%d']);

    wp_redirect(home_url() . '?hubspot_auth=success');
    exit;
}

/**
 * Return stored token for admin view.
 */
function steelmark_get_stored_token(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . "hubspot_tokens";

    if (!current_user_can('manage_options')) {
        return new WP_REST_Response(['error' => 'Unauthorized'], 403);
    }

    $token = $wpdb->get_row("SELECT portal_id FROM {$table_name} LIMIT 1", ARRAY_A);
    if (!$token) {
        return new WP_REST_Response(['status' => 'Not connected'], 200);
    }

    return new WP_REST_Response([
        'status'    => 'Connected',
        'portal_id' => intval($token['portal_id'])
    ], 200);
}

/**
 * Get the latest HubSpot access token.
 */
function get_hubspot_access_token() {
    global $wpdb;
    $table = $wpdb->prefix . "hubspot_tokens";

    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
        error_log("[HubSpot Sync] ❌ Table '{$table}' does not exist.");
        return false;
    }

    $row = $wpdb->get_row("SELECT access_token FROM {$table} LIMIT 1", ARRAY_A);
    if (!$row || empty($row['access_token'])) {
        error_log("[HubSpot Sync] ❌ No access token found in '{$table}'.");
        return false;
    }

    return $row['access_token'];
}

/**
 * Utility to fetch portal ID from access token.
 */
function get_hubspot_portal_id($access_token) {
    $response = wp_remote_get("https://api.hubapi.com/oauth/v1/access-tokens/{$access_token}");
    if (is_wp_error($response)) {
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['hub_id'] ?? false;
}
