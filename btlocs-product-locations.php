<?php
/**
 * Plugin Name: BTLOCS Product Locations
 * Description: Extends YITH WooCommerce Product Add-Ons with a custom admin backend for managing product locations.
 * Version: 1.0.0
 * Author: Your Name
 *
 * Requires Plugins: yith-woocommerce-product-add-ons
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Autoload BTLOCS plugin classes from their respective directories
require_once __DIR__ . '/includes/class-btlocs-db.php';
require_once __DIR__ . '/includes/class-btlocs-ajax.php';
require_once __DIR__ . '/includes/class-btlocs-emails.php';
require_once __DIR__ . '/includes/class-btlocs-cart.php';
require_once __DIR__ . '/admin/class-btlocs-admin-locations.php';
require_once __DIR__ . '/admin/class-btlocs-admin-pricing.php';
require_once __DIR__ . '/public/class-btlocs-frontend-location.php';
require_once __DIR__ . '/public/class-btlocs-frontend-pricing.php';

register_activation_hook( __FILE__, function() {
    BTLOCS_Product_Locations::activate_plugin();
    if (class_exists('BTLOCS_DB')) {
        BTLOCS_DB::create_tables();
    }
});

// Dependency check for WooCommerce and YITH WooCommerce Product Add-Ons
add_action( 'admin_init', function() {
    if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) || ! is_plugin_active( 'yith-woocommerce-product-add-ons/init.php' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p><strong>BTLOCS Product Locations</strong> requires <strong>WooCommerce</strong> and <strong>YITH WooCommerce Product Add-Ons</strong> plugins to be installed and active.</p></div>';
        } );
    }
} );

class BTLOCS_Product_Locations {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            'NBT Product Locations',
            'Product Locations',
            'manage_options',
            'btlocs-manage-locations',
            array( $this, 'redirect_to_locations' ),
            'dashicons-location-alt',
            56
        );
    }

    public function redirect_to_locations() {
        // Directly render the locations UI
        if (class_exists('BTLOCS_Admin_Locations')) {
            $admin_locations = new BTLOCS_Admin_Locations();
            $admin_locations->render();
        }
    }

    public function enqueue_admin_assets( $hook ) {
        // Optionally enqueue admin styles/scripts here
        wp_enqueue_style( 'wp-components' );
        wp_enqueue_style( 'btlocs-admin', plugin_dir_url( __FILE__ ) . 'assets/css/btlocs-admin.css', array(), '1.0.0' );
    }

    public static function activate_plugin() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'btlocs_locations';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            location_name VARCHAR(255) NOT NULL,
            address VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id)
        ) $charset_collate;";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}

new BTLOCS_Product_Locations(); 