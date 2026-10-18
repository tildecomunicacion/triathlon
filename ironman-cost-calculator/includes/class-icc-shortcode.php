<?php
/**
 * Shortcode [calculadora_ironman].
 *
 * Atributos:
 *   whatsapp  Número al que se envía el desglose (solo dígitos, con prefijo).
 *   tema      'claro' u 'oscuro' para forzar el tema; por defecto, el del sistema.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICC_Shortcode {

    public static function init() {
        add_shortcode( 'calculadora_ironman', array( __CLASS__, 'render' ) );
    }

    public static function render( $atts ) {
        $atts = shortcode_atts( array(
            'whatsapp' => get_option( 'icc_whatsapp', '' ),
            'tema'     => '',
        ), $atts, 'calculadora_ironman' );

        self::enqueue_assets();

        $whatsapp = preg_replace( '/[^0-9]/', '', (string) $atts['whatsapp'] );

        return sprintf(
            '<div class="icc"%s%s></div>',
            $whatsapp ? ' data-whatsapp="' . esc_attr( $whatsapp ) . '"' : '',
            in_array( $atts['tema'], array( 'claro', 'oscuro' ), true ) ? ' data-tema="' . esc_attr( $atts['tema'] ) . '"' : ''
        );
    }

    /**
     * Carga los assets solo cuando la calculadora está en la página.
     */
    private static function enqueue_assets() {
        wp_enqueue_style(
            'icc-frontend',
            ICC_PLUGIN_URL . 'assets/css/icc-frontend.css',
            array(),
            ICC_VERSION
        );

        wp_enqueue_script( 'icc-data', ICC_PLUGIN_URL . 'assets/js/icc-data.js', array(), ICC_VERSION, true );
        wp_enqueue_script( 'icc-engine', ICC_PLUGIN_URL . 'assets/js/icc-engine.js', array( 'icc-data' ), ICC_VERSION, true );
        wp_enqueue_script( 'icc-ui', ICC_PLUGIN_URL . 'assets/js/icc-ui.js', array( 'icc-engine' ), ICC_VERSION, true );

        wp_localize_script( 'icc-data', 'ICC_CONFIG', array(
            'precios' => ICC_Refs::get_prices(),
            'data'    => self::data_overrides(),
        ) );
    }

    /**
     * Ajustes editables desde el admin que sobreescriben la tabla de datos.
     */
    private static function data_overrides() {
        $overrides = array();

        $gestion = get_option( 'icc_gestion_pct', '' );
        if ( '' !== $gestion ) {
            $overrides['evento'] = array(
                'inscripcion' => array( 'gestion_pct' => (float) $gestion ),
            );
        }

        $eur_km = get_option( 'icc_eur_km', '' );
        if ( '' !== $eur_km ) {
            if ( ! isset( $overrides['evento'] ) ) {
                $overrides['evento'] = array();
            }
            $overrides['evento']['viaje'] = array( 'coche_eur_km' => (float) $eur_km );
        }

        return $overrides;
    }
}
