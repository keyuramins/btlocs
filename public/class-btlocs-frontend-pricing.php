<?php
// Handles frontend display of location-based prices, sale badges, and strikethroughs for BTLOCS plugin.
class BTLOCS_Frontend_Pricing {
    public function __construct() {
        add_filter('woocommerce_get_price_html', array($this, 'price_html'), 99, 2);
        add_filter('woocommerce_sale_flash', array($this, 'sale_flash'), 99, 3);
        // Add these filters for location-based pricing
        add_filter('woocommerce_product_get_price', array($this, 'location_price'), 99, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'location_regular_price'), 99, 2);
        add_filter('woocommerce_product_get_sale_price', array($this, 'location_sale_price'), 99, 2);
    }

    public function price_html($price, $product) {
        // Only for frontend
        if (is_admin()) return $price;
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
        $addon_price = 0;
        // Try to get add-on price from cart context
        if (isset($GLOBALS['woocommerce']->cart)) {
            foreach ($GLOBALS['woocommerce']->cart->get_cart() as $cart_item) {
                if ($cart_item['product_id'] == $product_id) {
                    if (isset($cart_item['yith_wapo_total_options_price'])) {
                        $addon_price = floatval($cart_item['yith_wapo_total_options_price']);
                    } elseif (isset($cart_item['yith_wapo_addons_price'])) {
                        $addon_price = floatval($cart_item['yith_wapo_addons_price']);
                    }
                    break;
                }
            }
        }
        $final_display_price = ($sale && $sale < $regular) ? $sale : $regular;
        if ($final_display_price !== null) {
            $final_display_price += $addon_price;
            return '<ins>' . wc_price($final_display_price) . '</ins>';
        }
        return $price;
    }

    public function sale_flash($html, $post, $product) {
        // Only show sale badge if there is a location-based sale price
        $location_id = BTLOCS_Frontend_Location::get_current_location_id();
        if (!$location_id) return $html;
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
            return $html; // Show sale badge
        }
        return '';
    }

    public function location_price($price, $product) {
        if (is_admin()) return $price;
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

    public function location_regular_price($price, $product) {
        if (is_admin()) return $price;
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
        return isset($row['regular_price']) ? floatval($row['regular_price']) : $price;
    }

    public function location_sale_price($price, $product) {
        if (is_admin()) return $price;
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
        return isset($row['sale_price']) ? floatval($row['sale_price']) : $price;
    }
}
new BTLOCS_Frontend_Pricing(); 