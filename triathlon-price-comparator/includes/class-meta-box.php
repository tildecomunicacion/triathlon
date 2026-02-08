<?php
/**
 * Registers and renders the price comparator meta box.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TPC_Meta_Box {

    const META_KEY = '_tpc_comparator_entries';

    /**
     * Initialize meta box hooks.
     */
    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
        add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
    }

    /**
     * Register the meta box for posts and pages.
     */
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
                <?php esc_html_e( 'Añade las tiendas y precios para el comparador. Usa el shortcode [price_comparator] en el contenido del post para mostrarlo.', 'triathlon-price-comparator' ); ?>
            </p>

            <?php if ( empty( $stores ) ) : ?>
                <div class="tpc-notice">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: URL to stores settings */
                            esc_html__( 'No hay tiendas registradas. %sAñade tiendas primero%s.', 'triathlon-price-comparator' ),
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

            <p>
                <button type="button" class="button button-primary" id="tpc-add-entry">
                    + <?php esc_html_e( 'Añadir tienda al comparador', 'triathlon-price-comparator' ); ?>
                </button>
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
            'store_slug'      => '',
            'affiliate_url'   => '',
            'price'           => '',
            'original_price'  => '',
            'discount'        => '',
            'extra_discount'  => '',
            'shipping_text'   => 'Envío gratuito',
            'coupon_code'     => '',
        );
        $entry = wp_parse_args( $entry, $defaults );
        $prefix = "tpc_entries[{$index}]";
        ?>
        <div class="tpc-entry" data-index="<?php echo esc_attr( $index ); ?>">
            <div class="tpc-entry-header">
                <span class="tpc-entry-drag dashicons dashicons-menu"></span>
                <span class="tpc-entry-title">
                    <?php
                    if ( ! empty( $entry['store_slug'] ) && isset( $stores[ $entry['store_slug'] ] ) ) {
                        echo esc_html( $stores[ $entry['store_slug'] ]['name'] );
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
                                class="large-text" placeholder="https://...">
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Precio', 'triathlon-price-comparator' ); ?></label></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr( $prefix ); ?>[price]"
                                value="<?php echo esc_attr( $entry['price'] ); ?>"
                                class="small-text" placeholder="153,00">
                            <span class="description"><?php esc_html_e( 'Precio actual (ej: 153,00)', 'triathlon-price-comparator' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Precio original', 'triathlon-price-comparator' ); ?></label></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr( $prefix ); ?>[original_price]"
                                value="<?php echo esc_attr( $entry['original_price'] ); ?>"
                                class="small-text" placeholder="180,00">
                            <span class="description"><?php esc_html_e( 'Precio sin descuento (opcional, para calcular %)', 'triathlon-price-comparator' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Descuento', 'triathlon-price-comparator' ); ?></label></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr( $prefix ); ?>[discount]"
                                value="<?php echo esc_attr( $entry['discount'] ); ?>"
                                class="small-text" placeholder="-15%">
                            <span class="description"><?php esc_html_e( 'Ej: -15%', 'triathlon-price-comparator' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Descuento extra', 'triathlon-price-comparator' ); ?></label></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr( $prefix ); ?>[extra_discount]"
                                value="<?php echo esc_attr( $entry['extra_discount'] ); ?>"
                                class="small-text" placeholder="-10% EXTRA">
                            <span class="description"><?php esc_html_e( 'Ej: -10% EXTRA (opcional)', 'triathlon-price-comparator' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Envío', 'triathlon-price-comparator' ); ?></label></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr( $prefix ); ?>[shipping_text]"
                                value="<?php echo esc_attr( $entry['shipping_text'] ); ?>"
                                class="regular-text" placeholder="Envío gratuito">
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Código de cupón', 'triathlon-price-comparator' ); ?></label></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr( $prefix ); ?>[coupon_code]"
                                value="<?php echo esc_attr( $entry['coupon_code'] ); ?>"
                                class="regular-text" placeholder="RUNNING10">
                            <span class="description"><?php esc_html_e( 'Opcional. Se mostrará con botón de copiar.', 'triathlon-price-comparator' ); ?></span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Save the meta box data.
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
                if ( empty( $entry['store_slug'] ) ) {
                    continue;
                }
                $entries[] = array(
                    'store_slug'     => sanitize_text_field( $entry['store_slug'] ),
                    'affiliate_url'  => esc_url_raw( $entry['affiliate_url'] ),
                    'price'          => sanitize_text_field( $entry['price'] ),
                    'original_price' => sanitize_text_field( $entry['original_price'] ),
                    'discount'       => sanitize_text_field( $entry['discount'] ),
                    'extra_discount' => sanitize_text_field( $entry['extra_discount'] ),
                    'shipping_text'  => sanitize_text_field( $entry['shipping_text'] ),
                    'coupon_code'    => sanitize_text_field( $entry['coupon_code'] ),
                );
            }
        }

        update_post_meta( $post_id, self::META_KEY, $entries );
    }
}
