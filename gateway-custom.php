<?php

/**
 * Plugin Name: Paystation Payment Gateway
 * Plugin URI: https://www.paystation.com.bd
 * Description: Paystation payment gateway use properly. If you're first time user then you should read Getting Started section first.
 * Version: 1.0.0
 * Author: Md. Anisur Rahaman
 * Author URI: https://anascloud.blogspot.com
 * Tested up to: 4.6.1
 * Text Domain: anascloud
 * Domain Path: /languages/
 *
 * @package Custom Gateway for WooCommerce
 * @author Md. Anisur Rahaman
 */


if (!defined('WPINC')) {
	die; // if accessed directly
}

// check woocommerce activation
$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
if (!in_array('woocommerce/woocommerce.php', $active_plugins)) {
	return;
}

// Add custom place order button
function add_button_after_place_order()
{
	echo '<button type="button" class="button alt wp-element-button" id="track-order-button" style="display:none;">Place order</button>';
}
add_action('woocommerce_review_order_after_submit', 'add_button_after_place_order');

// Remove default Place order button
// add_filter('woocommerce_order_button_html', 'remove_order_button_html');
// function remove_order_button_html($button)
// {
// 	$button = '';
// 	return $button;
// }

// plugin directory
define('WOO_CUSTOM_PAYMENT_DIR', plugin_dir_path(__FILE__));

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action('plugins_loaded', 'paystation_payment_gateway_init', 0);
function paystation_payment_gateway_init()
{

	// load text domain
	load_plugin_textdomain('paystation_payment_gateway', FALSE, WOO_CUSTOM_PAYMENT_DIR . '/languages/');

	// Lets add it too WooCommerce
	add_filter('woocommerce_payment_gateways', 'paystation_payment_gateway');
	function paystation_payment_gateway($methods)
	{
		$methods[] = 'paystation_payment_gateway';
		return $methods;
	}

	// include extended gateway class 
	include_once('paystation_payment_gateway.php');
}

add_action('wp_enqueue_scripts', 'my_plugin_enqueue_scripts');
function my_plugin_enqueue_scripts()
{
	wp_enqueue_script('paystation', plugin_dir_url(__FILE__) . 'assets/custom.js', array('jquery'), '1.0.0', true);
	wp_enqueue_style('paystation', plugins_url('assets/custom.css', __FILE__), false, '1.0.0', 'all');
}

add_action('woocommerce_after_order_notes', 'my_custom_content');

function my_custom_content()
{
	$payment_gateway_id = 'paystation_payment_gateway';
	$payment_gateways   = WC_Payment_Gateways::instance();
	$payment_gateway    = $payment_gateways->payment_gateways()[$payment_gateway_id];
	echo '<input type="hidden" id="baseurl" value=' . get_site_url()	. ' />';
	echo '<input type="hidden" id="cartTotal" value=' . WC()->cart->total	. ' />';
	echo '<input type="hidden" id="ps_merchant_id" value=' . $payment_gateway->ps_merchant_id . ' />';
	echo '<input type="hidden" id="ps_password" value=' . $payment_gateway->ps_password . ' />';
	echo '<input type="hidden" id="payment_url" value=' . plugin_dir_url(__FILE__) . "payment.php" . ' />';
}

add_action('wp_ajax_complete_order', 'complete_order_callback');
add_action('wp_ajax_nopriv_complete_order', 'complete_order_callback');

function complete_order_callback()
{
    $demo = $_POST["data"];
    $data = array();
    foreach ($demo as $item) {
        $data[$item['name']] = $item['value'];
    }

    $address = array(
        'first_name' => $data["billing_first_name"],
        'last_name'  => $data["billing_last_name"],
        'company'    => $data["billing_company"],
        'email'      => $data["billing_email"],
        'phone'      => $data["billing_phone"],
        'address_1'  => $data["billing_address_1"],
        'address_2'  => $data["billing_address_2"],
        'city'       => $data["billing_city"],
        'state'      => $data["billing_state"],
        'postcode'   => $data["billing_postcode"],
        'country'    => $data["billing_country"],
    );

    // Use wc_create_order to create an order object
    $order = wc_create_order();

    // Set billing address
    $order->set_address($address, 'billing');

    // Set customer details
    $order->set_customer_id(get_current_user_id());
    $order->set_customer_ip_address(WC_Geolocation::get_ip_address());
    $order->set_customer_user_agent(wc_get_user_agent());

    // Add products to the order
    $cart = WC()->cart;
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $quantity = $cart_item['quantity'];
        $order->add_product(get_product($product_id), $quantity);
    }

    // Set payment method
    $payment_gateways = WC()->payment_gateways->payment_gateways();
    $order->set_payment_method($payment_gateways[$data["payment_method"]]);

    // Calculate totals
    $order->calculate_totals();

    // Save order
    $order->save();

    // Get order ID and key
    $order_id = $order->get_id();
    $order_key = get_post_meta($order_id, '_order_key', true);

    // Set customer billing details explicitly
    $order->set_billing_first_name($data["billing_first_name"]);
    $order->set_billing_last_name($data["billing_last_name"]);
    $order->set_billing_company($data["billing_company"]);
    $order->set_billing_address_1($data["billing_address_1"]);
    $order->set_billing_address_2($data["billing_address_2"]);
    $order->set_billing_city($data["billing_city"]);
    $order->set_billing_state($data["billing_state"]);
    $order->set_billing_postcode($data["billing_postcode"]);
    $order->set_billing_country($data["billing_country"]);
    $order->set_billing_email($data["billing_email"]);
    $order->set_billing_phone($data["billing_phone"]);

    // Save changes to the order
    $order->save();

    // Get return URL
    $returnURL = site_url() . '/checkout/order-received/' . $order_id . '/?key=' . $order_key;

    wp_send_json(["success" => true, "order_id" => $order_id, "order_key" => $order_key, "returnURL" => $returnURL]);
}

add_action('woocommerce_thankyou', 'add_thank_you_message');
function add_thank_you_message($order_id)
{
	$payment_status = $_GET['status'];
	$order = wc_get_order($order_id);
	if ($payment_status == 'Successful') {
		$order->update_status('completed');
	}
	$order = wc_get_order($order_id);
}
