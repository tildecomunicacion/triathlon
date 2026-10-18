<?php
/**
 * Plugin Name: Calculadora de coste real IRONMAN
 * Description: Calculadora de cuánto cuesta de verdad una prueba IRONMAN: amortiza el material entre temporadas, calcula los consumibles por volumen de entrenamiento y separa el desembolso del coste real. Shortcode: [calculadora_ironman]
 * Version: 1.0.0
 * Author: Tilde Comunicación
 * Text Domain: ironman-cost-calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ICC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ICC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ICC_VERSION', '1.0.0' );

require_once ICC_PLUGIN_DIR . 'includes/class-icc-refs.php';
require_once ICC_PLUGIN_DIR . 'includes/class-icc-admin.php';
require_once ICC_PLUGIN_DIR . 'includes/class-icc-shortcode.php';

function icc_init() {
    ICC_Refs::init();
    ICC_Admin::init();
    ICC_Shortcode::init();
}
add_action( 'plugins_loaded', 'icc_init' );

/**
 * Refresco diario de los precios de referencia (si el comparador está activo).
 */
function icc_activate() {
    if ( ! wp_next_scheduled( 'icc_cron_refresh_prices' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'icc_cron_refresh_prices' );
    }
}
register_activation_hook( __FILE__, 'icc_activate' );

function icc_deactivate() {
    wp_clear_scheduled_hook( 'icc_cron_refresh_prices' );
}
register_deactivation_hook( __FILE__, 'icc_deactivate' );

add_action( 'icc_cron_refresh_prices', array( 'ICC_Refs', 'refresh_all' ) );
