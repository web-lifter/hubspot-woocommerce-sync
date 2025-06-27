<?php

/**
 * Sync WooCommerce Order to HubSpot Deal Stage
 */
function sync_order_to_hubspot_deal_stage($order, $status_key, $log_prefix = '[HubSpot Sync]') {
    if (get_option('hubspot_pipeline_sync_enabled') !== 'yes') {
        error_log("{$log_prefix} ❌ Sync is disabled in settings.");
        return;
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
        'method'  => 'PATCH',
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
        $order->add_order_note('❌ HubSpot sync failed: ' . print_r($body, true));
    } else {
        error_log("{$log_prefix} ✅ Deal #{$deal_id} updated to stage '{$deal_stage}'");
    }
}

/**
 * Get first stage of pipeline with logging
 */
function hubspot_get_first_stage_of_pipeline($pipeline_id, $access_token) {
    $url = "https://api.hubapi.com/crm/v3/pipelines/deals/{$pipeline_id}";
    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type'  => 'application/json'
        ]
    ]);

    $data = json_decode(wp_remote_retrieve_body($response), true);
    return $data['stages'][0]['id'] ?? '';
}
