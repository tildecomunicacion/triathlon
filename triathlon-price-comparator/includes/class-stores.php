<?php
/**
 * Manages the store registry for the price comparator.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TPC_Stores {

    /**
     * Option key for custom stores.
     */
    const OPTION_KEY = 'tpc_stores';

    /**
     * Initialize stores admin page.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'wp_ajax_tpc_add_store', array( __CLASS__, 'ajax_add_store' ) );
        add_action( 'wp_ajax_tpc_delete_store', array( __CLASS__, 'ajax_delete_store' ) );
    }

    /**
     * Add admin menu page.
     */
    public static function add_menu_page() {
        add_options_page(
            __( 'Tiendas del Comparador', 'triathlon-price-comparator' ),
            __( 'Tiendas Comparador', 'triathlon-price-comparator' ),
            'manage_options',
            'tpc-stores',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    /**
     * Register settings.
     */
    public static function register_settings() {
        register_setting( 'tpc_stores_group', self::OPTION_KEY );
    }

    /**
     * Get all registered stores.
     *
     * @return array
     */
    public static function get_stores() {
        $stores = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $stores ) ) {
            $stores = array();
        }
        return $stores;
    }

    /**
     * AJAX handler to add a store.
     */
    public static function ajax_add_store() {
        check_ajax_referer( 'tpc_stores_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No tienes permisos.' );
        }

        $name    = sanitize_text_field( wp_unslash( $_POST['store_name'] ?? '' ) );
        $logo_id = absint( $_POST['store_logo_id'] ?? 0 );

        if ( empty( $name ) ) {
            wp_send_json_error( 'El nombre es obligatorio.' );
        }

        $stores   = self::get_stores();
        $slug     = sanitize_title( $name );
        $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

        $stores[ $slug ] = array(
            'name'    => $name,
            'slug'    => $slug,
            'logo_id' => $logo_id,
            'logo_url' => $logo_url,
        );

        update_option( self::OPTION_KEY, $stores );

        wp_send_json_success( array(
            'store' => $stores[ $slug ],
            'stores' => $stores,
        ) );
    }

    /**
     * AJAX handler to delete a store.
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
     * Render the settings page.
     */
    public static function render_settings_page() {
        $stores = self::get_stores();
        $nonce  = wp_create_nonce( 'tpc_stores_nonce' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Tiendas del Comparador de Precios', 'triathlon-price-comparator' ); ?></h1>
            <p><?php esc_html_e( 'Añade las tiendas que aparecerán disponibles en el comparador de precios.', 'triathlon-price-comparator' ); ?></p>

            <div id="tpc-stores-list" style="margin-bottom: 30px;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 80px;"><?php esc_html_e( 'Logo', 'triathlon-price-comparator' ); ?></th>
                            <th><?php esc_html_e( 'Nombre', 'triathlon-price-comparator' ); ?></th>
                            <th style="width: 150px;"><?php esc_html_e( 'Slug', 'triathlon-price-comparator' ); ?></th>
                            <th style="width: 100px;"><?php esc_html_e( 'Acciones', 'triathlon-price-comparator' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="tpc-stores-tbody">
                        <?php if ( empty( $stores ) ) : ?>
                            <tr class="tpc-no-stores">
                                <td colspan="4"><?php esc_html_e( 'No hay tiendas registradas.', 'triathlon-price-comparator' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $stores as $store ) : ?>
                                <tr data-slug="<?php echo esc_attr( $store['slug'] ); ?>">
                                    <td>
                                        <?php if ( ! empty( $store['logo_url'] ) ) : ?>
                                            <img src="<?php echo esc_url( $store['logo_url'] ); ?>" alt="" style="max-width: 60px; max-height: 30px;">
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $store['name'] ); ?></td>
                                    <td><code><?php echo esc_html( $store['slug'] ); ?></code></td>
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
                    <th><label for="tpc-new-store-name"><?php esc_html_e( 'Nombre de la tienda', 'triathlon-price-comparator' ); ?></label></th>
                    <td><input type="text" id="tpc-new-store-name" class="regular-text" placeholder="Ej: Alltricks"></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Logo', 'triathlon-price-comparator' ); ?></label></th>
                    <td>
                        <div id="tpc-new-store-logo-preview" style="margin-bottom: 10px;"></div>
                        <input type="hidden" id="tpc-new-store-logo-id" value="">
                        <button type="button" class="button" id="tpc-new-store-logo-btn">
                            <?php esc_html_e( 'Seleccionar logo', 'triathlon-price-comparator' ); ?>
                        </button>
                        <button type="button" class="button" id="tpc-new-store-logo-remove" style="display:none;">
                            <?php esc_html_e( 'Quitar logo', 'triathlon-price-comparator' ); ?>
                        </button>
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

            // Media uploader for logo
            $('#tpc-new-store-logo-btn').on('click', function(e) {
                e.preventDefault();
                var frame = wp.media({
                    title: '<?php echo esc_js( __( 'Seleccionar logo', 'triathlon-price-comparator' ) ); ?>',
                    button: { text: '<?php echo esc_js( __( 'Usar este logo', 'triathlon-price-comparator' ) ); ?>' },
                    multiple: false,
                    library: { type: 'image' }
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#tpc-new-store-logo-id').val(attachment.id);
                    var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                    $('#tpc-new-store-logo-preview').html('<img src="' + url + '" style="max-width:120px;max-height:50px;">');
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
                var name = $('#tpc-new-store-name').val().trim();
                var logoId = $('#tpc-new-store-logo-id').val();

                if (!name) {
                    alert('<?php echo esc_js( __( 'Introduce el nombre de la tienda.', 'triathlon-price-comparator' ) ); ?>');
                    return;
                }

                $.post(ajaxurl, {
                    action: 'tpc_add_store',
                    nonce: nonce,
                    store_name: name,
                    store_logo_id: logoId
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                });
            });

            // Delete store
            $(document).on('click', '.tpc-delete-store', function() {
                if (!confirm('<?php echo esc_js( __( '¿Eliminar esta tienda?', 'triathlon-price-comparator' ) ); ?>')) return;

                var slug = $(this).data('slug');
                $.post(ajaxurl, {
                    action: 'tpc_delete_store',
                    nonce: nonce,
                    store_slug: slug
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });
        });
        </script>
        <?php
    }
}
