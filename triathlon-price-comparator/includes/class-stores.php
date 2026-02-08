<?php
/**
 * Manages the store registry for the price comparator.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TPC_Stores {

    const OPTION_KEY = 'tpc_stores';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'wp_ajax_tpc_add_store', array( __CLASS__, 'ajax_add_store' ) );
        add_action( 'wp_ajax_tpc_delete_store', array( __CLASS__, 'ajax_delete_store' ) );
    }

    public static function add_menu_page() {
        add_options_page(
            __( 'Tiendas del Comparador', 'triathlon-price-comparator' ),
            __( 'Tiendas Comparador', 'triathlon-price-comparator' ),
            'manage_options',
            'tpc-stores',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    public static function register_settings() {
        register_setting( 'tpc_stores_group', self::OPTION_KEY );
    }

    /**
     * Get all registered stores.
     */
    public static function get_stores() {
        $stores = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $stores ) ) {
            $stores = array();
        }
        return $stores;
    }

    /**
     * Get a single store by slug.
     */
    public static function get_store( $slug ) {
        $stores = self::get_stores();
        return isset( $stores[ $slug ] ) ? $stores[ $slug ] : null;
    }

    /**
     * AJAX: add a store.
     */
    public static function ajax_add_store() {
        check_ajax_referer( 'tpc_stores_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No tienes permisos.' );
        }

        $name                   = sanitize_text_field( wp_unslash( $_POST['store_name'] ?? '' ) );
        $logo_id                = absint( $_POST['store_logo_id'] ?? 0 );
        $price_selector         = sanitize_text_field( wp_unslash( $_POST['price_selector'] ?? '' ) );
        $original_price_selector = sanitize_text_field( wp_unslash( $_POST['original_price_selector'] ?? '' ) );
        $cache_hours            = absint( $_POST['cache_hours'] ?? 6 );
        $default_shipping       = sanitize_text_field( wp_unslash( $_POST['default_shipping'] ?? 'Envío gratuito' ) );

        if ( empty( $name ) ) {
            wp_send_json_error( 'El nombre es obligatorio.' );
        }
        if ( empty( $price_selector ) ) {
            wp_send_json_error( 'El selector CSS del precio es obligatorio.' );
        }

        $stores    = self::get_stores();
        $slug      = sanitize_title( $name );
        $logo_url  = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

        if ( $cache_hours < 1 ) {
            $cache_hours = 6;
        }

        $stores[ $slug ] = array(
            'name'                    => $name,
            'slug'                    => $slug,
            'logo_id'                 => $logo_id,
            'logo_url'                => $logo_url,
            'price_selector'          => $price_selector,
            'original_price_selector' => $original_price_selector,
            'cache_hours'             => $cache_hours,
            'default_shipping'        => $default_shipping,
        );

        update_option( self::OPTION_KEY, $stores );

        wp_send_json_success( array(
            'store'  => $stores[ $slug ],
            'stores' => $stores,
        ) );
    }

    /**
     * AJAX: delete a store.
     */
    public static function ajax_delete_store() {
        check_ajax_referer( 'tpc_stores_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No tienes permisos.' );
        }

        $slug   = sanitize_text_field( wp_unslash( $_POST['store_slug'] ?? '' ) );
        $stores = self::get_stores();

        if ( isset( $stores[ $slug ] ) ) {
            unset( $stores[ $slug ] );
            update_option( self::OPTION_KEY, $stores );
        }

        wp_send_json_success( array( 'stores' => $stores ) );
    }

    /**
     * Render settings page.
     */
    public static function render_settings_page() {
        $stores = self::get_stores();
        $nonce  = wp_create_nonce( 'tpc_stores_nonce' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Tiendas del Comparador de Precios', 'triathlon-price-comparator' ); ?></h1>
            <p><?php esc_html_e( 'Configura las tiendas con sus selectores CSS para extraer precios automáticamente.', 'triathlon-price-comparator' ); ?></p>

            <div id="tpc-stores-list" style="margin-bottom: 30px;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:70px;"><?php esc_html_e( 'Logo', 'triathlon-price-comparator' ); ?></th>
                            <th><?php esc_html_e( 'Nombre', 'triathlon-price-comparator' ); ?></th>
                            <th><?php esc_html_e( 'Selector precio', 'triathlon-price-comparator' ); ?></th>
                            <th><?php esc_html_e( 'Selector precio original', 'triathlon-price-comparator' ); ?></th>
                            <th style="width:60px;"><?php esc_html_e( 'Cache (h)', 'triathlon-price-comparator' ); ?></th>
                            <th style="width:90px;"><?php esc_html_e( 'Acciones', 'triathlon-price-comparator' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="tpc-stores-tbody">
                        <?php if ( empty( $stores ) ) : ?>
                            <tr class="tpc-no-stores">
                                <td colspan="6"><?php esc_html_e( 'No hay tiendas registradas.', 'triathlon-price-comparator' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $stores as $store ) : ?>
                                <tr data-slug="<?php echo esc_attr( $store['slug'] ); ?>">
                                    <td>
                                        <?php if ( ! empty( $store['logo_url'] ) ) : ?>
                                            <img src="<?php echo esc_url( $store['logo_url'] ); ?>" alt="" style="max-width:60px;max-height:30px;">
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo esc_html( $store['name'] ); ?></strong></td>
                                    <td><code><?php echo esc_html( $store['price_selector'] ?? '' ); ?></code></td>
                                    <td><code><?php echo esc_html( $store['original_price_selector'] ?? '—' ); ?></code></td>
                                    <td><?php echo esc_html( $store['cache_hours'] ?? 6 ); ?></td>
                                    <td>
                                        <button type="button" class="button button-small tpc-delete-store" data-slug="<?php echo esc_attr( $store['slug'] ); ?>">
                                            <?php esc_html_e( 'Eliminar', 'triathlon-price-comparator' ); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h2><?php esc_html_e( 'Añadir nueva tienda', 'triathlon-price-comparator' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="tpc-new-store-name"><?php esc_html_e( 'Nombre', 'triathlon-price-comparator' ); ?></label></th>
                    <td><input type="text" id="tpc-new-store-name" class="regular-text" placeholder="Ej: Alltricks"></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Logo', 'triathlon-price-comparator' ); ?></label></th>
                    <td>
                        <div id="tpc-new-store-logo-preview" style="margin-bottom:10px;"></div>
                        <input type="hidden" id="tpc-new-store-logo-id" value="">
                        <button type="button" class="button" id="tpc-new-store-logo-btn"><?php esc_html_e( 'Seleccionar logo', 'triathlon-price-comparator' ); ?></button>
                        <button type="button" class="button" id="tpc-new-store-logo-remove" style="display:none;"><?php esc_html_e( 'Quitar', 'triathlon-price-comparator' ); ?></button>
                    </td>
                </tr>
                <tr>
                    <th><label for="tpc-new-price-selector"><?php esc_html_e( 'Selector CSS del precio', 'triathlon-price-comparator' ); ?></label></th>
                    <td>
                        <input type="text" id="tpc-new-price-selector" class="regular-text" placeholder="Ej: .product-price .current-price, span.price">
                        <p class="description">
                            <?php esc_html_e( 'Selector CSS que apunta al elemento HTML que contiene el precio en la página del producto. Usa el inspector del navegador para encontrarlo.', 'triathlon-price-comparator' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="tpc-new-original-price-selector"><?php esc_html_e( 'Selector precio original (opcional)', 'triathlon-price-comparator' ); ?></label></th>
                    <td>
                        <input type="text" id="tpc-new-original-price-selector" class="regular-text" placeholder="Ej: .product-price .old-price, span.was-price">
                        <p class="description">
                            <?php esc_html_e( 'Para calcular el % de descuento automáticamente. Si no lo pones, no se mostrará descuento.', 'triathlon-price-comparator' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="tpc-new-default-shipping"><?php esc_html_e( 'Texto de envío por defecto', 'triathlon-price-comparator' ); ?></label></th>
                    <td><input type="text" id="tpc-new-default-shipping" class="regular-text" value="Envío gratuito"></td>
                </tr>
                <tr>
                    <th><label for="tpc-new-cache-hours"><?php esc_html_e( 'Horas de caché', 'triathlon-price-comparator' ); ?></label></th>
                    <td>
                        <input type="number" id="tpc-new-cache-hours" class="small-text" value="6" min="1" max="72">
                        <p class="description"><?php esc_html_e( 'Cada cuántas horas se re-consulta el precio de esta tienda.', 'triathlon-price-comparator' ); ?></p>
                    </td>
                </tr>
            </table>
            <p>
                <button type="button" class="button button-primary" id="tpc-add-store-btn">
                    <?php esc_html_e( 'Añadir tienda', 'triathlon-price-comparator' ); ?>
                </button>
            </p>
            <input type="hidden" id="tpc-stores-nonce" value="<?php echo esc_attr( $nonce ); ?>">
        </div>

        <script>
        jQuery(function($) {
            var nonce = $('#tpc-stores-nonce').val();

            $('#tpc-new-store-logo-btn').on('click', function(e) {
                e.preventDefault();
                var frame = wp.media({ title: 'Seleccionar logo', button: { text: 'Usar este logo' }, multiple: false, library: { type: 'image' } });
                frame.on('select', function() {
                    var att = frame.state().get('selection').first().toJSON();
                    $('#tpc-new-store-logo-id').val(att.id);
                    var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
                    $('#tpc-new-store-logo-preview').html('<img src="'+url+'" style="max-width:120px;max-height:50px;">');
                    $('#tpc-new-store-logo-remove').show();
                });
                frame.open();
            });

            $('#tpc-new-store-logo-remove').on('click', function() {
                $('#tpc-new-store-logo-id').val('');
                $('#tpc-new-store-logo-preview').html('');
                $(this).hide();
            });

            $('#tpc-add-store-btn').on('click', function() {
                var name = $('#tpc-new-store-name').val().trim();
                var priceSelector = $('#tpc-new-price-selector').val().trim();

                if (!name) { alert('Introduce el nombre de la tienda.'); return; }
                if (!priceSelector) { alert('El selector CSS del precio es obligatorio.'); return; }

                $.post(ajaxurl, {
                    action: 'tpc_add_store',
                    nonce: nonce,
                    store_name: name,
                    store_logo_id: $('#tpc-new-store-logo-id').val(),
                    price_selector: priceSelector,
                    original_price_selector: $('#tpc-new-original-price-selector').val().trim(),
                    default_shipping: $('#tpc-new-default-shipping').val().trim(),
                    cache_hours: $('#tpc-new-cache-hours').val()
                }, function(response) {
                    if (response.success) { location.reload(); }
                    else { alert(response.data); }
                });
            });

            $(document).on('click', '.tpc-delete-store', function() {
                if (!confirm('¿Eliminar esta tienda?')) return;
                $.post(ajaxurl, {
                    action: 'tpc_delete_store',
                    nonce: nonce,
                    store_slug: $(this).data('slug')
                }, function(response) {
                    if (response.success) location.reload();
                });
            });
        });
        </script>
        <?php
    }
}
