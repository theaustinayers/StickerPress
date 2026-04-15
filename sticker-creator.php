<?php
/**
 * Plugin Name: StickerPress
 * Description: Custom sticker ordering plugin with artwork upload, cut types, real-time preview, and dynamic pricing.
 * Version: 1.0.0
 * Author: Sticker Business
 * Text Domain: sticker-press
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SC_VERSION', '1.9.0' );

/**
 * Activation: create default options.
 */
function sc_activate() {
    $default_sizes = array(
        array( 'label' => '2" x 2"',  'width' => 2, 'height' => 2 ),
        array( 'label' => '3" x 3"',  'width' => 3, 'height' => 3 ),
        array( 'label' => '4" x 4"',  'width' => 4, 'height' => 4 ),
        array( 'label' => '5" x 5"',  'width' => 5, 'height' => 5 ),
        array( 'label' => '6" x 6"',  'width' => 6, 'height' => 6 ),
    );

    $default_pricing = array(
        // key: "{size_index}_{material}_{finish}_{laminated}"
        // material: vinyl | paper | clear
        // finish: glossy | matte
        // laminated: yes | no
    );

    // Build default pricing grid
    foreach ( $default_sizes as $i => $size ) {
        $area = $size['width'] * $size['height'];
        foreach ( array( 'vinyl', 'paper', 'clear' ) as $material ) {
            foreach ( array( 'glossy', 'matte' ) as $finish ) {
                foreach ( array( 'yes', 'no' ) as $laminated ) {
                    $base = $area * 0.25;
                    if ( $material === 'clear' ) $base *= 1.3;
                    if ( $finish === 'glossy' ) $base *= 1.1;
                    if ( $laminated === 'yes' ) $base *= 1.2;
                    $default_pricing["{$i}_{$material}_{$finish}_{$laminated}"] = round( $base, 2 );
                }
            }
        }
    }

    if ( false === get_option( 'sc_sizes' ) ) {
        add_option( 'sc_sizes', $default_sizes );
    }
    if ( false === get_option( 'sc_materials' ) ) {
        add_option( 'sc_materials', array(
            array( 'slug' => 'vinyl', 'label' => 'Vinyl' ),
            array( 'slug' => 'paper', 'label' => 'Paper' ),
            array( 'slug' => 'clear', 'label' => 'Clear' ),
        ));
    }
    if ( false === get_option( 'sc_finishes' ) ) {
        add_option( 'sc_finishes', array(
            array( 'slug' => 'glossy', 'label' => 'Glossy' ),
            array( 'slug' => 'matte', 'label' => 'Matte' ),
        ));
    }
    if ( false === get_option( 'sc_pricing' ) ) {
        add_option( 'sc_pricing', $default_pricing );
    }
    if ( false === get_option( 'sc_quantity_breaks' ) ) {
        add_option( 'sc_quantity_breaks', array(
            array( 'min' => 1,   'max' => 49,  'multiplier' => 1.0 ),
            array( 'min' => 50,  'max' => 99,  'multiplier' => 0.85 ),
            array( 'min' => 100, 'max' => 249, 'multiplier' => 0.70 ),
            array( 'min' => 250, 'max' => 999, 'multiplier' => 0.55 ),
        ));
    }
    if ( false === get_option( 'sc_quantity_breaks_overrides' ) ) {
        add_option( 'sc_quantity_breaks_overrides', array() );
    }

    // Create WooCommerce product if WooCommerce is active
    if ( class_exists( 'WooCommerce' ) ) {
        require_once SC_PLUGIN_DIR . 'includes/woocommerce.php';
        sc_wc_create_product();
    }

    // Create uploads directory
    $upload_dir = wp_upload_dir();
    $sticker_dir = $upload_dir['basedir'] . '/sticker-creator';
    if ( ! file_exists( $sticker_dir ) ) {
        wp_mkdir_p( $sticker_dir );
        // Protect directory
        file_put_contents( $sticker_dir . '/.htaccess', 'Options -Indexes' );
    }
}
register_activation_hook( __FILE__, 'sc_activate' );

/**
 * Ensure new options exist for upgrades (runs on every load, lightweight).
 */
add_action( 'init', 'sc_maybe_seed_options' );
function sc_maybe_seed_options() {
    if ( false === get_option( 'sc_materials' ) ) {
        add_option( 'sc_materials', array(
            array( 'slug' => 'vinyl', 'label' => 'Vinyl' ),
            array( 'slug' => 'paper', 'label' => 'Paper' ),
            array( 'slug' => 'clear', 'label' => 'Clear' ),
        ));
    }
    if ( false === get_option( 'sc_finishes' ) ) {
        add_option( 'sc_finishes', array(
            array( 'slug' => 'glossy', 'label' => 'Glossy' ),
            array( 'slug' => 'matte', 'label' => 'Matte' ),
        ));
    }
}

/**
 * Load admin.
 */
if ( is_admin() ) {
    require_once SC_PLUGIN_DIR . 'includes/admin.php';
}

/**
 * Load frontend.
 */
require_once SC_PLUGIN_DIR . 'includes/frontend.php';
require_once SC_PLUGIN_DIR . 'includes/ajax.php';

/**
 * Load WooCommerce integration after all plugins are loaded
 * so WooCommerce is guaranteed to be available.
 */
add_action( 'plugins_loaded', function() {
    if ( class_exists( 'WooCommerce' ) ) {
        require_once SC_PLUGIN_DIR . 'includes/woocommerce.php';
    }
}, 20 );
