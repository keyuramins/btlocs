<?php
// Handles admin UI and AJAX for managing locations in BTLOCS plugin.
class BTLOCS_Admin_Locations {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('wp_ajax_btlocs_get_locations', array($this, 'ajax_get_locations'));
        add_action('wp_ajax_btlocs_add_location', array($this, 'ajax_add_location'));
        add_action('wp_ajax_btlocs_update_location', array($this, 'ajax_update_location'));
        add_action('wp_ajax_btlocs_delete_location', array($this, 'ajax_delete_location'));
        add_action('wp_ajax_btlocs_set_default_location', array($this, 'ajax_set_default_location'));
    }

    public function add_menu_page() {
        add_submenu_page(
            null, // No menu, only accessible via direct link
            'Manage Locations',
            'Manage Locations',
            'manage_options',
            'btlocs-manage-locations',
            array($this, 'render')
        );
    }

    public function render() {
        ?>
        <div class="wrap">
            <h1>Manage Locations</h1>
            <div id="btlocs-locations-app"></div>
            <script>
            // Simple JS app for AJAX CRUD
            (function($){
                function fetchLocations() {
                    $.post(ajaxurl, {action: 'btlocs_get_locations'}, function(data) {
                        $('#btlocs-locations-app').html(data);
                    });
                }
                fetchLocations();
                $(document).on('submit', '#btlocs-add-location-form', function(e){
                    e.preventDefault();
                    $.post(ajaxurl, $(this).serialize()+'&action=btlocs_add_location', function(){ fetchLocations(); });
                });
                $(document).on('click', '.btlocs-delete-location', function(){
                    if(confirm('Delete this location?')){
                        $.post(ajaxurl, {action: 'btlocs_delete_location', id: $(this).data('id')}, function(){ fetchLocations(); });
                    }
                });
                $(document).on('click', '.btlocs-set-default', function(){
                    $.post(ajaxurl, {action: 'btlocs_set_default_location', id: $(this).data('id')}, function(){ fetchLocations(); });
                });
                $(document).on('submit', '.btlocs-edit-location-form', function(e){
                    e.preventDefault();
                    $.post(ajaxurl, $(this).serialize()+'&action=btlocs_update_location', function(){ fetchLocations(); });
                });
            })(jQuery);
            </script>
        </div>
        <?php
    }

    public function ajax_get_locations() {
        $locations = BTLOCS_DB::get_locations();
        ?>
        <table class="widefat">
            <thead><tr><th>Location</th><th>Address</th><th>Email</th><th>Default</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($locations as $loc): ?>
                <tr>
                    <td><?php echo esc_html($loc['location_name']); ?></td>
                    <td><?php echo esc_html($loc['address']); ?></td>
                    <td><?php echo esc_html($loc['email']); ?></td>
                    <td><?php if($loc['is_default']): ?><span style="color:green;font-weight:bold;">Default</span><?php else: ?><button class="button btlocs-set-default" data-id="<?php echo $loc['id']; ?>">Set Default</button><?php endif; ?></td>
                    <td>
                        <form class="btlocs-edit-location-form" style="display:inline-block;">
                            <input type="hidden" name="id" value="<?php echo $loc['id']; ?>" />
                            <input type="text" name="location_name" value="<?php echo esc_attr($loc['location_name']); ?>" required placeholder="Location" />
                            <input type="text" name="address" value="<?php echo esc_attr($loc['address']); ?>" required placeholder="Address" />
                            <input type="email" name="email" value="<?php echo esc_attr($loc['email']); ?>" required placeholder="Email" />
                            <button class="button">Update</button>
                        </form>
                        <button class="button btlocs-delete-location" data-id="<?php echo $loc['id']; ?>">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <h2>Add New Location</h2>
        <form id="btlocs-add-location-form">
            <input type="text" name="location_name" required placeholder="Location" />
            <input type="text" name="address" required placeholder="Address" />
            <input type="email" name="email" required placeholder="Email" />
            <button class="button button-primary">Add Location</button>
        </form>
        <?php
        wp_die();
    }

    public function ajax_add_location() {
        $name = sanitize_text_field($_POST['location_name'] ?? '');
        $address = sanitize_text_field($_POST['address'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        if($name && $address && $email) {
            BTLOCS_DB::create_location($name, $address, $email, false);
        }
        $this->ajax_get_locations();
    }

    public function ajax_update_location() {
        $id = intval($_POST['id'] ?? 0);
        $name = sanitize_text_field($_POST['location_name'] ?? '');
        $address = sanitize_text_field($_POST['address'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        if($id && $name && $address && $email) {
            BTLOCS_DB::update_location($id, $name, $address, $email, false);
        }
        $this->ajax_get_locations();
    }

    public function ajax_delete_location() {
        $id = intval($_POST['id'] ?? 0);
        if($id) {
            BTLOCS_DB::delete_location($id);
        }
        $this->ajax_get_locations();
    }

    public function ajax_set_default_location() {
        $id = intval($_POST['id'] ?? 0);
        if($id) {
            BTLOCS_DB::set_default_location($id);
        }
        $this->ajax_get_locations();
    }
}
new BTLOCS_Admin_Locations(); 