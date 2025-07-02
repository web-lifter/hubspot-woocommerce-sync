<?php
/**
 * Customer invoice email (reformatted like the quote email with bank details and contact info)
 */

if (!defined('ABSPATH')) exit;

do_action('woocommerce_email_header', $email_heading, $email);

// Get order and customer data
if (!isset($order) || !is_a($order, 'WC_Order')) return;
$order_id = $order->get_id();
$order = wc_get_order($order_id);
if (!$order) return;

$shipping_address = $order->get_formatted_shipping_address();
$billing_address = $order->get_formatted_billing_address();
$invoice_number = $order->get_order_number();
$invoice_date = wc_format_datetime($order->get_date_created(), 'j M Y');
$total = wc_price($order->get_total());
$tax_total = wc_price($order->get_total_tax());
$payment_url = esc_url($order->get_checkout_payment_url());
$first_name = $order->get_billing_first_name();
$hubspot_deal_id = get_post_meta($order_id, 'hubspot_deal_id', true);

// Contact fields
$billing_email = $order->get_billing_email();
$billing_phone = $order->get_billing_phone();
$shipping_phone = $order->get_shipping_phone();
?>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f5f5f5; padding: 0;">
  <tr>
    <td align="center">
      <table width="700" cellpadding="0" cellspacing="20" border="0" style="background-color: #ffffff; border-radius: 8px;">

        <!-- Greeting -->
        <tr>
          <td colspan="2" style="padding-bottom: 10px; font-family: Arial, sans-serif; font-size: 16px; color: #000;">
            <p>Dear <?php echo esc_html($first_name); ?>,</p>
            <p>Thank you for your order. Below is your invoice. If you haven’t paid yet, you can use the button at the bottom to proceed with secure payment.</p>
            <p>A PDF Copy of your invoice has been attached to your email, to view all your invoice please visit the my account section via our website.</p>
            <p>Thanks,<br>Steelmark</p>
          </td>
        </tr>

        <tr>
          <td colspan="2" style="border-bottom: 1px solid #ccc; height: 1px; line-height: 1px; font-size: 1px;">&nbsp;</td>
        </tr>

        <!-- Invoice Header -->
        <tr>
          <td align="left" style="width: 50%;">
            <img src="https://steelmark.com.au/wp-content/uploads/steelmark-logo-180.jpg" alt="Steelmark Logo" style="width: 180px; display: block;">
          </td>
          <td align="right" style="width: 50%; font-family: Arial, sans-serif; font-size: 24px; font-weight: bold; color: #000;">
            Invoice: <?php echo esc_html($invoice_number); ?>
          </td>
        </tr>

        <!-- Company & Bank Details -->
        <tr>
          <td colspan="2" style="padding-top: 20px; font-family: Arial, sans-serif; font-size: 14px; color: #000;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
              <tr>
                <!-- Company Info -->
                <td valign="top" width="50%" style="padding-right: 10px;">
                  <strong>Steelmark</strong><br>
                  10 Neon St, Sumner Queensland, Australia<br>
                  4074<br>
                  ABN: 73130494754<br>
                  <a href="https://steelmark.com.au" style="color: #000;">steelmark.com.au</a>
                </td>
                <!-- Bank Info -->
                <td valign="top" width="50%" style="padding-left: 10px;">
                  <strong>Bank Details (EFT):</strong><br>
                  Account Name: Steelmark Pty Ltd<br>
                  BSB: 123-456<br>
                  Account Number: 987654321<br>
                  Bank: Example Bank<br>
                  Reference: <?php echo esc_html($invoice_number); ?>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Billing & Shipping -->
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
                  <strong>Invoice number:</strong> <?php echo esc_html($hubspot_deal_id ?: $invoice_number); ?><br>
                  <strong>Invoice date:</strong> <?php echo esc_html($invoice_date); ?>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Product Table -->
        <tr>
          <td colspan="2" style="padding-top: 20px;">
            <table width="100%" border="1" cellpadding="10" cellspacing="0" style="border-collapse: collapse; font-family: Arial, sans-serif; font-size: 14px; color: #000;">
              <thead style="background-color: #f5f5f5;">
                <tr>
                  <th align="left">Product</th>
                  <th align="left">Qty</th>
                  <th align="left">Unit Price</th>
                  <th align="left">Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($order->get_items() as $item_id => $item): 
                  $product = $item->get_product();
                  $product_name = $product ? $product->get_name() : $item->get_name();
                ?>
                  <tr>
                    <td><?php echo esc_html($product_name); ?></td>
                    <td><?php echo esc_html($item->get_quantity()); ?></td>
                    <td><?php echo wc_price($order->get_item_total($item, false, true)); ?></td>
                    <td><?php echo wc_price($order->get_line_total($item, false, true)); ?></td>
                  </tr>
                <?php endforeach; ?>

                <?php if ($order->get_shipping_total() > 0): ?>
                  <tr>
                    <td><strong>Shipping</strong></td>
                    <td>-</td>
                    <td>-</td>
                    <td><?php echo wc_price($order->get_shipping_total()); ?></td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </td>
        </tr>

        <!-- Totals -->
        <tr>
          <td colspan="2" align="right" style="padding-top: 20px;">
            <table cellpadding="10" cellspacing="1" border="1" width="250" style="border-collapse: collapse; font-family: Arial, sans-serif; font-size: 14px;">
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

        <tr><td colspan="2" align="right" style="padding: 20px;"></td></tr>

        <!-- Pay Now Button -->
        <tr>
          <td colspan="2" align="center" style="padding-top: 30px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
              <tr>
                <td align="center">
                  <table width="700" cellpadding="0" cellspacing="0" border="0" style="cursor: pointer; padding: 10px 0;" role="presentation">
                    <tr>
                      <td bgcolor="#28a745" align="center" style="border-radius: 4px;">
                        <a href="<?php echo $payment_url; ?>"
                          role="button"
                          aria-label="Pay now for this invoice"
                          style="display: block; width: 100%; padding: 10px 0; font-family: Arial, sans-serif; font-size: 16px; color: #ffffff; text-decoration: none; font-weight: bold; text-align: center; border-radius: 4px;">
                          Pay Now
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

<?php do_action('woocommerce_email_footer', $email); ?>
