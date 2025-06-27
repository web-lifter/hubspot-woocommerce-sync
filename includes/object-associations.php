<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get HubSpot association type ID based on object types.
 *
 * @param string $from_type
 * @param string $to_type
 * @return int|null
 */
function get_hubspot_association_type_id($from_type, $to_type) {
    $map = [
        'deal_contact'   => 3,
        'deal_company'   => 5,
        'deal_line_item' => 19,
        'email_deal'     => 210,
    ];

    $key = "{$from_type}_{$to_type}";
    return $map[$key] ?? null;
}

/**
 * Associate two HubSpot CRM objects using the v4 batch API.
 *
 * @param string $from_type
 * @param int    $from_id
 * @param string $to_type
 * @param int    $to_id
 * @param string $access_token
 * @return void
 */
function hubspot_associate_objects($from_type, $from_id, $to_type, $to_id, $access_token) {
    $association_type_id = get_hubspot_association_type_id($from_type, $to_type);

    if (!$association_type_id) {
        error_log("[HubSpot Association] ❌ Unknown association type: {$from_type} → {$to_type}");
        return;
    }

    $payload = [
        'inputs' => [[
            'from' => ['id' => (int) $from_id],
            'to'   => ['id' => (int) $to_id],
            'types' => [[
                'associationCategory' => 'HUBSPOT_DEFINED',
                'associationTypeId'   => $association_type_id
            ]]
        ]]
    ];

    $url = "https://api.hubapi.com/crm/v4/associations/{$from_type}/{$to_type}/batch/create";

    error_log("[HubSpot Association] 📡 Requesting association: {$from_type} #{$from_id} → {$to_type} #{$to_id} (type ID: {$association_type_id})");
    error_log("[HubSpot Association] 📦 Payload: " . json_encode($payload));

    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => "Bearer {$access_token}",
            'Content-Type'  => 'application/json'
        ],
        'body' => json_encode($payload)
    ]);

    if (is_wp_error($response)) {
        error_log("[HubSpot Association] ❌ WP Error: " . $response->get_error_message());
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    error_log("[HubSpot Association] 🌐 Response Code: {$code}");
    error_log("[HubSpot Association] 🔍 Response Body: " . print_r($data, true));

    if (isset($data['status']) && $data['status'] === 'error') {
        error_log("[HubSpot Association] ❌ API Error: " . print_r($data, true));
    } else {
        error_log("[HubSpot Association] ✅ Success: {$from_type} #{$from_id} associated with {$to_type} #{$to_id}");
    }
}
