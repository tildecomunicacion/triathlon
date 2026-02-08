<?php
/**
 * Plugin Name: Triathlon Price Comparator
 * Description: Comparador de precios con enlaces de afiliados para productos de triatlón.
 * Version: 1.0.0
 * Author: Tilde Comunicación
 * Text Domain: triathlon-price-comparator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TPC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TPC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TPC_VERSION', '1.0.0' );

require_once TPC_PLUGIN_DIR . 'includes/class-meta-box.php';
require_once TPC_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once TPC_PLUGIN_DIR . 'includes/class-stores.php';

/**
 * Initialize the plugin.
 */
function tpc_init() {
    TPC_Meta_Box::init();
    TPC_Shortcode::init();
    TPC_Stores::init();
}
add_action( 'plugins_loaded', 'tpc_init' );

/**
 * Enqueue admin assets.
 */
function tpc_admin_assets( $hook ) {
    global $post;

    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }

    wp_enqueue_media();

    wp_enqueue_style(
        'tpc-admin',
        TPC_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        TPC_VERSION
    );

    wp_enqueue_script(
        'tpc-admin',
        TPC_PLUGIN_URL . 'assets/js/admin.js',
        array( 'jquery', 'jquery-ui-sortable' ),
        TPC_VERSION,
        true
    );

    $stores = TPC_Stores::get_stores();
    wp_localize_script( 'tpc-admin', 'tpcAdmin', array(
        'stores'      => $stores,
        'mediaTitle'   => __( 'Seleccionar logo', 'triathlon-price-comparator' ),
        'mediaButton'  => __( 'Usar este logo', 'triathlon-price-comparator' ),
        'confirmRemove' => __( '¿Eliminar esta tienda del comparador?', 'triathlon-price-comparator' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'tpc_admin_assets' );

/**
 * Enqueue frontend assets.
 */
function tpc_frontend_assets() {
    wp_enqueue_style(
        'tpc-frontend',
        TPC_PLUGIN_URL . 'assets/css/frontend.css',
        array(),
        TPC_VERSION
    );

    wp_enqueue_script(
        'tpc-frontend',
        TPC_PLUGIN_URL . 'assets/js/frontend.js',
        array(),
        TPC_VERSION,
        true
    );
}
add_action( 'wp_enqueue_scripts', 'tpc_frontend_assets' );
