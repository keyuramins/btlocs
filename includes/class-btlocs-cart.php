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
        // Filter shipping methods and label
        add_filter('woocommerce_package_rates', array($this, 'filter_shipping_methods_to_location'), 99, 2);
        add_filter('woocommerce_shipping_package_name', array($this, 'rename_shipping_label'), 99, 3);
        add_filter('woocommerce_cart_shipping_method_full_label', array($this, 'rename_shipping_method_label'), 99, 2);
        add_filter('woocommerce_cart_totals_shipping_method_label', array($this, 'rename_shipping_method_label'), 99, 2);
        // Ensure correct price on order line item
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'set_order_line_item_price'), 99, 4);
        // Remove WooCommerce's default shipping destination string from cart/checkout totals
        add_filter('woocommerce_cart_shipping_method_full_label', function($label, $method) {
            if (strpos($label, 'Shipping to') !== false) {
                return '';
            }
            return $label;
        }, 100, 2);
        add_filter('woocommerce_cart_totals_shipping_destination_html', '__return_empty_string', 100);
    }

    public function set_cart_item_prices($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        $location_id = BTLOCS_Frontend_Location::get_current_location_id();
        if (!$location_id) return;
        global $wpdb;
        $table = $wpdb->prefix . 'btlocs_product_prices';
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if ($cart_item_key === array_key_first($cart->get_cart())) {
                error_log('[BTLOCS] DEBUG cart_item array: ' . print_r($cart_item, true));
            }
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
            $location_price = ($sale && $sale < $regular) ? $sale : $regular;

            // Get YITH add-on price for this cart item (if any)
            $addon_price = 0;
            if (isset($cart_item['yith_wapo_total_options_price'])) {
                $addon_price = floatval($cart_item['yith_wapo_total_options_price']);
            } elseif (isset($cart_item['yith_wapo_addons_price'])) {
                $addon_price = floatval($cart_item['yith_wapo_addons_price']);
            } elseif (isset($cart_item['data']) && method_exists($cart_item['data'], 'get_meta')) {
                $meta_addon_price = $cart_item['data']->get_meta('yith_wapo_addons_price', true);
                if ($meta_addon_price !== '') {
                    $addon_price = floatval($meta_addon_price);
                }
            }

            // Combine location price and add-on price
            $final_price = ($location_price !== null ? $location_price : $product->get_price()) + $addon_price;
            $product->set_price($final_price);
            // Set price and totals directly on the cart item for WooCommerce calculations
            $cart->cart_contents[$cart_item_key]['data']->set_price($final_price);
            $cart->cart_contents[$cart_item_key]['line_total'] = $final_price * $cart_item['quantity'];
            $cart->cart_contents[$cart_item_key]['line_subtotal'] = $final_price * $cart_item['quantity'];
            error_log('[BTLOCS] set_cart_item_prices: product_id=' . $product_id . ', location_price=' . $location_price . ', addon_price=' . $addon_price . ', final_price=' . $final_price);
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
            echo '<p><strong>Pick-up From:</strong> ' . esc_html($location_name) . ' - ' . esc_html($location_address) . '</p>';
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

    /**
     * Filter shipping methods to only show the selected location as a pick-up option.
     */
    public function filter_shipping_methods_to_location($rates, $package) {
        $location_id = BTLOCS_Frontend_Location::get_current_location_id();
        if (!$location_id) return $rates;
        $location = BTLOCS_DB::get_location($location_id);
        error_log('[BTLOCS] Filtering shipping methods for location: ' . print_r($location, true));
        // Remove all rates and add only one for the selected location
        $new_rates = array();
        if ($location) {
            $rate_id = 'btlocs_pickup_' . $location_id;
            $rate = new WC_Shipping_Rate(
                $rate_id,
                'Pick-up: ' . $location['address'],
                0,
                array(),
                'btlocs_pickup'
            );
            $new_rates[$rate_id] = $rate;
        }
        return !empty($new_rates) ? $new_rates : $rates;
    }

    /**
     * Rename the shipping label to 'Pick-up Address'.
     */
    public function rename_shipping_label($package_name, $index, $package) {
        $location_id = BTLOCS_Frontend_Location::get_current_location_id();
        $location = BTLOCS_DB::get_location($location_id);
        $label = __('Pick-up Address', 'btlocs');
        if ($location) {
            $label .= ': ' . $location['address'];
        }
        error_log('[BTLOCS] Renaming shipping label to: ' . $label);
        return $label;
    }

    /**
     * Rename the shipping method label in cart/checkout.
     */
    public function rename_shipping_method_label($label, $method) {
        if (!is_string($label) || $label === null) {
            return $label;
        }
        if (strpos($label, 'Pick-up:') !== false || strpos($label, 'Pickup:') !== false) {
            $label = str_replace(['Pick-up:', 'Pickup:'], __('Pick-up Address:', 'btlocs'), $label);
        }
        error_log('[BTLOCS] Renaming shipping method label to: ' . $label);
        return $label;
    }

    /**
     * Ensure the correct price (location + add-on) is set on the order line item.
     */
    public function set_order_line_item_price($item, $cart_item_key, $values, $order) {
        global $wpdb;
        $location_id = BTLOCS_Frontend_Location::get_current_location_id();
        $table = $wpdb->prefix . 'btlocs_product_prices';
        $product = $values['data'];
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
        $location_price = ($sale && $sale < $regular) ? $sale : $regular;

        // Get YITH add-on price for this cart item (if any)
        $addon_price = 0;
        if (isset($values['yith_wapo_total_options_price'])) {
            $addon_price = floatval($values['yith_wapo_total_options_price']);
        } elseif (isset($values['yith_wapo_addons_price'])) {
            $addon_price = floatval($values['yith_wapo_addons_price']);
        } elseif (isset($values['data']) && method_exists($values['data'], 'get_meta')) {
            $meta_addon_price = $values['data']->get_meta('yith_wapo_addons_price', true);
            if ($meta_addon_price !== '') {
                $addon_price = floatval($meta_addon_price);
            }
        }

        $final_price = ($location_price !== null ? $location_price : $product->get_price()) + $addon_price;
        $item->set_subtotal($final_price * $item->get_quantity());
        $item->set_total($final_price * $item->get_quantity());
        $item->add_meta_data('_btlocs_final_price', $final_price, true);
        error_log('[BTLOCS] set_order_line_item_price: product_id=' . $product_id . ', location_price=' . $location_price . ', addon_price=' . $addon_price . ', final_price=' . $final_price);
    }
}
new BTLOCS_Cart(); 