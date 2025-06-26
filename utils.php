function hubwoo_order_type(WC_Order $order) {

function hubwoosync_order_type(WC_Order $order) {
    return $order->get_meta('order_type') ?: 'online';
}

 *
 * @package Steelmark
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function order_type(WC_Order $order) {
    return $order->get_meta('order_type') ?: 'online';
}

