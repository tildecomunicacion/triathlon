<?php
/**
 * Plugin Name: Triathlon Price Comparator
 * Description: Comparador de precios con scraping automático de URLs de afiliados.
 * Version: 2.0.0
 * Author: Tilde Comunicación
 * Text Domain: triathlon-price-comparator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TPC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TPC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TPC_VERSION', '2.0.0' );

require_once TPC_PLUGIN_DIR . 'includes/class-stores.php';
require_once TPC_PLUGIN_DIR . 'includes/class-scraper.php';
require_once TPC_PLUGIN_DIR . 'includes/class-meta-box.php';
require_once TPC_PLUGIN_DIR . 'includes/class-shortcode.php';

/**
 * Initialize the plugin.
 */
function tpc_init() {
    TPC_Stores::init();
    TPC_Meta_Box::init();
    TPC_Shortcode::init();
}
add_action( 'plugins_loaded', 'tpc_init' );

/**
 * Enqueue admin assets on post edit screens.
 */
function tpc_admin_assets( $hook ) {
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

    wp_localize_script( 'tpc-admin', 'tpcAdmin', array(
        'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
        'nonce'         => wp_create_nonce( 'tpc_save_comparator' ),
        'stores'        => TPC_Stores::get_stores(),
        'confirmRemove' => __( '¿Eliminar esta tienda del comparador?', 'triathlon-price-comparator' ),
        'scraping'      => __( 'Consultando...', 'triathlon-price-comparator' ),
        'scrapeOk'      => __( 'Precio encontrado:', 'triathlon-price-comparator' ),
        'scrapeFail'    => __( 'No se pudo extraer el precio.', 'triathlon-price-comparator' ),
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

/**
 * Register WP-Cron event for periodic price refresh.
 */
function tpc_activate() {
    if ( ! wp_next_scheduled( 'tpc_cron_refresh_prices' ) ) {
        wp_schedule_event( time(), 'tpc_every_6_hours', 'tpc_cron_refresh_prices' );
    }
}
register_activation_hook( __FILE__, 'tpc_activate' );

function tpc_deactivate() {
    wp_clear_scheduled_hook( 'tpc_cron_refresh_prices' );
}
register_deactivation_hook( __FILE__, 'tpc_deactivate' );

/**
 * Add custom cron schedule.
 */
function tpc_cron_schedules( $schedules ) {
    $schedules['tpc_every_6_hours'] = array(
        'interval' => 6 * HOUR_IN_SECONDS,
        'display'  => __( 'Cada 6 horas (TPC)', 'triathlon-price-comparator' ),
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'tpc_cron_schedules' );

/**
 * Cron handler: refresh all prices for all posts that have comparator entries.
 */
function tpc_cron_refresh_all_prices() {
    $posts = get_posts( array(
        'post_type'   => apply_filters( 'tpc_post_types', array( 'post', 'page' ) ),
        'meta_key'    => TPC_Meta_Box::META_KEY,
        'numberposts' => 100,
        'fields'      => 'ids',
    ) );

    foreach ( $posts as $post_id ) {
        $entries = get_post_meta( $post_id, TPC_Meta_Box::META_KEY, true );
        if ( ! is_array( $entries ) ) {
            continue;
        }
        foreach ( $entries as $entry ) {
            if ( empty( $entry['affiliate_url'] ) || empty( $entry['store_slug'] ) ) {
                continue;
            }
            // get_price() will auto-refresh if the transient expired.
            TPC_Scraper::get_price( $entry['affiliate_url'], $entry['store_slug'] );
        }
    }
}
add_action( 'tpc_cron_refresh_prices', 'tpc_cron_refresh_all_prices' );
