<?php

/**
 * Sync WooCommerce Order to HubSpot Deal Stage
 */
function sync_order_to_hubspot_deal_stage($order, $status_key, $log_prefix = '[HubSpot Sync]') {
    if (!get_option('hubspot_pipeline_sync_enabled')) {
        error_log("{$log_prefix} ❌ Sync is disabled in settings.");
        return;
    }

    $order_id   = $order->get_id();
    $deal_id    = $order->get_meta('hubspot_deal_id');
    $order_type = strtolower($order->get_meta('order_type'));

    // Fallback in case meta is missing
    if (empty($order_type)) {
        $order_type = strtolower(get_post_meta($order_id, 'order_type', true));
    }

    if (!$deal_id || !is_numeric($deal_id)) {
        error_log("{$log_prefix} ❌ Invalid or missing deal ID for order #{$order_id}.");
        return;
    }

    // Load correct mapping based on order_type
    if ($order_type === 'manual') {
        $mapping = get_option('hubspot-manual-mapping', []);
        $pipeline_id = get_option('hubspot_pipeline_manual');
        $deal_stages = get_option('hubspot-manual-deal-stages', []);
    } elseif ($order_type === 'online') {
        $mapping = get_option('hubspot-online-mapping', []);
        $pipeline_id = get_option('hubspot_pipeline_online');
        $deal_stages = get_option('hubspot-online-deal-stages', []);
    } else {
        error_log("{$log_prefix} ⚠️ Unknown or missing order_type '{$order_type}' on order #{$order_id}.");
        $mapping = [];
        $pipeline_id = get_option('hubspot_pipeline_online');
        $deal_stages = get_option('hubspot-online-deal-stages', []);
    }

    // Normalize WooCommerce status (e.g. wc-completed → completed)
    $status = $status_key;
    if (strpos($status, 'online_wc-') === 0) {
        $status = substr($status, strlen('online_wc-'));
    } elseif (strpos($status, 'manual_wc-') === 0) {
        $status = substr($status, strlen('manual_wc-'));
    } elseif (strpos($status, 'wc-') === 0) {
        $status = substr($status, strlen('wc-'));
    }


    $deal_stage = $mapping[$status] ?? '';

    if (empty($deal_stage)) {
        error_log("{$log_prefix} ℹ️ No mapped stage for status '{$status}', attempting fallback.");

        $deal_stage = array_key_first($deal_stages);

        if (!$deal_stage) {
            error_log("{$log_prefix} ⚠️ No deal stages available for pipeline '{$pipeline_id}' — cannot sync.");
            return;
        }

        error_log("{$log_prefix} ⚠️ Using fallback first stage '{$deal_stage}' from pipeline '{$pipeline_id}'");
    }

    $access_token = manage_hubspot_access_token();
    if (!$access_token) {
        error_log("{$log_prefix} ❌ Access token not available.");
        return;
    }

    // Send PATCH to HubSpot
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
 * Gets the first stage of a pipeline via live API call (deprecated)
 */
function hubspot_get_first_stage_of_pipeline($pipeline_id, $access_token) {
    $url = "https://api.hubapi.com/crm/v3/pipelines/deals/{$pipeline_id}";
    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => "Bearer {$access_token}",
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
    $manual_pipeline = get_option('hubspot_pipeline_manual');
    $online_pipeline = get_option('hubspot_pipeline_online');

    if ($pipeline_id === $manual_pipeline) {
        $stages = get_option('hubspot-manual-deal-stages', []);
    } else {
        $stages = get_option('hubspot-online-deal-stages', []);
    }

    $first_stage = array_key_first($stages);

    if (!$first_stage) {
        error_log("[HubSpot Sync] ⚠️ No stages found for pipeline '{$pipeline_id}'");
        return '';
    }

    return $first_stage;
}
