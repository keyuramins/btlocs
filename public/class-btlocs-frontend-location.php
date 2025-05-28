<?php
// Handles frontend location selector and session management for BTLOCS plugin.
class BTLOCS_Frontend_Location {
    public function __construct() {
        add_action('wp_head', array($this, 'inject_location_selector_css'));
        add_action('wp_footer', array($this, 'render_location_selector'));
        add_action('wp_ajax_btlocs_set_location', array($this, 'ajax_set_location'));
        add_action('wp_ajax_nopriv_btlocs_set_location', array($this, 'ajax_set_location'));
    }

    public function inject_location_selector_css() {
        echo '<style>.btlocs-location-selector{position:fixed;top:10px;right:10px;z-index:9999;background:#fff;padding:8px 16px;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.1);}</style>';
    }

    public function render_location_selector() {
        if (is_admin()) return;
        $locations = BTLOCS_DB::get_locations();
        if (empty($locations)) return;
        $current = self::get_current_location_id();
        ?>
        <form class="btlocs-location-selector" id="btlocs-location-selector">
            <label for="btlocs_location">Pickup Location:</label>
            <select name="btlocs_location" id="btlocs_location">
                <?php foreach($locations as $loc): ?>
                    <option value="<?php echo esc_attr($loc['id']); ?>" <?php selected($current, $loc['id']); ?>><?php echo esc_html($loc['location_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <script>
        (function($){
            $('#btlocs_location').on('change', function(){
                var val = $(this).val();
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {action:'btlocs_set_location', location_id:val}, function(){ location.reload(); });
            });
        })(jQuery);
        </script>
        <?php
    }

    public static function get_current_location_id() {
        if (isset($_SESSION['btlocs_location_id'])) {
            return intval($_SESSION['btlocs_location_id']);
        } elseif (isset($_COOKIE['btlocs_location_id'])) {
            return intval($_COOKIE['btlocs_location_id']);
        } else {
            $default = BTLOCS_DB::get_default_location();
            return $default ? intval($default['id']) : 0;
        }
    }

    public function ajax_set_location() {
        $id = intval($_POST['location_id'] ?? 0);
        if ($id) {
            $_SESSION['btlocs_location_id'] = $id;
            setcookie('btlocs_location_id', $id, time()+864000, "/");
        }
        wp_die();
    }
}
new BTLOCS_Frontend_Location(); 