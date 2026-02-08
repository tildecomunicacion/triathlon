<?php
/**
 * Price scraper: fetches product pages and extracts prices via CSS selectors.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TPC_Scraper {

    const TRANSIENT_PREFIX = 'tpc_price_';

    /**
     * Get the price data for a given URL + store, using cache.
     *
     * @param string $url        Product/affiliate URL.
     * @param string $store_slug Store slug.
     * @return array|null { price: string, original_price: string|null, discount: string|null, fetched_at: int } or null on failure.
     */
    public static function get_price( $url, $store_slug ) {
        $cache_key = self::make_cache_key( $url );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        return self::refresh_price( $url, $store_slug );
    }

    /**
     * Force-refresh the price for a URL.
     */
    public static function refresh_price( $url, $store_slug ) {
        $store = TPC_Stores::get_store( $store_slug );
        if ( ! $store || empty( $store['price_selector'] ) ) {
            return null;
        }

        $html = self::fetch_html( $url );
        if ( ! $html ) {
            return null;
        }

        $price          = self::extract_price( $html, $store['price_selector'] );
        $original_price = null;
        $discount       = null;

        if ( ! empty( $store['original_price_selector'] ) ) {
            $original_price = self::extract_price( $html, $store['original_price_selector'] );
        }

        if ( null === $price ) {
            return null;
        }

        // Calculate discount percentage.
        if ( $original_price && $original_price > 0 && $price < $original_price ) {
            $pct      = round( ( ( $original_price - $price ) / $original_price ) * 100 );
            $discount = '-' . $pct . '%';
        }

        $data = array(
            'price'          => $price,
            'original_price' => $original_price,
            'discount'       => $discount,
            'fetched_at'     => time(),
        );

        $cache_hours = isset( $store['cache_hours'] ) ? absint( $store['cache_hours'] ) : 6;
        set_transient( self::make_cache_key( $url ), $data, $cache_hours * HOUR_IN_SECONDS );

        return $data;
    }

    /**
     * Fetch HTML from a URL following redirects.
     */
    private static function fetch_html( $url ) {
        $response = wp_remote_get( $url, array(
            'timeout'     => 15,
            'redirection' => 5,
            'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'headers'     => array(
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
            ),
            'sslverify'   => false,
        ) );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 400 ) {
            return null;
        }

        return wp_remote_retrieve_body( $response );
    }

    /**
     * Extract a numeric price from HTML using a CSS selector.
     *
     * @param string $html     Full HTML of the page.
     * @param string $selector CSS selector (supports tag, .class, #id, and descendants).
     * @return float|null
     */
    private static function extract_price( $html, $selector ) {
        // Suppress HTML parsing warnings.
        libxml_use_internal_errors( true );

        $doc = new DOMDocument();
        $doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

        libxml_clear_errors();

        $xpath_query = self::css_to_xpath( $selector );
        $xpath       = new DOMXPath( $doc );
        $nodes       = $xpath->query( $xpath_query );

        if ( ! $nodes || 0 === $nodes->length ) {
            return null;
        }

        // Try each matching node until we find a valid price.
        foreach ( $nodes as $node ) {
            $text  = trim( $node->textContent );
            $price = self::parse_price_string( $text );
            if ( null !== $price && $price > 0 ) {
                return $price;
            }
        }

        return null;
    }

    /**
     * Parse a price string like "153,00 €" or "$153.00" into a float.
     */
    public static function parse_price_string( $text ) {
        // Remove currency symbols and whitespace.
        $text = preg_replace( '/[€$£¥\s\x{00a0}]/u', '', $text );

        if ( empty( $text ) ) {
            return null;
        }

        // Handle European format: 1.234,56 -> 1234.56
        if ( preg_match( '/^[\d.]+,\d{1,2}$/', $text ) ) {
            $text = str_replace( '.', '', $text );
            $text = str_replace( ',', '.', $text );
        }
        // Handle: 1,234.56 (already correct for floatval)
        elseif ( preg_match( '/^[\d,]+\.\d{1,2}$/', $text ) ) {
            $text = str_replace( ',', '', $text );
        }
        // Handle: 153,00 (European, no thousand separator)
        elseif ( preg_match( '/^\d+,\d{1,2}$/', $text ) ) {
            $text = str_replace( ',', '.', $text );
        }

        $value = floatval( $text );
        return $value > 0 ? $value : null;
    }

    /**
     * Convert a simple CSS selector to an XPath expression.
     *
     * Supports: tag, .class, #id, tag.class, tag#id,
     * descendants (space separated), and commas for multiple selectors.
     */
    public static function css_to_xpath( $selector ) {
        $selector = trim( $selector );

        // Handle comma-separated selectors (OR).
        if ( strpos( $selector, ',' ) !== false ) {
            $parts = array_map( 'trim', explode( ',', $selector ) );
            $xpaths = array_map( array( __CLASS__, 'css_to_xpath' ), $parts );
            return implode( ' | ', $xpaths );
        }

        // Handle descendant selectors (space separated).
        $tokens = preg_split( '/\s+/', $selector );
        $xpath  = '';

        foreach ( $tokens as $i => $token ) {
            $xpath .= ( 0 === $i ) ? '//' : '//';
            $xpath .= self::token_to_xpath( $token );
        }

        return $xpath;
    }

    /**
     * Convert a single CSS token to XPath.
     */
    private static function token_to_xpath( $token ) {
        $tag     = '*';
        $conditions = array();

        // Extract tag name.
        if ( preg_match( '/^([a-zA-Z][a-zA-Z0-9]*)([\.\#\[].*)?$/', $token, $m ) ) {
            $tag   = $m[1];
            $token = isset( $m[2] ) ? $m[2] : '';
        }

        // Extract #id.
        if ( preg_match( '/#([a-zA-Z0-9_-]+)/', $token, $m ) ) {
            $conditions[] = "@id='" . $m[1] . "'";
            $token = str_replace( $m[0], '', $token );
        }

        // Extract .class (multiple allowed).
        while ( preg_match( '/\.([a-zA-Z0-9_-]+)/', $token, $m ) ) {
            $conditions[] = "contains(concat(' ',normalize-space(@class),' '),' " . $m[1] . " ')";
            $token = preg_replace( '/' . preg_quote( $m[0], '/' ) . '/', '', $token, 1 );
        }

        // Extract [attribute] selectors.
        while ( preg_match( '/\[([a-zA-Z0-9_-]+)(?:=["\']?([^"\'\]]*)["\']?)?\]/', $token, $m ) ) {
            if ( isset( $m[2] ) ) {
                $conditions[] = "@" . $m[1] . "='" . $m[2] . "'";
            } else {
                $conditions[] = "@" . $m[1];
            }
            $token = str_replace( $m[0], '', $token );
        }

        if ( empty( $conditions ) ) {
            return $tag;
        }

        return $tag . '[' . implode( ' and ', $conditions ) . ']';
    }

    /**
     * Generate a transient cache key for a URL.
     */
    private static function make_cache_key( $url ) {
        return self::TRANSIENT_PREFIX . md5( $url );
    }

    /**
     * Delete cached price for a URL.
     */
    public static function delete_cache( $url ) {
        delete_transient( self::make_cache_key( $url ) );
    }

    /**
     * Format a numeric price for display (European format).
     */
    public static function format_price( $price ) {
        if ( null === $price || false === $price ) {
            return '—';
        }
        return number_format( (float) $price, 2, ',', '.' );
    }
}
