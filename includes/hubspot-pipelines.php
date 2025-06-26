    if (get_option('hubspot_pipeline_sync_enabled') !== 'yes') {
        hubwoo_log("{$log_prefix} ❌ Sync is disabled in settings.", 'error');
        return;
    }
        hubwoo_log("{$log_prefix} ❌ WP Error: " . $error_message, 'error');
        hubwoo_log("{$log_prefix} ❌ API error: " . $error_message, 'error');
        hubwoo_log("{$log_prefix} ✅ Deal #{$deal_id} updated to stage '{$deal_stage}'");
    if ($deal_stage === '') {
        hubwoo_log("{$log_prefix} ⚠️ HubSpot stage is empty for key '{$status_key}' — skipping.");
        return;
    }
    if (!$deal_id || !is_numeric($deal_id)) {
        hubwoo_log("{$log_prefix} ❌ Invalid or missing deal ID.", 'error');
        return;
    }
    if (!$access_token) {
        hubwoo_log("{$log_prefix} ❌ Access token not found.", 'error');
        return;
    }
    hubwoo_log("{$log_prefix} ✅ Mapped to stage '{$deal_stage}' (status key: '{$status_key}')");
    hubwoo_log("{$log_prefix} 📡 Sending PATCH to: {$update_url}");
    hubwoo_log("{$log_prefix} 📦 Payload: " . json_encode($update_payload));
    if (is_wp_error($response)) {
        hubwoo_log("{$log_prefix} ❌ WP Error: " . $response->get_error_message(), 'error');
        return;
    }
    hubwoo_log("{$log_prefix} 🌐 HubSpot response code: {$code}");
    hubwoo_log("{$log_prefix} 🔍 HubSpot response body: " . print_r($body, true));
        hubwoo_log("{$log_prefix} ❌ API error: " . print_r($body, true), 'error');
    } else {
        hubwoo_log("{$log_prefix} ✅ Deal #{$deal_id} updated to stage '{$deal_stage}'");
    }
        hubwoo_log("[HubSpot Sync] ❌ Table '{$table}' does not exist.", 'error');
        hubwoo_log("[HubSpot Sync] ❌ No access token found in '{$table}'.", 'error');
        hubwoo_log("{$log_prefix} ❌ API error: " . $error_message);
        hubwoo_log("{$log_prefix} ✅ Deal #{$deal_id} updated to stage '{$deal_stage}'");
        hubwoo_log("{$log_prefix} ⚠️ HubSpot stage is empty for key '{$status_key}' — skipping.");
        hubwoo_log("{$log_prefix} ❌ Invalid or missing deal ID.");
        hubwoo_log("{$log_prefix} ❌ Access token not found.");
    hubwoo_log("{$log_prefix} ✅ Mapped to stage '{$deal_stage}' (status key: '{$status_key}')");
    hubwoo_log("{$log_prefix} 📡 Sending PATCH to: {$update_url}");
    hubwoo_log("{$log_prefix} 📦 Payload: " . json_encode($update_payload));
        hubwoo_log("{$log_prefix} ❌ WP Error: " . $response->get_error_message());
    hubwoo_log("{$log_prefix} 🌐 HubSpot response code: {$code}");
    hubwoo_log("{$log_prefix} 🔍 HubSpot response body: " . print_r($body, true));
        hubwoo_log("{$log_prefix} ❌ API error: " . print_r($body, true));
        hubwoo_log("{$log_prefix} ✅ Deal #{$deal_id} updated to stage '{$deal_stage}'");
        hubwoo_log("[HubSpot Sync] ❌ Table '{$table}' does not exist.");
        hubwoo_log("[HubSpot Sync] ❌ No access token found in '{$table}'.");
    hubwoo_log("{$log_prefix} 📦 Payload: " . json_encode($update_payload), 'error');
        hubwoo_log("{$log_prefix} ❌ WP Error: " . $response->get_error_message(), 'error');
    hubwoo_log("{$log_prefix} 🌐 HubSpot response code: {$code}", 'error');
    hubwoo_log("{$log_prefix} 🔍 HubSpot response body: " . print_r($body, true), 'error');
        hubwoo_log("{$log_prefix} ❌ API error: " . print_r($body, true), 'error');
        hubwoo_log("{$log_prefix} ✅ Deal #{$deal_id} updated to stage '{$deal_stage}'", 'error');
        hubwoo_log("[HubSpot Sync] ❌ Table '{$table}' does not exist.", 'error');
        hubwoo_log("[HubSpot Sync] ❌ No access token found in '{$table}'.", 'error');
    if (isset($body['status']) && $body['status'] === 'error') {
        $error_message = print_r($body, true);
        error_log("{$log_prefix} ❌ API error: " . $error_message);
        $order->add_order_note('❌ HubSpot sync failed: ' . $error_message);
    } else {
        error_log("{$log_prefix} ✅ Deal #{$deal_id} updated to stage '{$deal_stage}'");
    }
    $mapping = get_option('hubspot_status_stage_mapping', []);
    $deal_stage = $mapping[$status_key] ?? '';

    if ($deal_stage === '') {
        error_log("{$log_prefix} ⚠️ HubSpot stage is empty for key '{$status_key}' — skipping.");
        return;
    }

    $deal_id = $order->get_meta('hubspot_deal_id');
    if (!$deal_id || !is_numeric($deal_id)) {
        error_log("{$log_prefix} ❌ Invalid or missing deal ID.");
        return;
    }

    $access_token = get_hubspot_access_token();
    if (!$access_token) {
        error_log("{$log_prefix} ❌ Access token not found.");
        return;
    }

    // Log mapping decision
    error_log("{$log_prefix} ✅ Mapped to stage '{$deal_stage}' (status key: '{$status_key}')");

    $update_url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}";
    $update_payload = [
        'properties' => [
            'dealstage' => $deal_stage
        ]
    ];

    error_log("{$log_prefix} 📡 Sending PATCH to: {$update_url}");
    error_log("{$log_prefix} 📦 Payload: " . json_encode($update_payload));

    $response = wp_remote_request($update_url, [
        'method' => 'PATCH',
        'headers' => [
            'Authorization' => "Bearer {$access_token}",
            'Content-Type'  => 'application/json'
        ],
        'body' => json_encode($update_payload)
    ]);

    if (is_wp_error($response)) {
        error_log("{$log_prefix} ❌ WP Error: " . $response->get_error_message());
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    error_log("{$log_prefix} 🌐 HubSpot response code: {$code}");
    error_log("{$log_prefix} 🔍 HubSpot response body: " . print_r($body, true));

    if (isset($body['status']) && $body['status'] === 'error') {
        error_log("{$log_prefix} ❌ API error: " . print_r($body, true));
    } else {
        error_log("{$log_prefix} ✅ Deal #{$deal_id} updated to stage '{$deal_stage}'");
    }
}

/**
 * Determine if an order is admin/manual
 */
function is_order_manual($order) {
    $order_type = $order->get_meta('order_type');
    return $order_type === 'manual';
}

/**
 * Get the latest HubSpot access token
 */
function get_hubspot_access_token() {
    global $wpdb;
    $table = $wpdb->prefix . "hubspot_tokens";

    // Defensive: ensure table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
        error_log("[HubSpot Sync] ❌ Table '{$table}' does not exist.");
        return false;
    }

    // Get the only row (no ORDER BY needed)
    $row = $wpdb->get_row("SELECT access_token FROM {$table} LIMIT 1", ARRAY_A);

    if (!$row || empty($row['access_token'])) {
        error_log("[HubSpot Sync] ❌ No access token found in '{$table}'.");
        return false;
    }

    return $row['access_token'];
}

/**
 * Get first stage of pipeline with logging
 */
function hubspot_get_first_stage_of_pipeline($pipeline_id, $access_token) {
    $url = "https://api.hubapi.com/crm/v3/pipelines/deals/{$pipeline_id}";
    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type' => 'application/json'
        ]
    ]);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return $data['stages'][0]['id'] ?? '';
}