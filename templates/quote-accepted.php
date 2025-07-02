<?php
/**
 * Template Name: Quote Accepted
 * Description: Thank-you page styled like WooCommerce's checkout/pay page, showing customer and order details.
 */

defined('ABSPATH') || exit;

get_header();

// Step 1: Get and validate order
$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
$order_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
$order = wc_get_order($order_id);

if (!$order) {
    echo '<div class="woocommerce-error">Sorry, we couldn\'t find your order.</div>';
    get_footer();
    exit;
}


// Step 2: Handle quote acceptance logic
$current_status = $order->get_meta('quote_status');
if ($current_status !== 'Quote Accepted') {
    $order->update_meta_data('quote_status', 'Quote Accepted');
    $order->update_status('pending_payment', 'Quote accepted by customer');

    // Trigger invoice email
    $email_invoice = WC()->mailer()->emails['WC_Email_Customer_Invoice'] ?? null;
    if ($email_invoice) {
        $email_invoice->trigger($order_id);
    }

    // HubSpot deal stage update
    $accepted_stage_id = get_option('hubspot_stage_quote_accepted');
    if ($accepted_stage_id) {
        log_email_in_hubspot($order_id, 'quote_accepted', $accepted_stage_id);
        $order->update_meta_data('hubspot_dealstage_id', $accepted_stage_id);
        $order->save();
    }
}


// Step 3: Display order summary
$billing_first_name = $order->get_billing_first_name();
$billing_last_name = $order->get_billing_last_name();
$billing_email = $order->get_billing_email();
$billing_phone = $order->get_billing_phone();
$billing_address = $order->get_formatted_billing_address();
?>

<div class="woocommerce woocommerce-checkout container">
    <div class="review-section">

        <h2 class="section-title__checkout_notice">Quote Accepted</h2>
        <p class="woocommerce-message">Thank you for accepting your quote. An invoice has been sent to your email. You can complete payment by clicking the button below.</p>

        <div class="checkout-container">

            <!-- Customer Details -->
            <div id="customer_details" class="checkout-form">
                <h3>Billing Details</h3>
                <ul>
                    <li><strong>Name:</strong> <?php echo esc_html("$billing_first_name $billing_last_name"); ?></li>
                    <li><strong>Email:</strong> <?php echo esc_html($billing_email); ?></li>
                    <li><strong>Phone:</strong> <?php echo esc_html($billing_phone); ?></li>
                    <li><strong>Billing Address:</strong> <?php echo wp_kses_post($billing_address); ?></li>
                </ul>

                <h3 style="margin-top: 30px;">Shipping Details</h3>
                <ul>
                    <li><strong>Shipping Name:</strong> <?php echo esc_html($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()); ?></li>
                    <li><strong>Shipping Address:</strong> <?php echo wp_kses_post($order->get_formatted_shipping_address()); ?></li>
                    <?php if ($order->get_shipping_phone()): ?>
                        <li><strong>Shipping Phone:</strong> <?php echo esc_html($order->get_shipping_phone()); ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Order Summary -->
            <div class="woocommerce-checkout-review-order">
                <h3>Order Summary</h3>
                <table class="shop_table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order->get_items() as $item): ?>
                            <tr>
                                <td><?php echo esc_html($item->get_name()); ?> × <?php echo esc_html($item->get_quantity()); ?></td>
                                <td><?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Subtotal</th>
                            <td><?php echo wp_kses_post($order->get_subtotal_to_display()); ?></td>
                        </tr>
                        <?php if ($order->get_shipping_total() > 0): ?>
                            <tr>
                                <th>Shipping</th>
                                <td><?php echo wc_price($order->get_shipping_total()); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="product-total">
                            <th>Total</th>
                            <td><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
                        </tr>
                    </tfoot>
                </table>

                <p>
                    <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" class="button alt">
                        Proceed to Payment
                    </a>
                </p>
            </div>

        </div>
    </div>
</div>

<?php get_footer(); ?>
