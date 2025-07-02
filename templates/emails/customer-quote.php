<?php
/**
 * Customer quote email template
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($order) || !is_a($order, 'WC_Order')) {
    return;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */

$email_heading = 'Your Steelmark Quote'; // or dynamic if needed
$email = $order->get_billing_email();    // this must be defined before use

do_action( 'woocommerce_email_header', $email_heading, $email );


$order_id = $order->get_id();
$shipping_address = $order->get_formatted_shipping_address();
$billing_address = $order->get_formatted_billing_address();
$quote_number = $order->get_order_number();
$quote_date = wc_format_datetime($order->get_date_created());
$total = wc_price($order->get_total());
$tax_total = wc_price($order->get_total_tax());
$hubspot_deal_id = get_post_meta($order_id, 'hubspot_deal_id', true);
$accept_url = isset($accept_url) ? esc_url($accept_url) : '';
$first_name = $order->get_billing_first_name();

// Contact fields
$billing_email = $order->get_billing_email();
$billing_phone = $order->get_billing_phone();
$shipping_phone = $order->get_meta('_shipping_phone');
?>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f5f5f5; padding: 0;" role="presentation">
  <tr>
    <td align="center">
        <table width="700" cellpadding="0" cellspacing="20" border="0" style="background-color: #ffffff; border-radius: 8px;" role="presentation">
            
            <!-- Header -->
            <tr>
            <td colspan="2" style="padding-bottom: 10px; font-family: Arial, sans-serif; font-size: 16px; color: #000;" role="document">
                <p>Dear <?php echo esc_html($first_name); ?>,</p>
                <p>Thank you for reaching out to us. Below is your quote. Once you accept the quote, you'll be directed to an order overview page where you can proceed with payment. An invoice will be emailed to you after payment has been completed.</p>
                <p>Thanks,<br>Steelmark</p>
            </td>
            </tr>

            <tr>
                <td colspan="2" style="border-bottom: 1px solid #ccc; height: 1px; line-height: 1px; font-size: 1px;" role="separator">&nbsp;</td>
            </tr>

            <!-- Quote Header -->
            <tr>
            <td align="left" style="width: 50%;">
                <img src="https://steelmark.com.au/wp-content/uploads/steelmark-logo-180.jpg" alt="Steelmark Logo" style="width: 180px; display: block;" role="img">
            </td>
            <td align="right" style="width: 50%; font-family: Arial, sans-serif; font-size: 24px; font-weight: bold; color: #000;" role="heading" aria-level="1">
                Quote: <?php echo esc_html($quote_number); ?>
            </td>
            </tr>

            <!-- Company & Bank Details -->
            <tr>
              <td colspan="2" style="padding-top: 20px;" role="contentinfo">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-family: Arial, sans-serif; font-size: 14px; color: #000;" role="presentation">
                  <tr>
                    <!-- Company Info -->
                    <td valign="top" width="50%" style="padding-right: 10px;">
                      <strong>Steelmark</strong><br>
                      10 Neon St, Sumner Queensland, Australia<br>
                      4074<br>
                      ABN: 73130494754<br>
                      <a href="https://steelmark.com.au" style="color: #000;">steelmark.com.au</a>
                    </td>
                    <!-- Bank/EFT Details -->
                    <td valign="top" width="50%" style="padding-left: 10px;">
                      <strong>Bank Details (EFT):</strong><br>
                      Account Name: Steelmark Pty Ltd<br>
                      BSB: 123-456<br>
                      Account Number: 987654321<br>
                      Bank: Example Bank Australia<br>
                      Reference: Use Quote Number
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <!-- Billing and Shipping -->
            <tr>
              <td colspan="2" style="padding-top: 20px;">
                <table width="100%" cellpadding="5" cellspacing="0" border="0" style="font-family: Arial, sans-serif; font-size: 14px; color: #000;" role="presentation">
                  <tr>
                    <td valign="top" width="33%">
                      <strong>Bill to:</strong><br>
                      <?php echo wp_kses_post($billing_address); ?><br>
                      Email: <?php echo esc_html($billing_email); ?><br>
                      Phone: <?php echo esc_html($billing_phone); ?>
                    </td>
                    <td valign="top" width="33%">
                      <strong>Shipping to:</strong><br>
                      <?php echo wp_kses_post($shipping_address); ?><br>
                      Phone: <?php echo esc_html($shipping_phone); ?>
                    </td>
                    <td valign="top" width="33%">
                      <strong>Quote number:</strong> <?php echo esc_html($quote_number); ?><br>
                      <strong>Quote date:</strong> <?php echo esc_html($quote_date); ?>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <!-- Product Table -->
            <tr>
            <td colspan="2" style="padding-top: 20px; padding-bottom: 20px;">
                <table width="100%" border="1" cellpadding="10" cellspacing="0" style="border-collapse: collapse; font-family: Arial, sans-serif; font-size: 14px; color: #000;" role="table">
                <thead style="background-color: #f5f5f5;">
                    <tr>
                    <th align="left" role="columnheader">Product</th>
                    <th align="left" role="columnheader">Qty</th>
                    <th align="left" role="columnheader">Unit Price</th>
                    <th align="left" role="columnheader">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order->get_items() as $item_id => $item) :
                    $product = $item->get_product();
                    $product_name = $product ? $product->get_name() : $item->get_name();
                    ?>
                    <tr role="row">
                    <td role="cell"><?php echo esc_html($product_name); ?></td>
                    <td role="cell"><?php echo esc_html($item->get_quantity()); ?></td>
                    <td role="cell"><?php echo wc_price($order->get_item_total($item, false, true)); ?></td>
                    <td role="cell"><?php echo wc_price($order->get_line_total($item, false, true)); ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if ($order->get_shipping_total() > 0) : ?>
                    <tr role="row">
                    <td role="cell"><strong>Shipping</strong></td>
                    <td role="cell">-</td>
                    <td role="cell">-</td>
                    <td role="cell"><?php echo wc_price($order->get_shipping_total()); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                </table>
            </td>
            </tr>

            <!-- Totals -->
            <tr>
                <td colspan="2" align="right" style="padding-top: 20px;">
                    <table cellpadding="10" cellspacing="1" border="1" width="250" style="border-collapse: collapse; font-family: Arial, sans-serif; font-size: 14px;" role="presentation">
                    <tr>
                        <td>Subtotal:</td>
                        <td><?php echo wc_price($order->get_subtotal()); ?></td>
                    </tr>
                    <tr>
                        <td>GST (10%):</td>
                        <td><?php echo $tax_total; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total:</strong></td>
                        <td><strong><?php echo $total; ?></strong></td>
                    </tr>
                    </table>
                </td>
            </tr>

            <tr>
                <td colspan="2" align="right" style="padding: 20px;"></td>
            </tr>

            <!-- Pay Now Button -->
            <tr>
                <td colspan="2" align="center" style="padding-top: 30px;">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
                        <tr>
                            <td align="center">
                                <table width="700" cellpadding="0" cellspacing="0" border="0" style="cursor: pointer; padding: 10px 0;" role="presentation">
                                    <tr>
                                        <td bgcolor="#28a745" align="center" style="border-radius: 4px;">
                                            <a href="<?php echo $accept_url; ?>"
                                            role="button"
                                            aria-label="Accept Quote and proceed to payment"
                                            style="display: block; width: 100%; padding: 10px 0; font-family: Arial, sans-serif; font-size: 16px; color: #ffffff; text-decoration: none; font-weight: bold; text-align: center; border-radius: 4px;">
                                                Accept Quote
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </td>
  </tr>
</table>


<?php
do_action('woocommerce_email_footer', $email);
?>