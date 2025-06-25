<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --- Hubspot FETCH HELPERS ---

function fetch_hubspot_deal($id) {
    $token = manage_hubspot_access_token();
    $url = "https://api.hubapi.com/crm/v3/objects/deals/{$id}?associations=contacts,companies,line_items&properties=amount,shipping,pipeline,dealstage,address_line_1,city,postcode,state,country_region,address_line_1_shipping,city_shipping,postcode_shipping,state_shipping,country_region_shipping,deal_notes,first_name_shipping,last_name_shipping,phone_shipping";
    
    $res = wp_remote_get($url, ['headers' => ['Authorization' => "Bearer $token"]]);
    if (is_wp_error($res)) return false;

    $body = json_decode(wp_remote_retrieve_body($res), true);
    $props = $body['properties'] ?? [];

    // Load cached pipeline label map
    $labels = get_hubspot_pipeline_and_stage_labels();
    $pipeline_id = $props['pipeline'] ?? '';
    $dealstage_id = $props['dealstage'] ?? '';

    return [
        'amount'     => $props['amount'] ?? 0,
        'shipping'   => $props['shipping'] ?? 0,

        // Save both ID and label
        'pipeline_id'   => $pipeline_id,
        'pipeline'      => $labels['pipelines'][$pipeline_id] ?? $pipeline_id,
        'dealstage_id'  => $dealstage_id,
        'dealstage'     => $labels['stages'][$dealstage_id] ?? $dealstage_id,

        'contacts'   => array_column($body['associations']['contacts']['results'] ?? [], 'id'),
        'companies'  => array_column($body['associations']['companies']['results'] ?? [], 'id'),
        'line_items' => array_column($body['associations']['line items']['results'] ?? [], 'id'),

        'address_line_1' => $props['address_line_1'] ?? '',
        'city' => $props['city'] ?? '',
        'postcode' => $props['postcode'] ?? '',
        'state' => $props['state'] ?? '',
        'country_region' => $props['country_region'] ?? '',

        'address_line_1_shipping' => $props['address_line_1_shipping'] ?? '',
        'city_shipping' => $props['city_shipping'] ?? '',
        'postcode_shipping' => $props['postcode_shipping'] ?? '',
        'state_shipping' => $props['state_shipping'] ?? '',
        'country_region_shipping' => $props['country_region_shipping'] ?? '',

        'deal_notes' => $props['deal_notes'] ?? '',
        'first_name_shipping' => $props['first_name_shipping'] ?? '',
        'last_name_shipping' => $props['last_name_shipping'] ?? '',
        'phone_shipping' => $props['phone_shipping'] ?? '',
    ];
}


function fetch_hubspot_contact($id) {
    $token = manage_hubspot_access_token();
    $url = "https://api.hubapi.com/crm/v3/objects/contacts/{$id}?properties=firstname,lastname,email,phone,address,city,state,zip,country";
    $res = wp_remote_get($url, ['headers' => ['Authorization' => "Bearer $token"]]);
    return is_wp_error($res) ? false : json_decode(wp_remote_retrieve_body($res), true)['properties'] ?? [];
}

function fetch_hubspot_company($id) {
    $token = manage_hubspot_access_token();
    $url = "https://api.hubapi.com/crm/v3/objects/companies/{$id}?properties=name";
    $res = wp_remote_get($url, ['headers' => ['Authorization' => "Bearer $token"]]);
    return is_wp_error($res) ? false : json_decode(wp_remote_retrieve_body($res), true)['properties'] ?? [];
}

function fetch_hubspot_line_item($id) {
    $token = manage_hubspot_access_token();
    $url = "https://api.hubapi.com/crm/v3/objects/line_items/{$id}?properties=name,price,quantity,hs_sku";
    $res = wp_remote_get($url, ['headers' => ['Authorization' => "Bearer $token"]]);
    if (is_wp_error($res)) return false;

    $body = json_decode(wp_remote_retrieve_body($res), true)['properties'] ?? [];

    return [
        'id'       => $id,
        'name'     => $body['name'] ?? 'Unnamed',
        'price'    => floatval($body['price'] ?? 0),
        'quantity' => intval($body['quantity'] ?? 1),
        'sku'      => $body['hs_sku'] ?? 'TEMP-' . rand(1000, 9999)
    ];
}
