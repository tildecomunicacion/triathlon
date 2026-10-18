<?php
/**
 * Precios de referencia: productos concretos cuyo precio real se lee de la
 * tienda para sustituir la horquilla estimada de una gama.
 *
 * Reutiliza el scraper del plugin Triathlon Price Comparator si está activo.
 * Si no lo está, la calculadora sigue funcionando con las horquillas de la
 * tabla de datos: nunca se queda sin precio.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICC_Refs {

    const OPTION_KEY = 'icc_refs';

    public static function init() {
        // Nada que engancharse por ahora: el cron se registra en el bootstrap.
    }

    /**
     * Referencias guardadas.
     *
     * Cada una: array( item, opcion, modelo, url, store_slug ).
     */
    public static function get_refs() {
        $refs = get_option( self::OPTION_KEY, array() );
        return is_array( $refs ) ? $refs : array();
    }

    public static function save_refs( $refs ) {
        update_option( self::OPTION_KEY, array_values( $refs ) );
    }

    /**
     * ¿Tenemos scraper disponible?
     */
    public static function scraper_available() {
        return class_exists( 'TPC_Scraper' );
    }

    /**
     * Precios listos para el frontend.
     *
     * @return array Claves 'item.opcion' => array( precio, modelo, tienda, fecha ).
     */
    public static function get_prices() {
        $out = array();

        foreach ( self::get_refs() as $ref ) {
            if ( empty( $ref['item'] ) || empty( $ref['opcion'] ) ) {
                continue;
            }

            $precio = isset( $ref['precio_manual'] ) && '' !== $ref['precio_manual']
                ? (float) $ref['precio_manual']
                : self::scrape( $ref );

            if ( ! $precio ) {
                continue;
            }

            $tienda = '';
            if ( ! empty( $ref['store_slug'] ) && class_exists( 'TPC_Stores' ) ) {
                $store  = TPC_Stores::get_store( $ref['store_slug'] );
                $tienda = $store && ! empty( $store['name'] ) ? $store['name'] : $ref['store_slug'];
            }

            $out[ $ref['item'] . '.' . $ref['opcion'] ] = array(
                'precio' => round( $precio, 2 ),
                'modelo' => isset( $ref['modelo'] ) ? $ref['modelo'] : '',
                'tienda' => $tienda,
                'fecha'  => date_i18n( 'Y-m-d' ),
            );
        }

        return $out;
    }

    /**
     * Lee el precio de una referencia con el scraper del comparador.
     */
    private static function scrape( $ref ) {
        if ( ! self::scraper_available() || empty( $ref['url'] ) ) {
            return 0;
        }

        $data = TPC_Scraper::get_price( $ref['url'], isset( $ref['store_slug'] ) ? $ref['store_slug'] : '' );

        return ( $data && ! empty( $data['price'] ) ) ? (float) $data['price'] : 0;
    }

    /**
     * Cron: fuerza el refresco de todas las referencias.
     */
    public static function refresh_all() {
        if ( ! self::scraper_available() ) {
            return;
        }

        foreach ( self::get_refs() as $ref ) {
            if ( empty( $ref['url'] ) ) {
                continue;
            }
            TPC_Scraper::refresh_price( $ref['url'], isset( $ref['store_slug'] ) ? $ref['store_slug'] : '' );
        }
    }
}
