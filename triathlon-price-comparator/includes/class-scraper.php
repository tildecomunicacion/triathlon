<?php
/**
 * Price scraper: auto-detects prices from product pages.
 *
 * Extraction priority:
 * 1. JSON-LD structured data (most reliable, used by almost all e-commerce)
 * 2. Open Graph / meta tags (og:price:amount, product:price:amount)
 * 3. Microdata (itemprop="price")
 * 4. Custom CSS selector (fallback for unusual sites)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TPC_Scraper {

    const TRANSIENT_PREFIX = 'tpc_price_';

    /**
     * Get price data for a URL, using cache.
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
     * Resolve affiliate/redirect URLs to the real product URL.
     *
     * Supports: AWIN, Tradedoubler, TradeTracker, CJ, ShareASale,
     * Webgains, Rakuten, and generic redirect parameters.
     */
    public static function resolve_affiliate_url( $url ) {
        $parsed = wp_parse_url( $url );
        $host   = isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';
        $query  = array();

        if ( ! empty( $parsed['query'] ) ) {
            parse_str( $parsed['query'], $query );
        }

        // AWIN (awin1.com) — real URL in "ued" parameter.
        if ( strpos( $host, 'awin1.com' ) !== false ) {
            if ( ! empty( $query['ued'] ) ) {
                return urldecode( $query['ued'] );
            }
        }

        // Tradedoubler — real URL in "url" parameter.
        if ( strpos( $host, 'tradedoubler.com' ) !== false ) {
            if ( ! empty( $query['url'] ) ) {
                return urldecode( $query['url'] );
            }
        }

        // TradeTracker — real URL in "r" or "url" parameter.
        if ( strpos( $host, 'tradetracker.' ) !== false ) {
            foreach ( array( 'r', 'url' ) as $param ) {
                if ( ! empty( $query[ $param ] ) ) {
                    return urldecode( $query[ $param ] );
                }
            }
        }

        // CJ (Commission Junction) — real URL in "url" parameter.
        if ( strpos( $host, 'anrdoezrs.net' ) !== false || strpos( $host, 'dpbolvw.net' ) !== false || strpos( $host, 'jdoqocy.com' ) !== false ) {
            if ( ! empty( $query['url'] ) ) {
                return urldecode( $query['url'] );
            }
        }

        // ShareASale — real URL in "urllink" parameter.
        if ( strpos( $host, 'shareasale.com' ) !== false ) {
            if ( ! empty( $query['urllink'] ) ) {
                return urldecode( $query['urllink'] );
            }
        }

        // Webgains — real URL in "wgtarget" parameter.
        if ( strpos( $host, 'webgains.' ) !== false ) {
            if ( ! empty( $query['wgtarget'] ) ) {
                return urldecode( $query['wgtarget'] );
            }
        }

        // Rakuten / LinkShare — real URL in "murl" parameter.
        if ( strpos( $host, 'click.linksynergy.com' ) !== false ) {
            if ( ! empty( $query['murl'] ) ) {
                return urldecode( $query['murl'] );
            }
        }

        // Generic: try common redirect parameters.
        foreach ( array( 'url', 'redirect', 'dest', 'destination', 'target', 'goto', 'link' ) as $param ) {
            if ( ! empty( $query[ $param ] ) ) {
                $candidate = urldecode( $query[ $param ] );
                if ( filter_var( $candidate, FILTER_VALIDATE_URL ) ) {
                    return $candidate;
                }
            }
        }

        // Not an affiliate URL or unknown format — use as is.
        return $url;
    }

    /**
     * Force-refresh the price.
     */
    public static function refresh_price( $url, $store_slug ) {
        $store = TPC_Stores::get_store( $store_slug );
        if ( ! $store ) {
            return null;
        }

        // Resolve affiliate URL to the real product page.
        $product_url = self::resolve_affiliate_url( $url );

        $html = self::fetch_html( $product_url );
        if ( ! $html ) {
            return null;
        }

        // Try all extraction methods in order of reliability.
        $price          = null;
        $original_price = null;
        $method         = '';

        // 1. JSON-LD structured data.
        $jsonld = self::extract_from_jsonld( $html );
        if ( $jsonld ) {
            $price          = $jsonld['price'];
            $original_price = $jsonld['original_price'];
            $method         = 'json-ld';
        }

        // 2. Meta tags (Open Graph / product meta).
        if ( null === $price ) {
            $meta = self::extract_from_meta_tags( $html );
            if ( $meta ) {
                $price  = $meta['price'];
                $method = 'meta-tags';
            }
        }

        // 3. Microdata (itemprop="price").
        if ( null === $price ) {
            $microdata = self::extract_from_microdata( $html );
            if ( $microdata ) {
                $price          = $microdata['price'];
                $original_price = $microdata['original_price'];
                $method         = 'microdata';
            }
        }

        // 4. Custom CSS selector (fallback).
        if ( null === $price && ! empty( $store['price_selector'] ) ) {
            $price  = self::extract_with_selector( $html, $store['price_selector'] );
            $method = 'css-selector';

            if ( $price && ! empty( $store['original_price_selector'] ) ) {
                $original_price = self::extract_with_selector( $html, $store['original_price_selector'] );
            }
        }

        if ( null === $price ) {
            return null;
        }

        // Calculate discount.
        $discount = null;
        if ( $original_price && $original_price > 0 && $price < $original_price ) {
            $pct      = round( ( ( $original_price - $price ) / $original_price ) * 100 );
            $discount = '-' . $pct . '%';
        }

        // Extract product image.
        $image = self::extract_product_image( $html );

        // Extract product name.
        $product_name = self::extract_product_name( $html );

        $data = array(
            'price'          => $price,
            'original_price' => $original_price,
            'discount'       => $discount,
            'image'          => $image,
            'product_name'   => $product_name,
            'product_url'    => $product_url,
            'method'         => $method,
            'fetched_at'     => time(),
        );

        $cache_hours = isset( $store['cache_hours'] ) ? absint( $store['cache_hours'] ) : 6;
        if ( $cache_hours < 1 ) {
            $cache_hours = 6;
        }
        set_transient( self::make_cache_key( $url ), $data, $cache_hours * HOUR_IN_SECONDS );

        return $data;
    }

    /**
     * Extract price from JSON-LD structured data.
     * Most e-commerce sites include this for SEO.
     */
    private static function extract_from_jsonld( $html ) {
        // Find all JSON-LD script blocks.
        if ( ! preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches ) ) {
            return null;
        }

        foreach ( $matches[1] as $json_str ) {
            $data = json_decode( trim( $json_str ), true );
            if ( ! $data ) {
                continue;
            }

            // Handle @graph arrays.
            $items = array();
            if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
                $items = $data['@graph'];
            } else {
                $items = array( $data );
            }

            foreach ( $items as $item ) {
                $result = self::parse_jsonld_item( $item );
                if ( $result ) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Parse a single JSON-LD item for price data.
     */
    private static function parse_jsonld_item( $item ) {
        if ( ! is_array( $item ) ) {
            return null;
        }

        $type = $item['@type'] ?? '';

        // Direct Product type.
        if ( in_array( $type, array( 'Product', 'IndividualProduct', 'ProductModel' ), true ) ) {
            return self::extract_jsonld_offers( $item );
        }

        // Check nested items.
        if ( isset( $item['mainEntity'] ) ) {
            $result = self::parse_jsonld_item( $item['mainEntity'] );
            if ( $result ) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Extract price from JSON-LD offers.
     */
    private static function extract_jsonld_offers( $product ) {
        $offers = $product['offers'] ?? null;
        if ( ! $offers ) {
            return null;
        }

        // Single offer.
        if ( isset( $offers['price'] ) ) {
            return self::parse_jsonld_offer( $offers );
        }

        // AggregateOffer.
        if ( isset( $offers['lowPrice'] ) ) {
            $price = self::parse_price_string( (string) $offers['lowPrice'] );
            $high  = isset( $offers['highPrice'] ) ? self::parse_price_string( (string) $offers['highPrice'] ) : null;
            if ( $price ) {
                return array(
                    'price'          => $price,
                    'original_price' => ( $high && $high > $price ) ? $high : null,
                );
            }
        }

        // Array of offers — pick the lowest price.
        if ( is_array( $offers ) && isset( $offers[0] ) ) {
            $best = null;
            foreach ( $offers as $offer ) {
                $parsed = self::parse_jsonld_offer( $offer );
                if ( $parsed && ( null === $best || $parsed['price'] < $best['price'] ) ) {
                    $best = $parsed;
                }
            }
            return $best;
        }

        // Offers list inside "offers".
        if ( isset( $offers['offers'] ) && is_array( $offers['offers'] ) ) {
            $best = null;
            foreach ( $offers['offers'] as $offer ) {
                $parsed = self::parse_jsonld_offer( $offer );
                if ( $parsed && ( null === $best || $parsed['price'] < $best['price'] ) ) {
                    $best = $parsed;
                }
            }
            return $best;
        }

        return null;
    }

    /**
     * Parse a single JSON-LD offer object.
     */
    private static function parse_jsonld_offer( $offer ) {
        if ( ! isset( $offer['price'] ) ) {
            return null;
        }

        $price = self::parse_price_string( (string) $offer['price'] );
        if ( ! $price ) {
            return null;
        }

        return array(
            'price'          => $price,
            'original_price' => null,
        );
    }

    /**
     * Extract product image URL from HTML.
     */
    private static function extract_product_image( $html ) {
        // 1. JSON-LD image.
        if ( preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches ) ) {
            foreach ( $matches[1] as $json_str ) {
                $data = json_decode( trim( $json_str ), true );
                if ( ! $data ) continue;

                $items = isset( $data['@graph'] ) ? $data['@graph'] : array( $data );
                foreach ( $items as $item ) {
                    $type = $item['@type'] ?? '';
                    if ( in_array( $type, array( 'Product', 'IndividualProduct', 'ProductModel' ), true ) ) {
                        $img = $item['image'] ?? null;
                        if ( is_array( $img ) ) {
                            $img = $img[0] ?? ( $img['url'] ?? null );
                        }
                        if ( $img && filter_var( $img, FILTER_VALIDATE_URL ) ) {
                            return $img;
                        }
                    }
                }
            }
        }

        // 2. Open Graph image.
        if ( preg_match( '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)/i', $html, $m ) ) {
            if ( filter_var( $m[1], FILTER_VALIDATE_URL ) ) {
                return $m[1];
            }
        }
        if ( preg_match( '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image/i', $html, $m ) ) {
            if ( filter_var( $m[1], FILTER_VALIDATE_URL ) ) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * Extract product name from HTML.
     */
    private static function extract_product_name( $html ) {
        // 1. JSON-LD name.
        if ( preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches ) ) {
            foreach ( $matches[1] as $json_str ) {
                $data = json_decode( trim( $json_str ), true );
                if ( ! $data ) continue;

                $items = isset( $data['@graph'] ) ? $data['@graph'] : array( $data );
                foreach ( $items as $item ) {
                    $type = $item['@type'] ?? '';
                    if ( in_array( $type, array( 'Product', 'IndividualProduct', 'ProductModel' ), true ) ) {
                        if ( ! empty( $item['name'] ) ) {
                            return sanitize_text_field( $item['name'] );
                        }
                    }
                }
            }
        }

        // 2. og:title.
        if ( preg_match( '/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)/i', $html, $m ) ) {
            return sanitize_text_field( $m[1] );
        }

        return null;
    }

    /**
     * Extract price from meta tags (Open Graph, product meta).
     */
    private static function extract_from_meta_tags( $html ) {
        $selectors = array(
            'product:price:amount',
            'og:price:amount',
            'twitter:data1',
        );

        foreach ( $selectors as $name ) {
            if ( preg_match( '/<meta[^>]+(?:property|name)=["\']' . preg_quote( $name, '/' ) . '["\'][^>]+content=["\']([^"\']+)/i', $html, $m ) ) {
                $price = self::parse_price_string( $m[1] );
                if ( $price ) {
                    return array( 'price' => $price, 'original_price' => null );
                }
            }
            // Reversed attribute order.
            if ( preg_match( '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']' . preg_quote( $name, '/' ) . '/i', $html, $m ) ) {
                $price = self::parse_price_string( $m[1] );
                if ( $price ) {
                    return array( 'price' => $price, 'original_price' => null );
                }
            }
        }

        return null;
    }

    /**
     * Extract price from HTML microdata (itemprop="price").
     */
    private static function extract_from_microdata( $html ) {
        libxml_use_internal_errors( true );
        $doc = new DOMDocument();
        $doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();

        $xpath = new DOMXPath( $doc );

        // itemprop="price" with content attribute.
        $nodes = $xpath->query( '//*[@itemprop="price"]' );
        $price = null;

        if ( $nodes && $nodes->length > 0 ) {
            foreach ( $nodes as $node ) {
                $val = $node->getAttribute( 'content' );
                if ( empty( $val ) ) {
                    $val = trim( $node->textContent );
                }
                $parsed = self::parse_price_string( $val );
                if ( $parsed && $parsed > 0 ) {
                    $price = $parsed;
                    break;
                }
            }
        }

        if ( ! $price ) {
            return null;
        }

        // Try to find original price.
        $original_price = null;
        $original_nodes = $xpath->query( '//*[contains(@class,"old") or contains(@class,"original") or contains(@class,"was") or contains(@class,"regular") or contains(@class,"before")]' );
        if ( $original_nodes ) {
            foreach ( $original_nodes as $node ) {
                $val    = trim( $node->textContent );
                $parsed = self::parse_price_string( $val );
                if ( $parsed && $parsed > $price ) {
                    $original_price = $parsed;
                    break;
                }
            }
        }

        return array(
            'price'          => $price,
            'original_price' => $original_price,
        );
    }

    /**
     * Extract price using a CSS selector (fallback).
     */
    private static function extract_with_selector( $html, $selector ) {
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
     * Fetch HTML following redirects.
     */
    private static function fetch_html( $url ) {
        $response = wp_remote_get( $url, array(
            'timeout'     => 15,
            'redirection' => 10,
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
     * Parse a price string into a float.
     */
    public static function parse_price_string( $text ) {
        $text = preg_replace( '/[€$£¥\s\x{00a0}]/u', '', $text );

        if ( empty( $text ) ) {
            return null;
        }

        // 1.234,56
        if ( preg_match( '/^[\d.]+,\d{1,2}$/', $text ) ) {
            $text = str_replace( '.', '', $text );
            $text = str_replace( ',', '.', $text );
        }
        // 1,234.56
        elseif ( preg_match( '/^[\d,]+\.\d{1,2}$/', $text ) ) {
            $text = str_replace( ',', '', $text );
        }
        // 153,00
        elseif ( preg_match( '/^\d+,\d{1,2}$/', $text ) ) {
            $text = str_replace( ',', '.', $text );
        }

        $value = floatval( $text );
        return $value > 0 ? $value : null;
    }

    /**
     * Convert CSS selector to XPath.
     */
    public static function css_to_xpath( $selector ) {
        $selector = trim( $selector );

        if ( strpos( $selector, ',' ) !== false ) {
            $parts  = array_map( 'trim', explode( ',', $selector ) );
            $xpaths = array_map( array( __CLASS__, 'css_to_xpath' ), $parts );
            return implode( ' | ', $xpaths );
        }

        $tokens = preg_split( '/\s+/', $selector );
        $xpath  = '';

        foreach ( $tokens as $token ) {
            $xpath .= '//' . self::token_to_xpath( $token );
        }

        return $xpath;
    }

    private static function token_to_xpath( $token ) {
        $tag        = '*';
        $conditions = array();

        if ( preg_match( '/^([a-zA-Z][a-zA-Z0-9]*)([\.\#\[].*)?$/', $token, $m ) ) {
            $tag   = $m[1];
            $token = isset( $m[2] ) ? $m[2] : '';
        }

        if ( preg_match( '/#([a-zA-Z0-9_-]+)/', $token, $m ) ) {
            $conditions[] = "@id='" . $m[1] . "'";
            $token = str_replace( $m[0], '', $token );
        }

        while ( preg_match( '/\.([a-zA-Z0-9_-]+)/', $token, $m ) ) {
            $conditions[] = "contains(concat(' ',normalize-space(@class),' '),' " . $m[1] . " ')";
            $token = preg_replace( '/' . preg_quote( $m[0], '/' ) . '/', '', $token, 1 );
        }

        if ( empty( $conditions ) ) {
            return $tag;
        }

        return $tag . '[' . implode( ' and ', $conditions ) . ']';
    }

    private static function make_cache_key( $url ) {
        return self::TRANSIENT_PREFIX . md5( $url );
    }

    public static function delete_cache( $url ) {
        delete_transient( self::make_cache_key( $url ) );
    }

    /**
     * Format price for display (European format).
     */
    public static function format_price( $price ) {
        if ( null === $price || false === $price ) {
            return '—';
        }
        return number_format( (float) $price, 2, ',', '.' );
    }
}
