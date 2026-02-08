<?php
/**
 * Renders the [price_comparator] shortcode.
 * Prices are fetched automatically from cached scraper data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TPC_Shortcode {

    public static function init() {
        add_shortcode( 'price_comparator', array( __CLASS__, 'render' ) );
    }

    /**
     * Render the comparator.
     */
    public static function render( $atts ) {
        $atts = shortcode_atts( array(
            'post_id'   => get_the_ID(),
            'title'     => '',
            'show_more' => 3,
        ), $atts, 'price_comparator' );

        $post_id = absint( $atts['post_id'] );
        $entries = get_post_meta( $post_id, TPC_Meta_Box::META_KEY, true );

        if ( ! is_array( $entries ) || empty( $entries ) ) {
            return '';
        }

        $stores    = TPC_Stores::get_stores();
        $title     = ! empty( $atts['title'] ) ? $atts['title'] : get_the_title( $post_id ) . ' al mejor precio';
        $show_more = absint( $atts['show_more'] );

        // Build rows with scraped price data.
        $rows = array();
        foreach ( $entries as $entry ) {
            $store = isset( $stores[ $entry['store_slug'] ] ) ? $stores[ $entry['store_slug'] ] : null;
            if ( ! $store ) {
                continue;
            }

            // Get price: manual override > scraped.
            $price_data = null;
            $price_display    = null;
            $discount_display = null;

            if ( ! empty( $entry['price_override'] ) ) {
                $price_display = $entry['price_override'];
            } else {
                $price_data = TPC_Scraper::get_price( $entry['affiliate_url'], $entry['store_slug'] );
                if ( $price_data && ! empty( $price_data['price'] ) ) {
                    $price_display    = TPC_Scraper::format_price( $price_data['price'] );
                    $discount_display = $price_data['discount'] ?? null;
                }
            }

            // Shipping text: entry override > store default.
            $shipping = ! empty( $entry['shipping_text'] ) ? $entry['shipping_text'] : ( $store['default_shipping'] ?? '' );

            $rows[] = array(
                'store'         => $store,
                'entry'         => $entry,
                'price_display' => $price_display,
                'discount'      => $discount_display,
                'shipping'      => $shipping,
                'sort_price'    => $price_data ? (float) $price_data['price'] : ( $price_display ? (float) str_replace( array( '.', ',' ), array( '', '.' ), $price_display ) : 99999 ),
            );
        }

        // Sort by price ascending (cheapest first).
        usort( $rows, function ( $a, $b ) {
            return $a['sort_price'] <=> $b['sort_price'];
        } );

        ob_start();
        ?>
        <div class="tpc-comparator">
            <h3 class="tpc-comparator__title"><?php echo esc_html( $title ); ?></h3>

            <div class="tpc-comparator__list">
                <?php foreach ( $rows as $i => $row ) :
                    $store = $row['store'];
                    $entry = $row['entry'];
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

                        <?php if ( ! empty( $row['shipping'] ) ) : ?>
                            <span class="tpc-comparator__shipping">
                                <?php echo esc_html( $row['shipping'] ); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ( ! empty( $entry['coupon_code'] ) ) : ?>
                            <div class="tpc-comparator__coupon">
                                <span class="tpc-comparator__coupon-label">Código:</span>
                                <span class="tpc-comparator__coupon-code"><?php echo esc_html( $entry['coupon_code'] ); ?></span>
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
                        <?php if ( ! empty( $row['discount'] ) ) : ?>
                            <span class="tpc-comparator__discount-badge">
                                <?php echo esc_html( $row['discount'] ); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="tpc-comparator__price">
                        <?php if ( $row['price_display'] ) : ?>
                            <span class="tpc-comparator__price-value">
                                <?php echo esc_html( $row['price_display'] ); ?> &euro;
                            </span>
                        <?php else : ?>
                            <span class="tpc-comparator__price-value tpc-comparator__price-value--unavailable">
                                —
                            </span>
                        <?php endif; ?>
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

            <?php if ( $show_more > 0 && count( $rows ) > $show_more ) : ?>
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
