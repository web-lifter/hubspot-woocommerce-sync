<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function hubwoosync_order_type( WC_Order $order ) {
    return $order->get_meta( 'order_type' ) ?: 'online';
}

/**
 * Determine if an order is admin/manual
 */
function is_order_manual($order) {
    $order_type = $order->get_meta('order_type');

    if ($order_type === 'manual') {
        return true;
    }

    if (empty($order_type) && $order->get_created_via() === 'admin') {
        return true;
    }

    return false;
}

function hubwoo_log( $message, $level = 'info' ) {
    $debug_enabled = ( defined( 'HUBSPOT_WC_DEBUG' ) && HUBSPOT_WC_DEBUG ) ||
        ( defined( 'WP_DEBUG' ) && WP_DEBUG );

    if ( $debug_enabled ) {
        error_log( $message );
    }
}
/**
 * Get a value from a WooCommerce order using a generic field name.
 * If the field starts with an underscore, it is treated as order meta.
 */
function hubwoo_get_order_field_value($order, $field) {
    if (!$order) return '';

    $state_map = [
        'ACT' => 'Australian Capital Territory',
        'NSW' => 'New South Wales',
        'NT'  => 'Northern Territory',
        'QLD' => 'Queensland',
        'SA'  => 'South Australia',
        'TAS' => 'Tasmania',
        'VIC' => 'Victoria',
        'WA'  => 'Western Australia',
    ];
    $country_map = ['AU' => 'Australia'];

    // Meta fields
    if (strpos($field, '_') === 0) {
        return $order->get_meta($field);
    }

    $method = 'get_' . $field;
    if (method_exists($order, $method)) {
        $value = $order->$method();
    } else {
        $value = $order->get_meta($field);
    }

    // Normalize state and country codes
    if (in_array($field, ['billing_state', 'shipping_state'], true)) {
        return $state_map[$value] ?? $value;
    }
    if (in_array($field, ['billing_country', 'shipping_country'], true)) {
        return $country_map[$value] ?? $value;
    }

    return $value;
}

/**
 * Generic helper to get a value from a WooCommerce data object (product, item).
 */
function hubwoo_get_object_field_value($object, $field) {
    if (!$object) return '';

    if (strpos($field, '_') === 0 && method_exists($object, 'get_meta')) {
        return $object->get_meta($field, true);
    }

    $method = 'get_' . $field;
    if (method_exists($object, $method)) {
        return $object->$method();
    }

    return method_exists($object, 'get_meta') ? $object->get_meta($field, true) : '';
}

/**
 * Set a value on a WooCommerce order using a generic field name.
 * Falls back to order meta when no setter exists.
 */
function hubwoo_set_order_field_value($order, $field, $value) {
    if (!$order) {
        return;
    }

    if ($field === 'shipping_phone') {
        // WooCommerce uses _shipping_phone meta field
        $order->update_meta_data('_shipping_phone', $value);
        return;
    }

    if (strpos($field, '_') === 0) {
        $order->update_meta_data($field, $value);
        return;
    }

    $method = 'set_' . $field;
    if (method_exists($order, $method)) {
        $order->$method($value);
    } else {
        $order->update_meta_data($field, $value);
    }
}

/**
 * Generic helper to set a value on a WooCommerce data object.
 */
function hubwoo_set_object_field_value($object, $field, $value) {
    if (!$object) {
        return;
    }

    if (strpos($field, '_') === 0 && method_exists($object, 'update_meta_data')) {
        $object->update_meta_data($field, $value);
        return;
    }

    $method = 'set_' . $field;
    if (method_exists($object, $method)) {
        $object->$method($value);
    } elseif (method_exists($object, 'update_meta_data')) {
        $object->update_meta_data($field, $value);
    }
}
