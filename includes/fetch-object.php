<?php

if (!defined('ABSPATH')) {
    exit;
}

// --- HubSpot FETCH HELPERS ---

/**
 * Fetch a HubSpot deal by ID with associations and custom properties.
 */
function fetch_hubspot_deal($id)
{
    $token = manage_hubspot_access_token();

    $properties = [
        'amount', 'shipping', 'pipeline', 'dealstage',
        'address_line_1', 'city', 'postcode', 'state', 'country_region',
        'address_line_1_shipping', 'city_shipping', 'postcode_shipping',
        'state_shipping', 'country_region_shipping',
        'deal_notes', 'first_name_shipping', 'last_name_shipping', 'phone_shipping'
    ];

    $url = "https://api.hubapi.com/crm/v3/objects/deals/{$id}"
         . '?associations=contacts,companies,line_items'
         . '&properties=' . implode(',', $properties);

    $res = wp_remote_get($url, [
        'headers' => ['Authorization' => "Bearer $token"]
    ]);

    if (is_wp_error($res)) {
        hubwoo_log("[HubSpot] Failed to fetch deal {$id}: " . $res->get_error_message(), 'error');
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($res), true);
    if (empty($body['properties'])) {
        hubwoo_log("[HubSpot] No deal properties returned for deal {$id}", 'warning');
        return null;
    }

    $props = $body['properties'];
    $labels = get_hubspot_pipeline_and_stage_labels();
    $pipeline_id = $props['pipeline'] ?? '';
    $dealstage_id = $props['dealstage'] ?? '';

    return [
        'amount'       => $props['amount'] ?? 0,
        'shipping'     => $props['shipping'] ?? 0,
        'pipeline_id'  => $pipeline_id,
        'pipeline'     => $labels['pipelines'][$pipeline_id] ?? $pipeline_id,
        'dealstage_id' => $dealstage_id,
        'dealstage'    => $labels['stages'][$dealstage_id] ?? $dealstage_id,

        // Associations
        'contacts'   => array_column($body['associations']['contacts']['results'] ?? [], 'id'),
        'companies'  => array_column($body['associations']['companies']['results'] ?? [], 'id'),
        'line_items' => array_column(
            ($body['associations']['line_items']['results']
                ?? $body['associations']['lineItems']['results']
                ?? []),
            'id'
        ),

        // Billing
        'address_line_1' => $props['address_line_1'] ?? '',
        'city'           => $props['city'] ?? '',
        'postcode'       => $props['postcode'] ?? '',
        'state'          => $props['state'] ?? '',
        'country_region' => $props['country_region'] ?? '',

        // Shipping
        'address_line_1_shipping' => $props['address_line_1_shipping'] ?? '',
        'city_shipping'           => $props['city_shipping'] ?? '',
        'postcode_shipping'       => $props['postcode_shipping'] ?? '',
        'state_shipping'          => $props['state_shipping'] ?? '',
        'country_region_shipping' => $props['country_region_shipping'] ?? '',

        // Custom
        'deal_notes'           => $props['deal_notes'] ?? '',
        'first_name_shipping'  => $props['first_name_shipping'] ?? '',
        'last_name_shipping'   => $props['last_name_shipping'] ?? '',
        'phone_shipping'       => $props['phone_shipping'] ?? '',
    ];
}

/**
 * Fetch a HubSpot contact by ID.
 */
function fetch_hubspot_contact($id)
{
    $token = manage_hubspot_access_token();
    $url = "https://api.hubapi.com/crm/v3/objects/contacts/{$id}?properties=firstname,lastname,email,phone,address,city,state,zip,country";

    $res = wp_remote_get($url, [
        'headers' => ['Authorization' => "Bearer $token"]
    ]);

    if (is_wp_error($res)) {
        hubwoo_log("[HubSpot] Failed to fetch contact {$id}: " . $res->get_error_message(), 'error');
        return null;
    }

    return json_decode(wp_remote_retrieve_body($res), true)['properties'] ?? [];
}

/**
 * Fetch a HubSpot company by ID.
 */
function fetch_hubspot_company($id)
{
    $token = manage_hubspot_access_token();
    $url = "https://api.hubapi.com/crm/v3/objects/companies/{$id}?properties=name";

    $res = wp_remote_get($url, [
        'headers' => ['Authorization' => "Bearer $token"]
    ]);

    if (is_wp_error($res)) {
        hubwoo_log("[HubSpot] Failed to fetch company {$id}: " . $res->get_error_message(), 'error');
        return null;
    }

    return json_decode(wp_remote_retrieve_body($res), true)['properties'] ?? [];
}

/**
 * Fetch a HubSpot line item by ID.
 */
function fetch_hubspot_line_item($id)
{
    $token = manage_hubspot_access_token();
    $url = "https://api.hubapi.com/crm/v3/objects/line_items/{$id}?properties=name,price,quantity,hs_sku,sku";

    $res = wp_remote_get($url, [
        'headers' => ['Authorization' => "Bearer $token"]
    ]);

    if (is_wp_error($res)) {
        hubwoo_log("[HubSpot] Failed to fetch line item {$id}: " . $res->get_error_message(), 'error');
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($res), true);
    $props = $body['properties'] ?? [];
    // Legacy line items may use the 'sku' property, so fall back if hs_sku is missing
    $sku   = $props['hs_sku'] ?? $props['sku'] ?? 'TEMP-' . rand(1000, 9999);

    return [
        'id'       => $id,
        'name'     => $props['name'] ?? 'Unnamed',
        'price'    => floatval($props['price'] ?? 0),
        'quantity' => intval($props['quantity'] ?? 1),
        'sku'      => $sku,
    ];
}

/**
 * Fallback: fetch line item IDs for a deal via v4 associations.
 *
 * @param int $deal_id
 * @return array
 */
function hubwoosync_fetch_line_item_ids_fallback($deal_id)
{
    $token = manage_hubspot_access_token();
    $url   = "https://api.hubapi.com/crm/v4/objects/deals/{$deal_id}/associations/line_items";

    $res = wp_remote_get($url, [
        'headers' => ['Authorization' => "Bearer $token"]
    ]);

    if (is_wp_error($res)) {
        hubwoo_log("[HubSpot] Failed to fetch line item associations for deal {$deal_id}: " . $res->get_error_message(), 'error');
        return [];
    }

    $body = json_decode(wp_remote_retrieve_body($res), true);
    $ids  = [];

    if (!empty($body['results'])) {
        foreach ($body['results'] as $result) {
            if (isset($result['id'])) {
                $ids[] = $result['id'];
            } elseif (isset($result['toObjectId'])) {
                $ids[] = $result['toObjectId'];
            }
        }
    }

    return $ids;
}
