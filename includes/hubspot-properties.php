<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fetch HubSpot CRM properties for a specific object type.
 *
 * @param string $object_type deals|contacts|companies|products|line_items
 * @return array property name => ['label' => label, 'type' => type]
 */
function hubwoo_fetch_hubspot_properties($object_type) {
    $token = manage_hubspot_access_token();
    if (!$token) {
        return [];
    }

    $url = "https://api.hubapi.com/crm/v3/properties/{$object_type}";
    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => "Bearer {$token}",
            'Content-Type'  => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        hubwoo_log("[HubSpot] Failed to fetch {$object_type} properties: " . $response->get_error_message(), 'error');
        return [];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $results = $body['results'] ?? [];
    $props = [];

    foreach ($results as $prop) {
        if (!empty($prop['name'])) {
            $props[$prop['name']] = [
                'label' => $prop['label'] ?? $prop['name'],
                'type'  => $prop['type'] ?? ($prop['fieldType'] ?? ''),
            ];
        }
    }

    return $props;
}

/**
 * Refresh cached HubSpot property lists for all object types.
 */
function hubwoo_refresh_property_cache() {
    $mapping = [
        'deals'      => 'hubspot_properties_deals',
        'contacts'   => 'hubspot_properties_contacts',
        'companies'  => 'hubspot_properties_companies',
        'products'   => 'hubspot_properties_products',
        'line_items' => 'hubspot_properties_line_items',
    ];

    foreach ($mapping as $object => $option) {
        $props = hubwoo_fetch_hubspot_properties($object);
        update_option($option, $props);
    }
}
