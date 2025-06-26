        'hubwoo_render_combined_order_management_page'

    add_submenu_page(
        'hubspot-woocommerce-sync',
        __('Order Management', 'hub-woo-sync'),
        __('Order Management', 'hub-woo-sync'),
        'manage_woocommerce',
        'hubspot-order-management',
        'render_combined_order_management_page'
    );
    add_submenu_page(
        'hubspot-woocommerce-sync',
        __('Abandoned Carts', 'hub-woo-sync'),
        __('Abandoned Carts', 'hub-woo-sync'),
        'manage_woocommerce',
        'hubspot-abandoned-carts',
        'render_abandoned_cart_admin_view'
    );
    add_submenu_page(
        'hubspot-woocommerce-sync',
        __('Abandoned Cart Emails', 'hub-woo-sync'),
        __('Abandoned Cart Emails', 'hub-woo-sync'),
        'manage_woocommerce',
        'hubspot-abandoned-cart-emails',
        'render_abandoned_cart_emails_page'
    );
    add_submenu_page(
        'hubspot-woocommerce-sync',
        __('Email Templates', 'hub-woo-sync'),
        __('Email Templates', 'hub-woo-sync'),
        'manage_woocommerce',
        'hubspot-email-templates',
        'render_hubspot_email_templates_page'
    );
    add_submenu_page(
        'hubspot-woocommerce-sync',
        __('Email Sequences', 'hub-woo-sync'),
        __('Email Sequences', 'hub-woo-sync'),
        'manage_woocommerce',
        'hubspot-email-sequences',
        'render_abandoned_sequence_builder_page'
    );
    add_submenu_page(
        'hubspot-woocommerce-sync',
        __('Email Previews', 'hub-woo-sync'),
        __('Email Previews', 'hub-woo-sync'),
        'manage_woocommerce',
        'hubspot-email-preview',
        'render_email_template_preview_page'
    );

    add_submenu_page(
        'hubspot-woocommerce-sync',
        'Abandoned Cart Emails',
        'Abandoned Cart Emails',
        'manage_woocommerce',
        'hubspot-abandoned-cart-emails',
        'render_abandoned_cart_emails_page'
    );

    add_submenu_page(
        'hubspot-woocommerce-sync',
        'Email Templates',
        'Email Templates',
        'manage_woocommerce',
        'hubspot-email-templates',
        'render_hubspot_email_templates_page'
    );

    add_submenu_page(
        'hubspot-woocommerce-sync',
        'Email Sequences',
        'Email Sequences',
        'manage_woocommerce',
        'hubspot-email-sequences',
        'render_abandoned_sequence_builder_page'
    );

    add_submenu_page(
        'hubspot-woocommerce-sync',
        'Email Previews',
        'Email Previews',
        'manage_woocommerce',
        'hubspot-email-preview',
        'render_email_template_preview_page'
    );
});
