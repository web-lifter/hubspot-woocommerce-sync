    register_rest_route('hubspot/v1', '/start-auth', [
        'methods'  => 'GET',
        'callback' => 'steelmark_start_hubspot_auth',
        'permission_callback' => function() { return current_user_can('manage_options'); },
    ]);
add_action('rest_api_init', function () {
    register_rest_route('hubspot/v1', '/start-auth', [
        'methods'  => 'GET',
        'callback' => 'steelmark_start_hubspot_auth',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);

    register_rest_route('hubspot/v1', '/oauth/callback', [
        'methods'  => 'GET',
        'callback' => 'steelmark_handle_oauth_callback',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);

    register_rest_route('hubspot/v1', '/get-token', [
        'methods'  => 'GET',
        'callback' => 'steelmark_get_stored_token',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);
});
use WP_REST_Request;
use WP_REST_Response;

global $wpdb, $hubspot_config;
$table_name = $wpdb->prefix . "hubspot_tokens";
$log_file   = WP_CONTENT_DIR . '/fetchdeal.log';

if (!file_exists($variables_path)) {
    hubwoo_log("[HubSpot OAuth] ❌ Missing variables.php file.", 'error');
    return;
}
if (!file_exists($variables_path)) {
    hubwoo_log("[HubSpot OAuth] ❌ Missing variables.php file.", 'error');
    return;
}
    hubwoo_log("[HubSpot OAuth] ❌ No stored token found.", 'error');
        hubwoo_log("[HubSpot OAuth] ❌ No valid tokens found in database.", 'error');
        hubwoo_log("[HubSpot OAuth] 🔄 Token is expired or about to expire. Refreshing...");
            hubwoo_log("[HubSpot OAuth] ✅ Token successfully refreshed.");
            hubwoo_log("[HubSpot OAuth] ❌ Failed to refresh access token.", 'error');
    hubwoo_log("[HubSpot OAuth] ✅ Access token is still valid.");
    if (empty($hubspot_config['client_id']) || empty($hubspot_config['client_secret'])) {
        hubwoo_log("[HubSpot OAuth] ❌ Missing HubSpot Client ID or Secret in configuration.", 'error');
        return false;
    }

    hubwoo_log("[HubSpot OAuth] 🔄 Refreshing access token for portal: " . $portal_id);
    if (is_wp_error($response)) {
        hubwoo_log("[HubSpot OAuth] ❌ Error refreshing token: " . $response->get_error_message(), 'error');
        return false;
    }
    if (!isset($body['access_token']) || !isset($body['refresh_token']) || !isset($body['expires_in'])) {
        hubwoo_log("[HubSpot OAuth] ❌ Failed to retrieve new access token. API Response: " . print_r($body, true), 'error');
        return false;
    }
    if ($update_result === false) {
        hubwoo_log("[HubSpot OAuth] ❌ Failed to update new token in database.", 'error');
        return false;
    }

    hubwoo_log("[HubSpot OAuth] ✅ Token successfully refreshed. New expiration time: " . date("Y-m-d H:i:s", $expires_at));
    return $new_access_token;
    // Debugging
    hubwoo_log("[HubSpot OAuth] Debugging HubSpot Config: " . print_r($hubspot_config, true));
    hubwoo_log("[HubSpot OAuth] Redirecting to: " . $auth_url);
if (!file_exists($variables_path)) {
    hubwoo_log("[HubSpot OAuth] ❌ Missing variables.php file.", 'error');
    return;
}
    hubwoo_log("[HubSpot OAuth] ❌ No stored token found.", 'error');
    return false;
}
        hubwoo_log("[HubSpot OAuth] ❌ No valid tokens found in database.", 'error');
        hubwoo_log("[HubSpot OAuth] 🔄 Token is expired or about to expire. Refreshing...", 'error');
            hubwoo_log("[HubSpot OAuth] ✅ Token successfully refreshed.", 'error');
            hubwoo_log("[HubSpot OAuth] ❌ Failed to refresh access token.", 'error');
    hubwoo_log("[HubSpot OAuth] ✅ Access token is still valid.", 'error');
        hubwoo_log("[HubSpot OAuth] ❌ Missing HubSpot Client ID or Secret in configuration.", 'error');
    hubwoo_log("[HubSpot OAuth] 🔄 Refreshing access token for portal: " . $portal_id, 'error');
        hubwoo_log("[HubSpot OAuth] ❌ Error refreshing token: " . $response->get_error_message(), 'error');
        hubwoo_log("[HubSpot OAuth] ❌ Failed to retrieve new access token. API Response: " . print_r($body, true), 'error');
        hubwoo_log("[HubSpot OAuth] ❌ Failed to update new token in database.", 'error');
    hubwoo_log("[HubSpot OAuth] ✅ Token successfully refreshed. New expiration time: " . date("Y-m-d H:i:s", $expires_at), 'error');
    hubwoo_log("[HubSpot OAuth] Debugging HubSpot Config: " . print_r($hubspot_config, true), 'error');
    hubwoo_log("[HubSpot OAuth] Redirecting to: " . $auth_url, 'error');
    }
}
add_action('hubspot_token_refresh_event', 'check_and_refresh_hubspot_token');

    $schedules['thirty_minutes'] = [
        'interval' => 1800, // 1800 seconds = 30 minutes
        'display'  => 'Every 30 Minutes'
    ];
    return $schedules;
});

    $token_data = $wpdb->get_row("SELECT * FROM {$table_name} LIMIT 1", ARRAY_A);

    if ($token_data) {
        $portal_id = $token_data['portal_id'] ?? null;
        $access_token = $token_data['access_token'];
        $expires_at = $token_data['expires_at'];
/**
 * Schedule HubSpot token refresh every 30 minutes.
 */
function schedule_hubspot_token_refresh() {
    if (!wp_next_scheduled('hubspot_token_refresh_event')) {
        wp_schedule_event(time(), 'thirty_minutes', 'hubspot_token_refresh_event');
    }
}
            return false;
        }
