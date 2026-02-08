<?php
/**
 * Renders the [price_comparator] shortcode on the frontend.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TPC_Shortcode {

    /**
     * Initialize shortcode.
     */
    public static function init() {
        add_shortcode( 'price_comparator', array( __CLASS__, 'render' ) );
    }

    /**
     * Render the price comparator.
     */
    public static function render( $atts ) {
        $atts = shortcode_atts( array(
            'post_id'    => get_the_ID(),
            'title'      => '',
            'show_more'  => 3,
        ), $atts, 'price_comparator' );

        $post_id = absint( $atts['post_id'] );
        $entries = get_post_meta( $post_id, TPC_Meta_Box::META_KEY, true );

        if ( ! is_array( $entries ) || empty( $entries ) ) {
            return '';
        }

        $stores    = TPC_Stores::get_stores();
        $title     = ! empty( $atts['title'] ) ? $atts['title'] : get_the_title( $post_id ) . ' al mejor precio';
        $show_more = absint( $atts['show_more'] );

        ob_start();
        ?>
        <div class="tpc-comparator">
            <h3 class="tpc-comparator__title"><?php echo esc_html( $title ); ?></h3>

            <div class="tpc-comparator__list">
                <?php foreach ( $entries as $i => $entry ) :
                    $store = isset( $stores[ $entry['store_slug'] ] ) ? $stores[ $entry['store_slug'] ] : null;
                    if ( ! $store ) continue;

                    $hidden_class = ( $show_more > 0 && $i >= $show_more ) ? ' tpc-comparator__row--hidden' : '';
                ?>
                <div class="tpc-comparator__row<?php echo esc_attr( $hidden_class ); ?>">
                    <div class="tpc-comparator__store">
                        <?php if ( ! empty( $store['logo_url'] ) ) : ?>
                            <div class="tpc-comparator__logo">
                                <img src="<?php echo esc_url( $store['logo_url'] ); ?>"
                                     alt="<?php echo esc_attr( $store['name'] ); ?>">
                            </div>
                        <?php else : ?>
                            <div class="tpc-comparator__logo tpc-comparator__logo--text">
                                <?php echo esc_html( $store['name'] ); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $entry['shipping_text'] ) ) : ?>
                            <span class="tpc-comparator__shipping">
                                <?php echo esc_html( $entry['shipping_text'] ); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ( ! empty( $entry['coupon_code'] ) ) : ?>
                            <div class="tpc-comparator__coupon">
                                <span class="tpc-comparator__coupon-label">Código:</span>
                                <span class="tpc-comparator__coupon-code" data-code="<?php echo esc_attr( $entry['coupon_code'] ); ?>">
                                    <?php echo esc_html( $entry['coupon_code'] ); ?>
                                </span>
                                <button type="button" class="tpc-comparator__coupon-copy" data-code="<?php echo esc_attr( $entry['coupon_code'] ); ?>" title="Copiar código">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="tpc-comparator__discounts">
                        <?php if ( ! empty( $entry['discount'] ) ) : ?>
                            <span class="tpc-comparator__discount-badge">
                                <?php echo esc_html( $entry['discount'] ); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ( ! empty( $entry['extra_discount'] ) ) : ?>
                            <span class="tpc-comparator__discount-badge tpc-comparator__discount-badge--extra">
                                <?php echo esc_html( $entry['extra_discount'] ); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="tpc-comparator__price">
                        <span class="tpc-comparator__price-value">
                            <?php echo esc_html( $entry['price'] ); ?> &euro;
                        </span>
                    </div>

                    <div class="tpc-comparator__action">
                        <a href="<?php echo esc_url( $entry['affiliate_url'] ); ?>"
                           class="tpc-comparator__btn"
                           target="_blank"
                           rel="nofollow noopener sponsored">
                            VER OFERTA
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ( $show_more > 0 && count( $entries ) > $show_more ) : ?>
                <div class="tpc-comparator__more-wrap">
                    <button type="button" class="tpc-comparator__more-btn">
                        Ver más Precios
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
