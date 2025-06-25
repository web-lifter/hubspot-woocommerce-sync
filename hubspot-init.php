        'hubwoo_render_combined_order_management_page'


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('admin_menu', function () {
    add_submenu_page(
        'hubspot-woocommerce-sync',
        'Order Management',
        'Order Management',
        'manage_woocommerce',
        'hubspot-order-management',
        'render_combined_order_management_page'
    );

    // New Abandoned Carts Page
    add_submenu_page(
        'hubspot-woocommerce-sync',
        'Abandoned Carts',
        'Abandoned Carts',
        'manage_woocommerce',
        'hubspot-abandoned-carts',
        'render_abandoned_cart_admin_view'
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
