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

    // Create new contact dynamically from mapping
    $create_url = "https://api.hubapi.com/crm/v3/objects/contacts";
    $field_map   = get_option('hubspot_contact_field_map', []);
    $properties  = [];

    foreach ($field_map as $prop => $field) {
        $properties[$prop] = hubwoo_get_order_field_value($order, $field);
    }

    // Ensure required email property is set
    if (empty($properties['email'])) {
        $properties['email'] = $email;
    }

    $create_payload = [ 'properties' => $properties ];

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
function hubspot_get_or_create_company($order, $name, $access_token, &$error_out = null) {
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

    // Create company dynamically from mapping
    $create_url  = "https://api.hubapi.com/crm/v3/objects/companies";
    $field_map   = get_option('hubspot_company_field_map', []);
    $properties  = [];

    foreach ($field_map as $prop => $field) {
        // For company creation we only have order context
        $properties[$prop] = hubwoo_get_order_field_value($order, $field);
    }

    if (empty($properties['name'])) {
        $properties['name'] = $name;
    }

    $create_payload = ['properties' => $properties];

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

    // Step 1: create deal with required fields and mapped properties
    $properties = [
        'dealname'        => trim($deal_name),
        'amount'          => floatval($order->get_total()),
        'pipeline'        => $pipeline_id,
        'dealstage'       => $deal_stage,
        'online_order_id' => (string) $order->get_id(),
    ];

    $field_map = get_option('hubspot_deal_field_map', []);
    foreach ($field_map as $prop => $field) {
        if (isset($properties[$prop])) continue; // don't overwrite required fields
        $properties[$prop] = hubwoo_get_order_field_value($order, $field);
    }

    $create_payload = [ 'properties' => $properties ];

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
    $field_map = get_option('hubspot_deal_field_map', []);
    $fields    = [];

    foreach ($field_map as $prop => $woo_field) {
        $fields[$prop] = hubwoo_get_order_field_value($order, $woo_field);
    }

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