function refresh_hubspot_access_token($portal_id, $refresh_token) {
    global $wpdb;
    global $hubspot_config; // Ensure access to plugin configuration
    $table_name = $wpdb->prefix . "hubspot_tokens";
            return $access_token;
function refresh_hubspot_access_token($portal_id, $refresh_token) {
    global $wpdb, $hubspot_config;
    $table_name = $wpdb->prefix . "hubspot_tokens";
        return refresh_hubspot_access_token($portal_id, $refresh_token);
    }

    error_log("[HubSpot OAuth] ❌ No stored token found.", 3, $log_file);
    return false;
}

/**
 * Check if HubSpot access token is expired and refresh if needed.
 */
function check_and_refresh_hubspot_token() {
    global $wpdb;
    $table_name = $wpdb->prefix . "hubspot_tokens";

    $token_data = $wpdb->get_row("SELECT * FROM {$table_name} LIMIT 1", ARRAY_A);

    if (!$token_data || empty($token_data['access_token']) || empty($token_data['refresh_token'])) {
        error_log("[HubSpot OAuth] ❌ No valid tokens found in database.");
        return false;
    }

    $access_token = $token_data['access_token'];
    $refresh_token = $token_data['refresh_token'];
    $expires_at = $token_data['expires_at'];
    $portal_id = $token_data['portal_id'];

    // If the token is about to expire in the next 5 minutes, refresh it
    if (time() >= ($expires_at - 300)) { // 300 seconds = 5 minutes buffer
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
 * Schedule HubSpot token refresh every 10 minutes.
 */
function schedule_hubspot_token_refresh() {
    if (!wp_next_scheduled('hubspot_token_refresh_event')) {
        wp_schedule_event(time(), 'ten_minutes', 'hubspot_token_refresh_event');
    }
}
add_action('wp', 'schedule_hubspot_token_refresh');function steelmark_get_stored_token(WP_REST_Request $request) {
    global $wpdb, $table_name;

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

        'interval' => 1800, // 1800 seconds = 30 minutes
        'display' => 'Every 30 Minutes'
    ];
function refresh_hubspot_access_token($portal_id, $refresh_token) {
    global $wpdb;
    $table_name = $wpdb->prefix . "hubspot_tokens";

    // ✅ Ensure hubspot_config is loaded
    $hubspot_config = include get_template_directory() . '/hubspot/variables.php';

    if (empty($hubspot_config['client_id']) || empty($hubspot_config['client_secret'])) {
        error_log("[HubSpot OAuth] ❌ Missing HubSpot Client ID or Secret in configuration.");
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

    if (!isset($body['access_token']) || !isset($body['refresh_token']) || !isset($body['expires_in'])) {
        error_log("[HubSpot OAuth] ❌ Failed to retrieve new access token. API Response: " . print_r($body, true));
        return false;
    }

    $new_access_token = $body['access_token'];
    $new_refresh_token = $body['refresh_token']; // HubSpot sometimes updates refresh tokens
    $expires_at = time() + $body['expires_in'];

    // Update database with new tokens
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
 * Redirect User to HubSpot Authorization URL
 */
function steelmark_start_hubspot_auth(WP_REST_Request $request) {
    global $hubspot_config;
    
    // Debugging
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
 * Handle OAuth Callback from HubSpot
 */
function steelmark_handle_oauth_callback(WP_REST_Request $request) {
    global $wpdb, $table_name, $hubspot_config, $log_file;

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
 * Fetch stored token from MySQL
 */
function steelmark_get_stored_token(WP_REST_Request $request) {
    global $wpdb, $table_name;

    $token = $wpdb->get_row("SELECT * FROM {$table_name} LIMIT 1", ARRAY_A);
    if (!$token) {
        return new WP_REST_Response(['error' => 'Token not found'], 404);
    }

    return new WP_REST_Response($token, 200);
}

/**
 * Get HubSpot Portal ID
 */
function get_hubspot_portal_id($access_token) {
    $response = wp_remote_get("https://api.hubapi.com/oauth/v1/access-tokens/$access_token");
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['hub_id'] ?? false;
}

