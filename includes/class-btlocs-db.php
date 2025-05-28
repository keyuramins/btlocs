<?php
// RULE: All global variables/constants (e.g., table names, option keys) for the BTLOCS plugin must be defined here and used across all plugin files for consistency.

// Global constants
if ( ! defined( 'BTLOCS_TABLE_LOCATIONS' ) ) {
    define( 'BTLOCS_TABLE_LOCATIONS', $GLOBALS['wpdb']->prefix . 'btlocs_locations' );
}
// Add more global constants as needed (e.g., for pricing, user preferences)

// Handles all database operations for BTLOCS plugin.
class BTLOCS_DB {
    /**
     * Create a new location. All fields are required.
     *
     * @param string $name
     * @param string $address
     * @param string $email
     * @param bool $is_default
     * @return int|false Inserted row ID or false on failure
     */
    public static function create_location( $name, $address, $email, $is_default = false ) {
        global $wpdb;
        if ( empty( $name ) || empty( $address ) || empty( $email ) ) {
            return false;
        }
        if ( $is_default ) {
            self::unset_default_location();
        }
        $result = $wpdb->insert(
            BTLOCS_TABLE_LOCATIONS,
            [
                'location_name' => $name,
                'address'       => $address,
                'email'         => $email,
                'is_default'    => $is_default ? 1 : 0,
            ],
            [ '%s', '%s', '%s', '%d' ]
        );
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get all locations.
     *
     * @return array
     */
    public static function get_locations() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM " . BTLOCS_TABLE_LOCATIONS . " ORDER BY id ASC", ARRAY_A );
    }

    /**
     * Get a single location by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function get_location( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . BTLOCS_TABLE_LOCATIONS . " WHERE id = %d", $id ), ARRAY_A );
    }

    /**
     * Update a location. All fields are required.
     *
     * @param int $id
     * @param string $name
     * @param string $address
     * @param string $email
     * @param bool $is_default
     * @return bool
     */
    public static function update_location( $id, $name, $address, $email, $is_default = false ) {
        global $wpdb;
        if ( empty( $name ) || empty( $address ) || empty( $email ) ) {
            return false;
        }
        if ( $is_default ) {
            self::unset_default_location();
        }
        $result = $wpdb->update(
            BTLOCS_TABLE_LOCATIONS,
            [
                'location_name' => $name,
                'address'       => $address,
                'email'         => $email,
                'is_default'    => $is_default ? 1 : 0,
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%s', '%d' ],
            [ '%d' ]
        );
        return $result !== false;
    }

    /**
     * Delete a location by ID.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_location( $id ) {
        global $wpdb;
        $result = $wpdb->delete( BTLOCS_TABLE_LOCATIONS, [ 'id' => $id ], [ '%d' ] );
        return $result !== false;
    }

    /**
     * Set a location as default. Unsets previous default.
     *
     * @param int $id
     * @return bool
     */
    public static function set_default_location( $id ) {
        global $wpdb;
        self::unset_default_location();
        $result = $wpdb->update( BTLOCS_TABLE_LOCATIONS, [ 'is_default' => 1 ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
        return $result !== false;
    }

    /**
     * Unset all default locations.
     *
     * @return void
     */
    public static function unset_default_location() {
        global $wpdb;
        $wpdb->update( BTLOCS_TABLE_LOCATIONS, [ 'is_default' => 0 ], [ 'is_default' => 1 ], [ '%d' ], [ '%d' ] );
    }

    /**
     * Get the default location.
     *
     * @return array|null
     */
    public static function get_default_location() {
        global $wpdb;
        return $wpdb->get_row( "SELECT * FROM " . BTLOCS_TABLE_LOCATIONS . " WHERE is_default = 1 LIMIT 1", ARRAY_A );
    }

    /**
     * Create custom tables for location-based pricing.
     * Called on plugin activation.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        // Table for location-based product pricing
        $table_name = $wpdb->prefix . 'btlocs_product_prices';
        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            regular_price DECIMAL(20,4) NOT NULL DEFAULT 0.0000,
            sale_price DECIMAL(20,4) DEFAULT NULL,
            variation_id BIGINT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY product_location (product_id, location_id),
            KEY variation (variation_id)
        ) $charset_collate;";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
} 