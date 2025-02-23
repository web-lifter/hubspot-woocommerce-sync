<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class HubSpot_WC_API {

    /**
     * Get headers with OAuth token for a specific WooCommerce store
     */
    private static function get_headers($store_url) {
        $access_token = HubSpot_WC_Auth::get_access_token($store_url);
        return [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json'
        ];
    }

    /**
     * Fetch a HubSpot deal by ID
     */
    public static function get_deal($store_url, $deal_id) {
        $url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}?associations=contacts,companies,line_items";
        $response = wp_remote_get($url, ['headers' => self::get_headers($store_url)]);

        if (is_wp_error($response)) {
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Fetch a contact from HubSpot by contact ID
     */
    public static function get_contact($store_url, $contact_id) {
        $url = "https://api.hubapi.com/crm/v3/objects/contacts/{$contact_id}";
        $response = wp_remote_get($url, ['headers' => self::get_headers($store_url)]);

        if (is_wp_error($response)) {
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Fetch a company from HubSpot by company ID
     */
    public static function get_company($store_url, $company_id) {
        $url = "https://api.hubapi.com/crm/v3/objects/companies/{$company_id}";
        $response = wp_remote_get($url, ['headers' => self::get_headers($store_url)]);

        if (is_wp_error($response)) {
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Update a HubSpot deal
     */
    public static function update_deal($store_url, $deal_id, $data) {
        $url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}";
        $response = wp_remote_request($url, [
            'method'  => 'PATCH',
            'headers' => self::get_headers($store_url),
            'body'    => json_encode($data)
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Fetch the HubSpot portal ID
     */
    public static function get_portal_id($store_url) {
        $url = "https://api.hubapi.com/oauth/v1/access-tokens/" . HubSpot_WC_Auth::get_access_token($store_url);
        $response = wp_remote_get($url, ['headers' => self::get_headers($store_url)]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['hub_id'] ?? false;
    }
}

?>
