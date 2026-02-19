<?php
/**
 * Registers and renders the price comparator meta box.
 * Simplified: user only enters URL, coupon code, and optional overrides.
 * Prices are fetched automatically by the scraper.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TPC_Meta_Box {

    const META_KEY = '_tpc_comparator_entries';

    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
        add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
        add_action( 'wp_ajax_tpc_test_scrape', array( __CLASS__, 'ajax_test_scrape' ) );
        add_action( 'wp_ajax_tpc_refresh_prices', array( __CLASS__, 'ajax_refresh_prices' ) );
    }

    public static function register() {
        $post_types = apply_filters( 'tpc_post_types', array( 'post', 'page' ) );
        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'tpc_price_comparator',
                __( 'Comparador de Precios', 'triathlon-price-comparator' ),
                array( __CLASS__, 'render' ),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    /**
     * Render the meta box.
     */
    public static function render( $post ) {
        wp_nonce_field( 'tpc_save_comparator', 'tpc_comparator_nonce' );

        $entries = get_post_meta( $post->ID, self::META_KEY, true );
        if ( ! is_array( $entries ) ) {
            $entries = array();
        }

        $stores = TPC_Stores::get_stores();
        ?>
        <div id="tpc-comparator-wrap">
            <p class="description">
                <?php esc_html_e( 'Añade la URL de afiliado de cada tienda. El precio se obtiene automáticamente. Shortcode: [price_comparator]', 'triathlon-price-comparator' ); ?>
            </p>

            <?php if ( empty( $stores ) ) : ?>
                <div class="tpc-notice">
                    <p>
                        <?php
                        printf(
                            esc_html__( 'No hay tiendas configuradas. %sConfigura tiendas y sus selectores CSS%s primero.', 'triathlon-price-comparator' ),
                            '<a href="' . esc_url( admin_url( 'options-general.php?page=tpc-stores' ) ) . '">',
                            '</a>'
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <div id="tpc-entries-list">
                <?php
                foreach ( $entries as $index => $entry ) {
                    self::render_entry( $index, $entry, $stores );
                }
                ?>
            </div>

            <p style="display: flex; gap: 8px; align-items: center;">
                <button type="button" class="button button-primary" id="tpc-add-entry">
                    + <?php esc_html_e( 'Añadir tienda', 'triathlon-price-comparator' ); ?>
                </button>
                <?php if ( ! empty( $entries ) ) : ?>
                    <button type="button" class="button" id="tpc-refresh-all-prices" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        &#x21bb; <?php esc_html_e( 'Refrescar todos los precios', 'triathlon-price-comparator' ); ?>
                    </button>
                <?php endif; ?>
            </p>

            <script type="text/html" id="tpc-entry-template">
                <?php self::render_entry( '{{INDEX}}', array(), $stores ); ?>
            </script>
        </div>
        <?php
    }

    /**
     * Render a single entry row.
     */
    private static function render_entry( $index, $entry, $stores ) {
        $defaults = array(
            'store_slug'    => '',
            'affiliate_url' => '',
            'coupon_code'   => '',
            'shipping_text' => '',
            'price_override' => '',
        );
        $entry  = wp_parse_args( $entry, $defaults );
        $prefix = "tpc_entries[{$index}]";

        // Try to get cached price info for display.
        $price_info = null;
        if ( ! empty( $entry['affiliate_url'] ) && ! empty( $entry['store_slug'] ) ) {
            $price_info = TPC_Scraper::get_price( $entry['affiliate_url'], $entry['store_slug'] );
        }
        ?>
        <div class="tpc-entry" data-index="<?php echo esc_attr( $index ); ?>">
            <div class="tpc-entry-header">
                <span class="tpc-entry-drag dashicons dashicons-menu"></span>
                <span class="tpc-entry-title">
                    <?php
                    if ( ! empty( $entry['store_slug'] ) && isset( $stores[ $entry['store_slug'] ] ) ) {
                        echo esc_html( $stores[ $entry['store_slug'] ]['name'] );
                        if ( $price_info && ! empty( $price_info['price'] ) ) {
                            echo ' — <strong>' . esc_html( TPC_Scraper::format_price( $price_info['price'] ) ) . ' &euro;</strong>';
                        }
                    } else {
                        esc_html_e( 'Nueva tienda', 'triathlon-price-comparator' );
                    }
                    ?>
                </span>
                <button type="button" class="tpc-entry-toggle dashicons dashicons-arrow-down-alt2" title="<?php esc_attr_e( 'Expandir/Colapsar', 'triathlon-price-comparator' ); ?>"></button>
                <button type="button" class="tpc-entry-remove dashicons dashicons-trash" title="<?php esc_attr_e( 'Eliminar', 'triathlon-price-comparator' ); ?>"></button>
            </div>
            <div class="tpc-entry-body">
                <table class="tpc-entry-fields">
                    <tr>
                        <th><label><?php esc_html_e( 'Tienda', 'triathlon-price-comparator' ); ?></label></th>
                        <td>
                            <select name="<?php echo esc_attr( $prefix ); ?>[store_slug]" class="tpc-store-select">
                                <option value=""><?php esc_html_e( '— Seleccionar tienda —', 'triathlon-price-comparator' ); ?></option>
                                <?php foreach ( $stores as $store ) : ?>
                                    <option value="<?php echo esc_attr( $store['slug'] ); ?>"
                                        <?php selected( $entry['store_slug'], $store['slug'] ); ?>>
                                        <?php echo esc_html( $store['name'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'URL de afiliado', 'triathlon-price-comparator' ); ?></label></th>
                        <td>
                            <input type="url" name="<?php echo esc_attr( $prefix ); ?>[affiliate_url]"
                                value="<?php echo esc_url( $entry['affiliate_url'] ); ?>"
                                class="large-text tpc-affiliate-url" placeholder="https://...">
                            <button type="button" class="button button-small tpc-test-scrape" style="margin-top:4px;">
                                &#x1F50D; <?php esc_html_e( 'Probar extracción de precio', 'triathlon-price-comparator' ); ?>
                            </button>
                            <span class="tpc-scrape-result" style="margin-left:8px;"></span>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Código de cupón', 'triathlon-price-comparator' ); ?></label></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr( $prefix ); ?>[coupon_code]"
                                value="<?php echo esc_attr( $entry['coupon_code'] ); ?>"
                                class="regular-text" placeholder="Ej: RUNNING10">
                            <span class="description"><?php esc_html_e( 'Opcional', 'triathlon-price-comparator' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Texto de envío', 'triathlon-price-comparator' ); ?></label></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr( $prefix ); ?>[shipping_text]"
                                value="<?php echo esc_attr( $entry['shipping_text'] ); ?>"
                                class="regular-text" placeholder="<?php esc_attr_e( 'Dejar vacío para usar el de la tienda', 'triathlon-price-comparator' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Precio manual', 'triathlon-price-comparator' ); ?></label></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr( $prefix ); ?>[price_override]"
                                value="<?php echo esc_attr( $entry['price_override'] ); ?>"
                                class="small-text" placeholder="—">
                            <span class="description"><?php esc_html_e( 'Solo si falla la extracción automática. Dejar vacío para scraping automático.', 'triathlon-price-comparator' ); ?></span>
                        </td>
                    </tr>

                    <?php if ( $price_info ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Precio obtenido', 'triathlon-price-comparator' ); ?></th>
                        <td class="tpc-scraped-info">
                            <strong style="font-size:16px;color:#2e7d32;">
                                <?php echo esc_html( TPC_Scraper::format_price( $price_info['price'] ) ); ?> &euro;
                            </strong>
                            <?php if ( ! empty( $price_info['original_price'] ) ) : ?>
                                <span style="text-decoration:line-through;color:#999;margin-left:10px;">
                                    <?php echo esc_html( TPC_Scraper::format_price( $price_info['original_price'] ) ); ?> &euro;
                                </span>
                            <?php endif; ?>
                            <?php if ( ! empty( $price_info['discount'] ) ) : ?>
                                <span style="background:#e8f5e9;color:#2e7d32;padding:2px 6px;border-radius:3px;margin-left:8px;font-size:12px;">
                                    <?php echo esc_html( $price_info['discount'] ); ?>
                                </span>
                            <?php endif; ?>
                            <br>
                            <small style="color:#888;">
                                <?php
                                printf(
                                    esc_html__( 'Actualizado: %s', 'triathlon-price-comparator' ),
                                    esc_html( date_i18n( 'd/m/Y H:i', $price_info['fetched_at'] ) )
                                );
                                ?>
                            </small>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Save meta box data.
     */
    public static function save( $post_id, $post ) {
        if ( ! isset( $_POST['tpc_comparator_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( $_POST['tpc_comparator_nonce'], 'tpc_save_comparator' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $raw_entries = isset( $_POST['tpc_entries'] ) ? $_POST['tpc_entries'] : array();
        $entries     = array();

        if ( is_array( $raw_entries ) ) {
            foreach ( $raw_entries as $entry ) {
                if ( empty( $entry['store_slug'] ) || empty( $entry['affiliate_url'] ) ) {
                    continue;
                }
                $entries[] = array(
                    'store_slug'     => sanitize_text_field( $entry['store_slug'] ),
                    'affiliate_url'  => esc_url_raw( $entry['affiliate_url'] ),
                    'coupon_code'    => sanitize_text_field( $entry['coupon_code'] ?? '' ),
                    'shipping_text'  => sanitize_text_field( $entry['shipping_text'] ?? '' ),
                    'price_override' => sanitize_text_field( $entry['price_override'] ?? '' ),
                );
            }
        }

        update_post_meta( $post_id, self::META_KEY, $entries );

        // Trigger background price fetch for new/updated URLs.
        foreach ( $entries as $entry ) {
            TPC_Scraper::get_price( $entry['affiliate_url'], $entry['store_slug'] );
        }
    }

    /**
     * AJAX: test scrape a single URL.
     */
    public static function ajax_test_scrape() {
        check_ajax_referer( 'tpc_save_comparator', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Sin permisos.' );
        }

        $url        = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
        $store_slug = sanitize_text_field( wp_unslash( $_POST['store_slug'] ?? '' ) );

        if ( empty( $url ) || empty( $store_slug ) ) {
            wp_send_json_error( 'URL y tienda son obligatorios.' );
        }

        // Force refresh (skip cache).
        TPC_Scraper::delete_cache( $url );
        $result = TPC_Scraper::refresh_price( $url, $store_slug );

        if ( ! $result || isset( $result['error'] ) ) {
            $error = isset( $result['error'] ) ? $result['error'] : 'Error desconocido.';
            wp_send_json_error( $error );
        }

        if ( empty( $result['price'] ) ) {
            wp_send_json_error( 'No se encontró precio en la página.' );
        }

        wp_send_json_success( array(
            'price'          => TPC_Scraper::format_price( $result['price'] ),
            'original_price' => ! empty( $result['original_price'] ) ? TPC_Scraper::format_price( $result['original_price'] ) : null,
            'discount'       => $result['discount'] ?? null,
            'product_name'   => $result['product_name'] ?? null,
            'image'          => $result['image'] ?? null,
            'method'         => $result['method'] ?? null,
            'product_url'    => $result['product_url'] ?? null,
        ) );
    }

    /**
     * AJAX: refresh all prices for a post.
     */
    public static function ajax_refresh_prices() {
        check_ajax_referer( 'tpc_save_comparator', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Sin permisos.' );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        $entries = get_post_meta( $post_id, self::META_KEY, true );

        if ( ! is_array( $entries ) || empty( $entries ) ) {
            wp_send_json_error( 'No hay entradas.' );
        }

        $results = array();
        foreach ( $entries as $entry ) {
            TPC_Scraper::delete_cache( $entry['affiliate_url'] );
            $price_data = TPC_Scraper::refresh_price( $entry['affiliate_url'], $entry['store_slug'] );
            $results[] = array(
                'store_slug' => $entry['store_slug'],
                'price'      => $price_data ? TPC_Scraper::format_price( $price_data['price'] ) : null,
                'discount'   => $price_data ? $price_data['discount'] : null,
                'success'    => null !== $price_data,
            );
        }

        wp_send_json_success( array( 'results' => $results ) );
    }
}
