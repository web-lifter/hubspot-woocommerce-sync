<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get or create a HubSpot contact.
 */
function hubspot_get_or_create_contact($order, $email, $access_token, &$error_out = null) {
    $search_url = "https://api.hubapi.com/crm/v3/objects/contacts/search";
    $search_payload = [
        'filterGroups' => [[
            'filters' => [[
                'propertyName' => 'email',
                'operator' => 'EQ',
                'value' => $email
            ]]
        ]]
    ];

    $response = wp_remote_post($search_url, [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type'  => 'application/json'
        ],
        'body' => json_encode($search_payload)
    ]);

    if (is_wp_error($response)) {
        $error_out = 'Contact search failed: ' . $response->get_error_message();
        hubwoo_log('[HubSpot] ' . $error_out, 'error');
        return null;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($data['results'][0]['id'])) {
        return $data['results'][0]['id'];
    }

    // Create new contact
    $create_url = "https://api.hubapi.com/crm/v3/objects/contacts";
    $create_payload = [
        'properties' => [
            'email'     => $email,
            'firstname' => $order->get_billing_first_name(),
            'lastname'  => $order->get_billing_last_name(),
            'phone'     => $order->get_billing_phone()
        ]
    ];

    $response = wp_remote_post($create_url, [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type'  => 'application/json'
        ],
        'body' => json_encode($create_payload)
    ]);

    if (is_wp_error($response)) {
        $error_out = 'Contact creation failed: ' . $response->get_error_message();
        hubwoo_log('[HubSpot] ' . $error_out, 'error');
        return null;
    }

    $created = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($created['id'])) {
        $error_out = 'Contact creation returned empty ID.';
        hubwoo_log('[HubSpot] ' . $error_out, 'error');
    }

    return $created['id'] ?? null;
}

/**
 * Get or create a HubSpot company.
 */
function hubspot_get_or_create_company($name, $access_token, &$error_out = null) {
    $search_url = "https://api.hubapi.com/crm/v3/objects/companies/search";
    $search_payload = [
        'filterGroups' => [[
            'filters' => [[
                'propertyName' => 'name',
                'operator' => 'EQ',
                'value' => $name
            ]]
        ]]
    ];

    $response = wp_remote_post($search_url, [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type'  => 'application/json'
        ],
        'body' => json_encode($search_payload)
    ]);

    if (is_wp_error($response)) {
        $error_out = 'Company search failed: ' . $response->get_error_message();
        hubwoo_log('[HubSpot] ' . $error_out, 'error');
        return null;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($data['results'][0]['id'])) {
        return $data['results'][0]['id'];
    }

    // Create company
    $create_url = "https://api.hubapi.com/crm/v3/objects/companies";
    $create_payload = ['properties' => ['name' => $name]];

    $response = wp_remote_post($create_url, [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type'  => 'application/json'
        ],
        'body' => json_encode($create_payload)
    ]);

    if (is_wp_error($response)) {
        $error_out = 'Company creation failed: ' . $response->get_error_message();
        hubwoo_log('[HubSpot] ' . $error_out, 'error');
        return null;
    }

    $created = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($created['id'])) {
        $error_out = 'Company creation returned empty ID.';
        hubwoo_log('[HubSpot] ' . $error_out, 'error');
    }

    return $created['id'] ?? null;
}

/**
 * Create a HubSpot deal from WooCommerce order with fallback-safe logic.
 */
function hubspot_create_deal_from_order($order, $pipeline_id, $deal_stage, $contact_id, $access_token, &$error_out = null) {
    $deal_name = 'Order #' . $order->get_id();
    if ($order->get_billing_first_name() || $order->get_billing_last_name()) {
        $deal_name .= ' - ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    }

    // Step 1: create deal with only required fields
    $create_payload = [
        'properties' => [
            'dealname'        => trim($deal_name),
            'amount'          => floatval($order->get_total()),
            'pipeline'        => $pipeline_id,
            'dealstage'       => $deal_stage,
            'online_order_id' => (string) $order->get_id()
        ]
    ];

    $response = wp_remote_post("https://api.hubapi.com/crm/v3/objects/deals", [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type'  => 'application/json'
        ],
        'body' => json_encode($create_payload)
    ]);

    if (is_wp_error($response)) {
        $error_out = 'Deal creation request failed: ' . $response->get_error_message();
        hubwoo_log('[HubSpot] ' . $error_out, 'error');
        return null;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    $deal_id = $data['id'] ?? null;

    if ($status_code !== 201 || empty($deal_id)) {
        $error_out = 'Deal creation failed. Status: ' . $status_code . '. Response: ' . wp_remote_retrieve_body($response);
        hubwoo_log('[HubSpot] ' . $error_out, 'error');
        return null;
    }

    // Step 2: Patch optional fields after creation
    hubspot_patch_deal_optional_fields($deal_id, $order, $access_token);

    return $deal_id;
}

/**
 * Patch optional deal fields to avoid creation failures.
 */
function hubspot_patch_deal_optional_fields($deal_id, $order, $access_token) {
    $state_map = [ 'ACT'=>'Australian Capital Territory','NSW'=>'New South Wales','NT'=>'Northern Territory','QLD'=>'Queensland','SA'=>'South Australia','TAS'=>'Tasmania','VIC'=>'Victoria','WA'=>'Western Australia' ];
    $country_map = ['AU' => 'Australia'];

    $fields = [
        'shipping'                => floatval($order->get_shipping_total()),
        'deal_notes'              => $order->get_customer_note(),
        'address_line_1'          => $order->get_billing_address_1(),
        'city'                    => $order->get_billing_city(),
        'postcode'                => $order->get_billing_postcode(),
        'state'                   => $state_map[$order->get_billing_state()] ?? $order->get_billing_state(),
        'country_region'          => $country_map[$order->get_billing_country()] ?? $order->get_billing_country(),
        'address_line_1_shipping' => $order->get_shipping_address_1(),
        'city_shipping'           => $order->get_shipping_city(),
        'postcode_shipping'       => $order->get_shipping_postcode(),
        'state_shipping'          => $state_map[$order->get_shipping_state()] ?? $order->get_shipping_state(),
        'country_region_shipping' => $country_map[$order->get_shipping_country()] ?? $order->get_shipping_country(),
        'first_name_shipping'     => $order->get_shipping_first_name(),
        'last_name_shipping'      => $order->get_shipping_last_name(),
        'payway_order_number'     => $order->get_meta('_payway_api_order_number'),
        'phone_shipping'          => $order->get_shipping_phone() ?: $order->get_billing_phone(),
    ];

    $patch_payload = ['properties' => $fields];

    $response = wp_remote_request("https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}", [
        'method'  => 'PATCH',
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type'  => 'application/json'
        ],
        'body' => json_encode($patch_payload)
    ]);

    if (is_wp_error($response)) {
        hubwoo_log('[HubSpot] ⚠️ Optional field update failed: ' . $response->get_error_message(), 'warning');
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        hubwoo_log("[HubSpot] ⚠️ Optional field update failed. Code: $code", 'warning');
        return false;
    }

    return true;
}
