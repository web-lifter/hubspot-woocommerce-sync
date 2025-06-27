<?php
add_action('admin_menu', function () {
    // Order Management Page
    add_submenu_page(
        'hubspot-woocommerce-sync',
        __('Order Management', 'hubspot-woocommerce-sync'),
        __('Order Management', 'hubspot-woocommerce-sync'),
        'manage_woocommerce',
        'hubspot-order-management',
        'render_combined_order_management_page'
    );

    // Abandoned Carts Page
    add_submenu_page(
        'hubspot-woocommerce-sync',
        __('Abandoned Carts', 'hubspot-woocommerce-sync'),
        __('Abandoned Carts', 'hubspot-woocommerce-sync'),
        'manage_woocommerce',
        'hubspot-abandoned-carts',
        'render_abandoned_cart_admin_view'
    );

    // Abandoned Cart Emails Page
    add_submenu_page(
        'hubspot-woocommerce-sync',
        __('Abandoned Cart Emails', 'hubspot-woocommerce-sync'),
        __('Abandoned Cart Emails', 'hubspot-woocommerce-sync'),
        'manage_woocommerce',
        'hubspot-abandoned-cart-emails',
        'render_abandoned_cart_emails_page'
    );

    // Email Templates Page
    add_submenu_page(
        'hubspot-woocommerce-sync',
        __('Email Templates', 'hubspot-woocommerce-sync'),
        __('Email Templates', 'hubspot-woocommerce-sync'),
        'manage_woocommerce',
        'hubspot-email-templates',
        'render_hubspot_email_templates_page'
    );

    // Email Sequences Page
    add_submenu_page(
        'hubspot-woocommerce-sync',
        __('Email Sequences', 'hubspot-woocommerce-sync'),
        __('Email Sequences', 'hubspot-woocommerce-sync'),
        'manage_woocommerce',
        'hubspot-email-sequences',
        'render_abandoned_sequence_builder_page'
    );

    // Email Previews Page
    add_submenu_page(
        'hubspot-woocommerce-sync',
        __('Email Previews', 'hubspot-woocommerce-sync'),
        __('Email Previews', 'hubspot-woocommerce-sync'),
        'manage_woocommerce',
        'hubspot-email-preview',
        'render_email_template_preview_page'
    );
});
