<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function get_hubspot_association_type_id($from_type, $to_type) {
    $map = [
        'deal_contact'     => 3,
        'deal_company'     => 5,
        'deal_line_item'   => 19,
        'email_deal'       => 210, // already working
    ];

    $key = "{$from_type}_{$to_type}";
    return $map[$key] ?? null;
}

/**
 * HubSpot object association with logging
 */
function hubspot_associate_objects($from_type, $from_id, $to_type, $to_id, $access_token) {
    $association_type_id = get_hubspot_association_type_id($from_type, $to_type);

    if (!$association_type_id) {
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

    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($payload)
    ]);

    if (is_wp_error($response)) {
    } else {
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);


        $data = json_decode($body, true);
        if (isset($data['status']) && $data['status'] === 'error') {
        } else {
        }
    }
}
