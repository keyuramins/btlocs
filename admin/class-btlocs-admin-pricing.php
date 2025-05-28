<?php
// Handles admin UI and logic for location-based product pricing in BTLOCS plugin.
class BTLOCS_Admin_Pricing {
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_pricing_metabox'));
        add_action('save_post_product', array($this, 'save_product_prices'));
        add_action('wp_ajax_btlocs_get_product_prices', array($this, 'ajax_get_product_prices'));
        add_action('wp_ajax_btlocs_save_product_prices', array($this, 'ajax_save_product_prices'));
    }

    public function add_pricing_metabox() {
        add_meta_box(
            'btlocs_product_pricing',
            'Location-Based Pricing',
            array($this, 'render_pricing_metabox'),
            'product',
            'normal',
            'default'
        );
    }

    public function render_pricing_metabox($post) {
        $product_id = $post->ID;
        $locations = BTLOCS_DB::get_locations();
        $is_variable = (get_post_type($product_id) === 'product' && 'variable' === get_post_field('product_type', $product_id));
        ?>
        <div id="btlocs-pricing-app">
            <table class="widefat">
                <thead><tr><th>Location</th><th>Regular Price</th><th>Sale Price</th></tr></thead>
                <tbody>
                <?php foreach($locations as $loc):
                    $price = $this->get_product_price($product_id, $loc['id']); ?>
                    <tr>
                        <td><?php echo esc_html($loc['location_name']); ?></td>
                        <td><input type="number" step="0.01" min="0" name="btlocs_regular_price[<?php echo $loc['id']; ?>]" value="<?php echo esc_attr($price['regular_price']); ?>" /></td>
                        <td><input type="number" step="0.01" min="0" name="btlocs_sale_price[<?php echo $loc['id']; ?>]" value="<?php echo esc_attr($price['sale_price']); ?>" /></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function get_product_price($product_id, $location_id, $variation_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'btlocs_product_prices';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE product_id = %d AND location_id = %d AND (variation_id IS NULL OR variation_id = 0)", $product_id, $location_id), ARRAY_A);
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
            // Remove old
            $wpdb->delete($table, ['product_id'=>$post_id, 'location_id'=>$location_id, 'variation_id'=>null]);
            // Insert new if set
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