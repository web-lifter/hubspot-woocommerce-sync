<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Contact creation with logging
 */
function hubspot_get_or_create_contact($order, $email, $access_token) {
    $url = "https://api.hubapi.com/crm/v3/objects/contacts/search";
    $payload = [
        'filterGroups' => [[
            'filters' => [[
                'propertyName' => 'email',
                'operator' => 'EQ',
                'value' => $email
            ]]
        ]]
    ];

    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($payload)
    ]);

    if (is_wp_error($response)) {
        return null;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($data['results'][0]['id'])) return $data['results'][0]['id'];

    $create_url = "https://api.hubapi.com/crm/v3/objects/contacts";
    $payload = [
        'properties' => [
            'email' => $email,
            'firstname' => $order->get_billing_first_name(),
            'lastname' => $order->get_billing_last_name(),
            'phone' => $order->get_billing_phone()
        ]
    ];

    $response = wp_remote_post($create_url, [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($payload)
    ]);

    $created = json_decode(wp_remote_retrieve_body($response), true);
    return $created['id'] ?? null;
}

/**
 * Company creation with logging
 */
function hubspot_get_or_create_company($name, $access_token) {
    $url = "https://api.hubapi.com/crm/v3/objects/companies/search";
    $payload = [
        'filterGroups' => [[
            'filters' => [[
                'propertyName' => 'name',
                'operator' => 'EQ',
                'value' => $name
            ]]
        ]]    $response = wp_remote_post("https://api.hubapi.com/crm/v3/objects/deals", [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($payload)
    ]);

    if (is_wp_error($response)) {
        error_log('[HubSpot] Deal creation request failed: ' . $response->get_error_message());
        return null;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    if ($status_code !== 201 || empty($data['id'])) {
        error_log('[HubSpot] Deal creation failed. Response: ' . $response_body);
        return null;
    }

    return $data['id'];
        ],
        'body' => json_encode($payload)
    ]);

    if (is_wp_error($response)) {
        return null;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($data['results'][0]['id'])) return $data['results'][0]['id'];

    $create_url = "https://api.hubapi.com/crm/v3/objects/companies";
    $payload = ['properties' => ['name' => $name]];

    $response = wp_remote_post($create_url, [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($payload)
    ]);
    $created = json_decode(wp_remote_retrieve_body($response), true);
    return $created['id'] ?? null;
}

/**
 * Deal creation with logging
 */
function hubspot_create_deal_from_order($order, $pipeline_id, $deal_stage, $contact_id, $access_token) {
    $deal_name = 'Order #' . $order->get_id();
    if ($order->get_billing_first_name() || $order->get_billing_last_name()) {
        $deal_name .= ' - ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    }

    $state_map = [
        'ACT' => 'Australian Capital Territory',
        'NSW' => 'New South Whales',
        'NT'  => 'Northern Territory',
        'QLD' => 'Queensland',
        'SA'  => 'South Australia',
        'TAS' => 'Tasmania',
        'VIC' => 'Victoria',
        'WA'  => 'Western Australia'
    ];

    $country_map = [
        'AU' => 'Australia'
    ];

    // Convert billing country/state
    $country_region = $country_map[$order->get_billing_country()] ?? $order->get_billing_country();
    $state = $state_map[$order->get_billing_state()] ?? $order->get_billing_state();

    // Convert shipping country/state
    $country_region_shipping = $country_map[$order->get_shipping_country()] ?? $order->get_shipping_country();
    $state_shipping = $state_map[$order->get_shipping_state()] ?? $order->get_shipping_state();


    $payload = [
        'properties' => [
            'dealname'                => trim($deal_name),
            'amount' => floatval($order->get_total()),
            'shipping' => floatval($order->get_shipping_total()),
            'pipeline'                => $pipeline_id,
            'dealstage'               => $deal_stage,
            'online_order_id'         => (string) $order->get_id(),
            'deal_notes'             => $order->get_customer_note(),

            // Billing
            'address_line_1'          => $order->get_billing_address_1(),
            'city'                    => $order->get_billing_city(),
            'postcode'                => $order->get_billing_postcode(),
            'state'          => $state,
            'country_region' => $country_region,

            // Shipping
            'address_line_1_shipping' => $order->get_shipping_address_1(),
            'city_shipping'           => $order->get_shipping_city(),
            'postcode_shipping'       => $order->get_shipping_postcode(),
            'country_region_shipping' => $country_region_shipping,
            'state_shipping'          => $state_shipping,
            'first_name_shipping'     => $order->get_shipping_first_name(),
            'last_name_shipping'      => $order->get_shipping_last_name(),
            'payway_order_number'     => $order->get_meta('_payway_api_order_number'),
            'phone_shipping' => $order->get_meta('_shipping_phone') ?: $order->get_billing_phone(),

        ]
    ];

    $response = wp_remote_post("https://api.hubapi.com/crm/v3/objects/deals", [
        'headers' => [
            'Authorization' => "Bearer $access_token",
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($payload)
    ]);

    $status_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    $data = json_decode($response_body, true);
    return $data['id'] ?? null;

}