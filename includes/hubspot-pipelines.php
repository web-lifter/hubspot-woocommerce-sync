<?php

/**
 * Sync WooCommerce Order to HubSpot Deal Stage
 */
function sync_order_to_hubspot_deal_stage($order, $status_key, $log_prefix = '[HubSpot Sync]') {
    if (!get_option('hubspot_pipeline_sync_enabled')) {
        error_log("{$log_prefix} ❌ Sync is disabled in settings.");
        return;
    }

    $mapping     = get_option('hubspot_status_stage_mapping', []);
    $deal_stage  = $mapping[$status_key] ?? '';
    $order_id    = $order->get_id();
    $deal_id     = $order->get_meta('hubspot_deal_id');

    if (!$deal_id || !is_numeric($deal_id)) {
        error_log("{$log_prefix} ❌ Invalid or missing deal ID for order #{$order_id}.");
        return;
    }

    $access_token = manage_hubspot_access_token();
    if (!$access_token) {
        error_log("{$log_prefix} ❌ Access token not available.");
        return;
    }

    if (empty($deal_stage)) {
        // Try to fallback to first stage of pipeline
        $pipeline_id = $order->get_meta('hubspot_pipeline_id') ?: get_option('hubspot_pipeline_online');
        $deal_stage = hubspot_get_cached_first_stage_of_pipeline($pipeline_id);

        if (empty($deal_stage)) {
            error_log("{$log_prefix} ⚠️ No stage found for key '{$status_key}', and fallback failed.");
            return;
        }

        error_log("{$log_prefix} ⚠️ Using fallback first stage '{$deal_stage}' from pipeline '{$pipeline_id}'");
    }

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
        $order->add_order_note("✅ HubSpot deal updated to stage '{$deal_stage}'");
    }
}


/**
 * Gets the first stage of a pipeline via live API call (deprecated fallback)
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

/**
 * Gets the first stage of a pipeline from cached settings (preferred)
 */
function hubspot_get_cached_first_stage_of_pipeline($pipeline_id) {
    $pipelines = get_option('hubspot_cached_pipelines', []);

    if (!isset($pipelines[$pipeline_id]['stages'])) {
        error_log("[HubSpot Sync] ⚠️ No cached stages found for pipeline '{$pipeline_id}'");
        return '';
    }

    $stages = $pipelines[$pipeline_id]['stages'];
    $first_stage = array_key_first($stages);

    if (!$first_stage) {
        error_log("[HubSpot Sync] ⚠️ No stages found in cached pipeline '{$pipeline_id}'");
        return '';
    }

    return $first_stage;
}
