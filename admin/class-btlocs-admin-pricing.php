<?php
// Handles admin UI and logic for location-based product pricing in BTLOCS plugin.
class BTLOCS_Admin_Pricing {
    public function __construct() {
        add_action('edit_form_after_title', array($this, 'render_pricing_metabox_after_title'));
        add_action('save_post_product', array($this, 'save_product_prices'));
        add_action('woocommerce_variation_options_pricing', array($this, 'render_variation_location_pricing'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_location_pricing'), 10, 2);
        add_action('wp_ajax_btlocs_get_product_prices', array($this, 'ajax_get_product_prices'));
        add_action('wp_ajax_btlocs_save_product_prices', array($this, 'ajax_save_product_prices'));
    }

    public function render_pricing_metabox_after_title($post) {
        if ($post->post_type !== 'product') return;
        $product_id = $post->ID;
        $locations = BTLOCS_DB::get_locations();
        echo '<div id="btlocs-pricing-app" style="margin:20px 0;">';
        echo '<h2>Location-Based Pricing</h2>';
        echo '<table class="widefat"><thead><tr><th>Location</th><th>Regular Price</th><th>Sale Price</th></tr></thead><tbody>';
        foreach($locations as $loc) {
            $price = $this->get_product_price($product_id, $loc['id']);
            echo '<tr>';
            echo '<td>' . esc_html($loc['location_name']) . '</td>';
            echo '<td><input type="number" step="0.01" min="0" name="btlocs_regular_price['.esc_attr($loc['id']).']" value="'.esc_attr($price['regular_price']).'" /></td>';
            echo '<td><input type="number" step="0.01" min="0" name="btlocs_sale_price['.esc_attr($loc['id']).']" value="'.esc_attr($price['sale_price']).'" /></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    public function render_variation_location_pricing($loop, $variation_data, $variation) {
        $variation_id = $variation->ID;
        $locations = BTLOCS_DB::get_locations();
        echo '<div class="btlocs-variation-location-pricing" style="margin:10px 0;">';
        echo '<strong>Location-Based Pricing</strong><br />';
        foreach($locations as $loc) {
            $price = $this->get_product_price($variation->post_parent, $loc['id'], $variation_id);
            echo esc_html($loc['location_name']) . ': ';
            echo '<input type="number" step="0.01" min="0" name="btlocs_var_regular_price['.$variation_id.']['.$loc['id'].']" value="'.esc_attr($price['regular_price']).'" placeholder="Regular" style="width:80px;" /> ';
            echo '<input type="number" step="0.01" min="0" name="btlocs_var_sale_price['.$variation_id.']['.$loc['id'].']" value="'.esc_attr($price['sale_price']).'" placeholder="Sale" style="width:80px;" /> ';
            echo '<br />';
        }
        echo '</div>';
    }

    public function save_variation_location_pricing($variation_id, $i) {
        $parent_id = wp_get_post_parent_id($variation_id);
        $locations = BTLOCS_DB::get_locations();
        global $wpdb;
        $table = $wpdb->prefix . 'btlocs_product_prices';
        foreach($locations as $loc) {
            $location_id = $loc['id'];
            $regular = isset($_POST['btlocs_var_regular_price'][$variation_id][$location_id]) ? floatval($_POST['btlocs_var_regular_price'][$variation_id][$location_id]) : null;
            $sale = isset($_POST['btlocs_var_sale_price'][$variation_id][$location_id]) ? floatval($_POST['btlocs_var_sale_price'][$variation_id][$location_id]) : null;
            $wpdb->delete($table, ['product_id'=>$parent_id, 'location_id'=>$location_id, 'variation_id'=>$variation_id]);
            if($regular !== null || $sale !== null) {
                $wpdb->insert($table, [
                    'product_id'=>$parent_id,
                    'location_id'=>$location_id,
                    'regular_price'=>$regular,
                    'sale_price'=>$sale,
                    'variation_id'=>$variation_id
                ]);
            }
        }
        // Fallback: set WooCommerce price meta for default location
        $default_location = BTLOCS_DB::get_default_location();
        if ($default_location) {
            $default_id = $default_location['id'];
            $default_regular = isset($_POST['btlocs_var_regular_price'][$variation_id][$default_id]) ? floatval($_POST['btlocs_var_regular_price'][$variation_id][$default_id]) : '';
            $default_sale = isset($_POST['btlocs_var_sale_price'][$variation_id][$default_id]) ? floatval($_POST['btlocs_var_sale_price'][$variation_id][$default_id]) : '';
            $final_regular = ($default_regular !== '') ? $default_regular : '';
            $final_sale = ($default_sale !== '') ? $default_sale : '';
            update_post_meta($variation_id, '_regular_price', $final_regular);
            update_post_meta($variation_id, '_sale_price', $final_sale);
            update_post_meta($variation_id, '_price', ($final_sale && $final_sale < $final_regular) ? $final_sale : $final_regular);
            wc_delete_product_transients($variation_id);
            // Uncomment for debugging:
            // error_log("[BTLOCS] Saved meta for variation $variation_id: regular=$final_regular, sale=$final_sale");
        }
    }

    public function get_product_price($product_id, $location_id, $variation_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'btlocs_product_prices';
        if ($variation_id) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE product_id = %d AND location_id = %d AND variation_id = %d", $product_id, $location_id, $variation_id), ARRAY_A);
        } else {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE product_id = %d AND location_id = %d AND (variation_id IS NULL OR variation_id = 0)", $product_id, $location_id), ARRAY_A);
        }
        return [
            'regular_price' => $row['regular_price'] ?? '',
            'sale_price' => $row['sale_price'] ?? ''
        ];
    }

    public function save_product_prices($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_product', $post_id)) return;
        if (!isset($_POST['btlocs_regular_price'], $_POST['btlocs_sale_price'])) return;
        $locations = BTLOCS_DB::get_locations();
        global $wpdb;
        $table = $wpdb->prefix . 'btlocs_product_prices';
        foreach($locations as $loc) {
            $location_id = $loc['id'];
            $regular = isset($_POST['btlocs_regular_price'][$location_id]) ? floatval($_POST['btlocs_regular_price'][$location_id]) : null;
            $sale = isset($_POST['btlocs_sale_price'][$location_id]) ? floatval($_POST['btlocs_sale_price'][$location_id]) : null;
            $wpdb->delete($table, ['product_id'=>$post_id, 'location_id'=>$location_id, 'variation_id'=>null]);
            if($regular !== null || $sale !== null) {
                $wpdb->insert($table, [
                    'product_id'=>$post_id,
                    'location_id'=>$location_id,
                    'regular_price'=>$regular,
                    'sale_price'=>$sale,
                    'variation_id'=>null
                ]);
            }
        }
        // Fallback: set WooCommerce price meta to default location's price
        $default_location = BTLOCS_DB::get_default_location();
        if ($default_location) {
            $default_id = $default_location['id'];
            $default_regular = isset($_POST['btlocs_regular_price'][$default_id]) ? floatval($_POST['btlocs_regular_price'][$default_id]) : '';
            $default_sale = isset($_POST['btlocs_sale_price'][$default_id]) ? floatval($_POST['btlocs_sale_price'][$default_id]) : '';
            $final_regular = ($default_regular !== '') ? $default_regular : '';
            $final_sale = ($default_sale !== '') ? $default_sale : '';
            update_post_meta($post_id, '_regular_price', $final_regular);
            update_post_meta($post_id, '_sale_price', $final_sale);
            update_post_meta($post_id, '_price', ($final_sale && $final_sale < $final_regular) ? $final_sale : $final_regular);
            wc_delete_product_transients($post_id);
            // Uncomment for debugging:
            // error_log("[BTLOCS] Saved meta for product $post_id: regular=$final_regular, sale=$final_sale");
        }
    }

    // AJAX handlers for future extensibility (e.g., for variations)
    public function ajax_get_product_prices() {
        // Not implemented in this version
        wp_send_json_error('Not implemented');
    }
    public function ajax_save_product_prices() {
        // Not implemented in this version
        wp_send_json_error('Not implemented');
    }
}
new BTLOCS_Admin_Pricing(); 