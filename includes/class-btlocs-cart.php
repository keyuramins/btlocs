<?php
// Handles cart and checkout integration for location-specific pricing in BTLOCS plugin.
class BTLOCS_Cart {
    public function __construct() {
        add_action('woocommerce_before_calculate_totals', array($this, 'set_cart_item_prices'), 99);
        add_action('init', array($this, 'maybe_empty_cart_on_location_change'));
        add_action('woocommerce_checkout_create_order', array($this, 'save_location_to_order'), 10, 2);
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_location_in_order'), 10, 1);
        add_action('woocommerce_email_after_order_table', array($this, 'display_location_in_order'), 10, 1);
        // YITH Product Add-Ons integration
        add_filter('yith_wapo_product_price', array($this, 'yith_location_base_price'), 99, 2);
        add_filter('yith_wapo_product_price_new', array($this, 'yith_location_base_price'), 99, 2);
    }

    public function set_cart_item_prices($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        $location_id = BTLOCS_Frontend_Location::get_current_location_id();
        if (!$location_id) return;
        global $wpdb;
        $table = $wpdb->prefix . 'btlocs_product_prices';
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $product_id = $product->get_id();
            $variation_id = $product->is_type('variation') ? $product_id : null;
            if ($product->is_type('variation')) {
                $parent_id = $product->get_parent_id();
                $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE product_id = %d AND location_id = %d AND variation_id = %d", $parent_id, $location_id, $variation_id), ARRAY_A);
            } else {
                $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE product_id = %d AND location_id = %d AND (variation_id IS NULL OR variation_id = 0)", $product_id, $location_id), ARRAY_A);
            }
            $regular = isset($row['regular_price']) ? floatval($row['regular_price']) : null;
            $sale = isset($row['sale_price']) ? floatval($row['sale_price']) : null;
            if ($sale && $sale < $regular) {
                $product->set_price($sale);
            } elseif ($regular !== null) {
                $product->set_price($regular);
            }
        }
    }

    public function maybe_empty_cart_on_location_change() {
        if (!is_admin() && isset($_POST['action']) && $_POST['action'] === 'btlocs_set_location') {
            if (function_exists('WC') && WC()->cart) {
                WC()->cart->empty_cart();
            }
        }
    }

    public function save_location_to_order($order, $data) {
        $location_id = BTLOCS_Frontend_Location::get_current_location_id();
        if ($location_id) {
            $location = BTLOCS_DB::get_location($location_id);
            if ($location) {
                $order->update_meta_data('_btlocs_location_id', $location_id);
                $order->update_meta_data('_btlocs_location_name', $location['location_name']);
                $order->update_meta_data('_btlocs_location_address', $location['address']);
            }
        }
    }

    public function display_location_in_order($order) {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }
        $location_name = $order->get_meta('_btlocs_location_name');
        $location_address = $order->get_meta('_btlocs_location_address');
        if ($location_name && $location_address) {
            echo '<p><strong>Pickup Location:</strong> ' . esc_html($location_name) . ' - ' . esc_html($location_address) . '</p>';
        }
    }

    /**
     * Ensure YITH add-on base price is location-aware
     */
    public function yith_location_base_price($price, $product) {
        $location_id = BTLOCS_Frontend_Location::get_current_location_id();
        if (!$location_id) return $price;
        global $wpdb;
        $table = $wpdb->prefix . 'btlocs_product_prices';
        $product_id = $product->get_id();
        $variation_id = $product->is_type('variation') ? $product_id : null;
        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE product_id = %d AND location_id = %d AND variation_id = %d", $parent_id, $location_id, $variation_id), ARRAY_A);
        } else {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE product_id = %d AND location_id = %d AND (variation_id IS NULL OR variation_id = 0)", $product_id, $location_id), ARRAY_A);
        }
        $regular = isset($row['regular_price']) ? floatval($row['regular_price']) : null;
        $sale = isset($row['sale_price']) ? floatval($row['sale_price']) : null;
        if ($sale && $sale < $regular) {
            return $sale;
        } elseif ($regular !== null) {
            return $regular;
        }
        return $price;
    }
}
new BTLOCS_Cart(); 