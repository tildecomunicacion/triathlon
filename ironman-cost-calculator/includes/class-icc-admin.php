<?php
/**
 * Pantalla de ajustes: número de WhatsApp, parámetros globales y los
 * productos de referencia cuyo precio real alimenta la calculadora.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICC_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
        add_action( 'admin_post_icc_save_settings', array( __CLASS__, 'handle_save' ) );
    }

    public static function add_menu_page() {
        add_options_page(
            __( 'Calculadora IRONMAN', 'ironman-cost-calculator' ),
            __( 'Calculadora IRONMAN', 'ironman-cost-calculator' ),
            'manage_options',
            'icc-settings',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    public static function handle_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permisos.', 'ironman-cost-calculator' ) );
        }
        check_admin_referer( 'icc_save_settings' );

        update_option( 'icc_whatsapp', preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['icc_whatsapp'] ?? '' ) ) );
        update_option( 'icc_gestion_pct', sanitize_text_field( wp_unslash( $_POST['icc_gestion_pct'] ?? '' ) ) );
        update_option( 'icc_eur_km', sanitize_text_field( wp_unslash( $_POST['icc_eur_km'] ?? '' ) ) );

        $refs  = array();
        $posted = isset( $_POST['refs'] ) && is_array( $_POST['refs'] ) ? wp_unslash( $_POST['refs'] ) : array();

        foreach ( $posted as $row ) {
            if ( empty( $row['item'] ) || empty( $row['opcion'] ) ) {
                continue;
            }
            $refs[] = array(
                'item'          => sanitize_key( $row['item'] ),
                'opcion'        => sanitize_key( $row['opcion'] ),
                'modelo'        => sanitize_text_field( $row['modelo'] ?? '' ),
                'url'           => esc_url_raw( $row['url'] ?? '' ),
                'store_slug'    => sanitize_key( $row['store_slug'] ?? '' ),
                'precio_manual' => '' === ( $row['precio_manual'] ?? '' ) ? '' : (float) str_replace( ',', '.', $row['precio_manual'] ),
            );
        }

        ICC_Refs::save_refs( $refs );

        wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'options-general.php?page=icc-settings' ) ) );
        exit;
    }

    public static function render_settings_page() {
        $refs   = ICC_Refs::get_refs();
        $refs[] = array(); // Fila vacía para añadir.
        $stores = class_exists( 'TPC_Stores' ) ? TPC_Stores::get_stores() : array();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Calculadora de coste real IRONMAN', 'ironman-cost-calculator' ); ?></h1>

            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ajustes guardados.', 'ironman-cost-calculator' ); ?></p></div>
            <?php endif; ?>

            <p>
                <?php esc_html_e( 'Shortcode:', 'ironman-cost-calculator' ); ?>
                <code>[calculadora_ironman]</code>
            </p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="icc_save_settings">
                <?php wp_nonce_field( 'icc_save_settings' ); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="icc_whatsapp"><?php esc_html_e( 'WhatsApp para recibir el desglose', 'ironman-cost-calculator' ); ?></label></th>
                        <td>
                            <input name="icc_whatsapp" id="icc_whatsapp" type="text" class="regular-text"
                                   value="<?php echo esc_attr( get_option( 'icc_whatsapp', '' ) ); ?>"
                                   placeholder="34600000000">
                            <p class="description"><?php esc_html_e( 'Con prefijo del país y sin signos. Si se deja vacío, no se muestra el botón de WhatsApp.', 'ironman-cost-calculator' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="icc_gestion_pct"><?php esc_html_e( 'Gastos de gestión por defecto (%)', 'ironman-cost-calculator' ); ?></label></th>
                        <td><input name="icc_gestion_pct" id="icc_gestion_pct" type="text" class="small-text"
                                   value="<?php echo esc_attr( get_option( 'icc_gestion_pct', '' ) ); ?>" placeholder="9"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="icc_eur_km"><?php esc_html_e( 'Coste del coche (€/km)', 'ironman-cost-calculator' ); ?></label></th>
                        <td><input name="icc_eur_km" id="icc_eur_km" type="text" class="small-text"
                                   value="<?php echo esc_attr( get_option( 'icc_eur_km', '' ) ); ?>" placeholder="0.26"></td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Precios reales de referencia', 'ironman-cost-calculator' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Cada fila sustituye la horquilla estimada de una gama por el precio real de un producto concreto. Si pones una URL de tienda, el precio se lee automáticamente con el scraper del comparador y se refresca a diario. El precio manual manda sobre el scrapeado.', 'ironman-cost-calculator' ); ?>
                    <?php if ( ! ICC_Refs::scraper_available() ) : ?>
                        <br><strong><?php esc_html_e( 'El plugin Triathlon Price Comparator no está activo: aquí solo funcionará el precio manual.', 'ironman-cost-calculator' ); ?></strong>
                    <?php endif; ?>
                </p>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Partida (id)', 'ironman-cost-calculator' ); ?></th>
                            <th><?php esc_html_e( 'Gama (id)', 'ironman-cost-calculator' ); ?></th>
                            <th><?php esc_html_e( 'Modelo', 'ironman-cost-calculator' ); ?></th>
                            <th><?php esc_html_e( 'URL de la tienda', 'ironman-cost-calculator' ); ?></th>
                            <th><?php esc_html_e( 'Tienda', 'ironman-cost-calculator' ); ?></th>
                            <th><?php esc_html_e( 'Precio manual (€)', 'ironman-cost-calculator' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $refs as $i => $ref ) : ?>
                        <tr>
                            <td><input type="text" name="refs[<?php echo (int) $i; ?>][item]" value="<?php echo esc_attr( $ref['item'] ?? '' ); ?>" placeholder="bici"></td>
                            <td><input type="text" name="refs[<?php echo (int) $i; ?>][opcion]" value="<?php echo esc_attr( $ref['opcion'] ?? '' ); ?>" placeholder="tt_media"></td>
                            <td><input type="text" name="refs[<?php echo (int) $i; ?>][modelo]" value="<?php echo esc_attr( $ref['modelo'] ?? '' ); ?>" placeholder="Canyon Speedmax CF 7"></td>
                            <td><input type="url" name="refs[<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $ref['url'] ?? '' ); ?>" class="regular-text"></td>
                            <td>
                                <select name="refs[<?php echo (int) $i; ?>][store_slug]">
                                    <option value=""><?php esc_html_e( '— ninguna —', 'ironman-cost-calculator' ); ?></option>
                                    <?php foreach ( $stores as $slug => $store ) : ?>
                                        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $ref['store_slug'] ?? '', $slug ); ?>>
                                            <?php echo esc_html( $store['name'] ?? $slug ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="refs[<?php echo (int) $i; ?>][precio_manual]" value="<?php echo esc_attr( $ref['precio_manual'] ?? '' ); ?>" class="small-text"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="description">
                    <?php esc_html_e( 'Los ids de partida y gama son los de assets/js/icc-data.js (bici, neopreno, reloj… y dentro de cada uno: tt_media, entrada, etc.). Están documentados en MODELO.md.', 'ironman-cost-calculator' ); ?>
                </p>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
