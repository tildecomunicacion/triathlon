<?php
/**
 * Store registry with pre-configured stores.
 * CSS selectors are optional — the scraper auto-detects prices.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TPC_Stores {

    const OPTION_KEY = 'tpc_stores';

    /**
     * Pre-configured stores (name + default selector as extra safety net).
     */
    private static $presets = array(
        'alltricks'       => array( 'name' => 'Alltricks' ),
        'top4running'     => array( 'name' => 'Top4Running' ),
        'running-emotion' => array( 'name' => 'Running Emotion' ),
        'runnea'          => array( 'name' => 'Runnea' ),
        'deporvillage'    => array( 'name' => 'Deporvillage' ),
        'tradeinn'        => array( 'name' => 'Tradeinn' ),
        'wiggle'          => array( 'name' => 'Wiggle' ),
        'chain-reaction'  => array( 'name' => 'Chain Reaction Cycles' ),
        'amazon'          => array( 'name' => 'Amazon' ),
        'decathlon'       => array( 'name' => 'Decathlon' ),
        'bike-inn'        => array( 'name' => 'BikeInn' ),
        'swim-inn'        => array( 'name' => 'SwimInn' ),
        'run-inn'         => array( 'name' => 'RunnerInn' ),
        'sportshoes'      => array( 'name' => 'SportsShoes' ),
        'i-run'           => array( 'name' => 'i-Run' ),
        'ekosport'        => array( 'name' => 'Ekosport' ),
        'lepape'          => array( 'name' => 'LePape' ),
    );

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
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

    public static function get_stores() {
        $stores = get_option( self::OPTION_KEY, array() );
        return is_array( $stores ) ? $stores : array();
    }

    public static function get_store( $slug ) {
        $stores = self::get_stores();
        return isset( $stores[ $slug ] ) ? $stores[ $slug ] : null;
    }

    public static function get_presets() {
        return self::$presets;
    }

    public static function ajax_add_store() {
        check_ajax_referer( 'tpc_stores_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No tienes permisos.' );
        }

        $name        = sanitize_text_field( wp_unslash( $_POST['store_name'] ?? '' ) );
        $logo_id     = absint( $_POST['store_logo_id'] ?? 0 );
        $cache_hours = absint( $_POST['cache_hours'] ?? 6 );
        $shipping    = sanitize_text_field( wp_unslash( $_POST['default_shipping'] ?? 'Envío gratuito' ) );

        if ( empty( $name ) ) {
            wp_send_json_error( 'El nombre es obligatorio.' );
        }

        $stores   = self::get_stores();
        $slug     = sanitize_title( $name );
        $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

        $stores[ $slug ] = array(
            'name'             => $name,
            'slug'             => $slug,
            'logo_id'          => $logo_id,
            'logo_url'         => $logo_url,
            'cache_hours'      => max( 1, $cache_hours ),
            'default_shipping' => $shipping,
            'price_selector'          => '',
            'original_price_selector' => '',
        );

        update_option( self::OPTION_KEY, $stores );
        wp_send_json_success( array( 'stores' => $stores ) );
    }

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

    public static function render_settings_page() {
        $stores  = self::get_stores();
        $presets = self::get_presets();
        $nonce   = wp_create_nonce( 'tpc_stores_nonce' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Tiendas del Comparador de Precios', 'triathlon-price-comparator' ); ?></h1>
            <p><?php esc_html_e( 'Añade las tiendas que usas. Solo necesitas el nombre y el logo. El precio se extrae automáticamente de la URL del producto.', 'triathlon-price-comparator' ); ?></p>

            <table class="wp-list-table widefat fixed striped" style="margin-bottom:30px;">
                <thead>
                    <tr>
                        <th style="width:70px;"><?php esc_html_e( 'Logo', 'triathlon-price-comparator' ); ?></th>
                        <th><?php esc_html_e( 'Nombre', 'triathlon-price-comparator' ); ?></th>
                        <th style="width:100px;"><?php esc_html_e( 'Cache (h)', 'triathlon-price-comparator' ); ?></th>
                        <th style="width:120px;"><?php esc_html_e( 'Envío', 'triathlon-price-comparator' ); ?></th>
                        <th style="width:90px;"><?php esc_html_e( 'Acciones', 'triathlon-price-comparator' ); ?></th>
                    </tr>
                </thead>
                <tbody id="tpc-stores-tbody">
                    <?php if ( empty( $stores ) ) : ?>
                        <tr class="tpc-no-stores"><td colspan="5"><?php esc_html_e( 'No hay tiendas registradas. Añade una abajo.', 'triathlon-price-comparator' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $stores as $store ) : ?>
                            <tr>
                                <td>
                                    <?php if ( ! empty( $store['logo_url'] ) ) : ?>
                                        <img src="<?php echo esc_url( $store['logo_url'] ); ?>" alt="" style="max-width:60px;max-height:30px;">
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo esc_html( $store['name'] ); ?></strong></td>
                                <td><?php echo esc_html( $store['cache_hours'] ?? 6 ); ?>h</td>
                                <td><?php echo esc_html( $store['default_shipping'] ?? '' ); ?></td>
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

            <h2><?php esc_html_e( 'Añadir tienda', 'triathlon-price-comparator' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Tienda', 'triathlon-price-comparator' ); ?></label></th>
                    <td>
                        <select id="tpc-preset-select">
                            <option value=""><?php esc_html_e( '— Seleccionar tienda conocida —', 'triathlon-price-comparator' ); ?></option>
                            <?php foreach ( $presets as $slug => $preset ) : ?>
                                <?php if ( ! isset( $stores[ $slug ] ) ) : ?>
                                    <option value="<?php echo esc_attr( $preset['name'] ); ?>">
                                        <?php echo esc_html( $preset['name'] ); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <option value="__custom__"><?php esc_html_e( '— Otra tienda (escribir nombre) —', 'triathlon-price-comparator' ); ?></option>
                        </select>
                        <input type="text" id="tpc-new-store-name" class="regular-text" placeholder="Nombre de la tienda" style="display:none;margin-top:8px;">
                    </td>
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
                    <th><label for="tpc-new-default-shipping"><?php esc_html_e( 'Texto de envío', 'triathlon-price-comparator' ); ?></label></th>
                    <td><input type="text" id="tpc-new-default-shipping" class="regular-text" value="Envío gratuito"></td>
                </tr>
                <tr>
                    <th><label for="tpc-new-cache-hours"><?php esc_html_e( 'Refrescar precio cada', 'triathlon-price-comparator' ); ?></label></th>
                    <td>
                        <input type="number" id="tpc-new-cache-hours" class="small-text" value="6" min="1" max="72"> horas
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

            // Preset selector
            $('#tpc-preset-select').on('change', function() {
                var val = $(this).val();
                if (val === '__custom__') {
                    $('#tpc-new-store-name').show().focus();
                } else {
                    $('#tpc-new-store-name').hide().val('');
                }
            });

            // Logo upload
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

            // Add store
            $('#tpc-add-store-btn').on('click', function() {
                var presetVal = $('#tpc-preset-select').val();
                var name = presetVal === '__custom__' ? $('#tpc-new-store-name').val().trim() : presetVal;

                if (!name) { alert('Selecciona o escribe el nombre de la tienda.'); return; }

                $.post(ajaxurl, {
                    action: 'tpc_add_store',
                    nonce: nonce,
                    store_name: name,
                    store_logo_id: $('#tpc-new-store-logo-id').val(),
                    default_shipping: $('#tpc-new-default-shipping').val().trim(),
                    cache_hours: $('#tpc-new-cache-hours').val()
                }, function(r) {
                    if (r.success) location.reload();
                    else alert(r.data);
                });
            });

            // Delete
            $(document).on('click', '.tpc-delete-store', function() {
                if (!confirm('¿Eliminar esta tienda?')) return;
                $.post(ajaxurl, {
                    action: 'tpc_delete_store',
                    nonce: nonce,
                    store_slug: $(this).data('slug')
                }, function(r) { if (r.success) location.reload(); });
            });
        });
        </script>
        <?php
    }
}
