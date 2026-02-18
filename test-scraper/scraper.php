<?php
/**
 * Standalone scraper — same logic as the WP plugin, zero WordPress dependency.
 */

class Scraper {

    /**
     * Resolve affiliate/redirect URLs to the real product URL.
     */
    public static function resolve_affiliate_url( $url ) {
        $parsed = parse_url( $url );
        $host   = isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';
        $query  = array();

        if ( ! empty( $parsed['query'] ) ) {
            parse_str( $parsed['query'], $query );
        }

        // AWIN (awin1.com)
        if ( strpos( $host, 'awin1.com' ) !== false ) {
            if ( ! empty( $query['ued'] ) ) {
                return urldecode( $query['ued'] );
            }
        }

        // Tradedoubler
        if ( strpos( $host, 'tradedoubler.com' ) !== false ) {
            if ( ! empty( $query['url'] ) ) {
                return urldecode( $query['url'] );
            }
        }

        // TradeTracker
        if ( strpos( $host, 'tradetracker.' ) !== false ) {
            foreach ( array( 'r', 'url' ) as $param ) {
                if ( ! empty( $query[ $param ] ) ) {
                    return urldecode( $query[ $param ] );
                }
            }
        }

        // CJ (Commission Junction)
        if ( strpos( $host, 'anrdoezrs.net' ) !== false || strpos( $host, 'dpbolvw.net' ) !== false || strpos( $host, 'jdoqocy.com' ) !== false ) {
            if ( ! empty( $query['url'] ) ) {
                return urldecode( $query['url'] );
            }
        }

        // ShareASale
        if ( strpos( $host, 'shareasale.com' ) !== false ) {
            if ( ! empty( $query['urllink'] ) ) {
                return urldecode( $query['urllink'] );
            }
        }

        // Webgains
        if ( strpos( $host, 'webgains.' ) !== false ) {
            if ( ! empty( $query['wgtarget'] ) ) {
                return urldecode( $query['wgtarget'] );
            }
        }

        // Rakuten / LinkShare
        if ( strpos( $host, 'click.linksynergy.com' ) !== false ) {
            if ( ! empty( $query['murl'] ) ) {
                return urldecode( $query['murl'] );
            }
        }

        // Generic redirect parameters
        foreach ( array( 'url', 'redirect', 'dest', 'destination', 'target', 'goto', 'link' ) as $param ) {
            if ( ! empty( $query[ $param ] ) ) {
                $candidate = urldecode( $query[ $param ] );
                if ( filter_var( $candidate, FILTER_VALIDATE_URL ) ) {
                    return $candidate;
                }
            }
        }

        return $url;
    }

    /**
     * Scrape a URL and return all extracted data.
     */
    public static function scrape( $url ) {
        $original_url = $url;
        $product_url  = self::resolve_affiliate_url( $url );

        $html = self::fetch_html( $product_url );
        if ( ! $html ) {
            return array( 'error' => 'No se pudo descargar la página: ' . $product_url );
        }

        $result = array(
            'affiliate_url' => $original_url,
            'product_url'   => $product_url,
            'html_length'   => strlen( $html ),
        );

        // 1. JSON-LD
        $jsonld = self::extract_from_jsonld( $html );
        if ( $jsonld ) {
            $result['price']          = $jsonld['price'];
            $result['original_price'] = $jsonld['original_price'];
            $result['method']         = 'json-ld';
        }

        // 2. Meta tags
        if ( empty( $result['price'] ) ) {
            $meta = self::extract_from_meta_tags( $html );
            if ( $meta ) {
                $result['price']  = $meta['price'];
                $result['method'] = 'meta-tags';
            }
        }

        // 3. Microdata
        if ( empty( $result['price'] ) ) {
            $microdata = self::extract_from_microdata( $html );
            if ( $microdata ) {
                $result['price']          = $microdata['price'];
                $result['original_price'] = $microdata['original_price'];
                $result['method']         = 'microdata';
            }
        }

        // Discount
        if ( ! empty( $result['price'] ) && ! empty( $result['original_price'] ) && $result['original_price'] > $result['price'] ) {
            $pct = round( ( ( $result['original_price'] - $result['price'] ) / $result['original_price'] ) * 100 );
            $result['discount'] = '-' . $pct . '%';
        }

        // Image
        $result['image'] = self::extract_product_image( $html );

        // Product name
        $result['product_name'] = self::extract_product_name( $html );

        // Collect ALL detected JSON-LD for debugging
        $result['jsonld_raw'] = self::get_all_jsonld( $html );

        // Collect meta tags for debugging
        $result['meta_tags'] = self::get_price_meta_tags( $html );

        return $result;
    }

    /**
     * Fetch HTML with cURL. Returns array with html and debug info.
     */
    public static function fetch_html( $url ) {
        $ch = curl_init();
        curl_setopt_array( $ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => array(
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
            ),
            CURLOPT_ENCODING       => '',
        ) );

        $html = curl_exec( $ch );
        $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $err  = curl_error( $ch );
        $final_url = curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL );
        curl_close( $ch );

        if ( $err ) {
            return null;
        }
        if ( $code < 200 || $code >= 400 ) {
            return null;
        }

        return $html;
    }

    /**
     * Scrape from a local HTML file (for testing).
     */
    public static function scrape_html( $html, $source_label = 'local' ) {
        $result = array(
            'affiliate_url' => $source_label,
            'product_url'   => $source_label,
            'html_length'   => strlen( $html ),
        );

        // 1. JSON-LD
        $jsonld = self::extract_from_jsonld( $html );
        if ( $jsonld ) {
            $result['price']          = $jsonld['price'];
            $result['original_price'] = $jsonld['original_price'];
            $result['method']         = 'json-ld';
        }

        // 2. Meta tags
        if ( empty( $result['price'] ) ) {
            $meta = self::extract_from_meta_tags( $html );
            if ( $meta ) {
                $result['price']  = $meta['price'];
                $result['method'] = 'meta-tags';
            }
        }

        // 3. Microdata
        if ( empty( $result['price'] ) ) {
            $microdata = self::extract_from_microdata( $html );
            if ( $microdata ) {
                $result['price']          = $microdata['price'];
                $result['original_price'] = $microdata['original_price'];
                $result['method']         = 'microdata';
            }
        }

        // Discount
        if ( ! empty( $result['price'] ) && ! empty( $result['original_price'] ) && $result['original_price'] > $result['price'] ) {
            $pct = round( ( ( $result['original_price'] - $result['price'] ) / $result['original_price'] ) * 100 );
            $result['discount'] = '-' . $pct . '%';
        }

        $result['image']        = self::extract_product_image( $html );
        $result['product_name'] = self::extract_product_name( $html );
        $result['jsonld_raw']   = self::get_all_jsonld( $html );
        $result['meta_tags']    = self::get_price_meta_tags( $html );

        return $result;
    }

    // ── JSON-LD ──────────────────────────────────────────

    public static function extract_from_jsonld( $html ) {
        if ( ! preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches ) ) {
            return null;
        }

        foreach ( $matches[1] as $json_str ) {
            $data = json_decode( trim( $json_str ), true );
            if ( ! $data ) continue;

            $items = array();
            if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
                $items = $data['@graph'];
            } else {
                $items = array( $data );
            }

            foreach ( $items as $item ) {
                $result = self::parse_jsonld_item( $item );
                if ( $result ) return $result;
            }
        }

        return null;
    }

    private static function parse_jsonld_item( $item ) {
        if ( ! is_array( $item ) ) return null;

        $type = $item['@type'] ?? '';

        if ( in_array( $type, array( 'Product', 'IndividualProduct', 'ProductModel' ), true ) ) {
            return self::extract_jsonld_offers( $item );
        }

        // ProductGroup (Nike, etc.) — variants inside hasVariant.
        if ( 'ProductGroup' === $type && ! empty( $item['hasVariant'] ) && is_array( $item['hasVariant'] ) ) {
            $best = null;
            foreach ( $item['hasVariant'] as $variant ) {
                $r = self::parse_jsonld_item( $variant );
                if ( $r && $r['price'] && ( null === $best || $r['price'] < $best['price'] ) ) {
                    $best = $r;
                }
            }
            if ( $best ) return $best;
        }

        if ( isset( $item['mainEntity'] ) ) {
            $result = self::parse_jsonld_item( $item['mainEntity'] );
            if ( $result ) return $result;
        }

        return null;
    }

    private static function extract_jsonld_offers( $product ) {
        $offers = $product['offers'] ?? null;
        if ( ! $offers ) return null;

        if ( isset( $offers['price'] ) ) {
            return self::parse_jsonld_offer( $offers );
        }

        if ( isset( $offers['lowPrice'] ) ) {
            $price = self::parse_price_string( (string) $offers['lowPrice'] );
            $high  = isset( $offers['highPrice'] ) ? self::parse_price_string( (string) $offers['highPrice'] ) : null;
            if ( $price ) {
                return array( 'price' => $price, 'original_price' => ( $high && $high > $price ) ? $high : null );
            }
        }

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

    private static function parse_jsonld_offer( $offer ) {
        if ( ! isset( $offer['price'] ) ) return null;
        $price = self::parse_price_string( (string) $offer['price'] );
        if ( ! $price ) return null;
        return array( 'price' => $price, 'original_price' => null );
    }

    // ── Meta tags ────────────────────────────────────────

    public static function extract_from_meta_tags( $html ) {
        $selectors = array( 'product:price:amount', 'og:price:amount', 'twitter:data1' );

        foreach ( $selectors as $name ) {
            if ( preg_match( '/<meta[^>]+(?:property|name)=["\']' . preg_quote( $name, '/' ) . '["\'][^>]+content=["\']([^"\']+)/i', $html, $m ) ) {
                $price = self::parse_price_string( $m[1] );
                if ( $price ) return array( 'price' => $price, 'original_price' => null );
            }
            if ( preg_match( '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']' . preg_quote( $name, '/' ) . '/i', $html, $m ) ) {
                $price = self::parse_price_string( $m[1] );
                if ( $price ) return array( 'price' => $price, 'original_price' => null );
            }
        }

        return null;
    }

    // ── Microdata ────────────────────────────────────────

    public static function extract_from_microdata( $html ) {
        libxml_use_internal_errors( true );
        $doc = new DOMDocument();
        $doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();

        $xpath = new DOMXPath( $doc );
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

        if ( ! $price ) return null;

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

        return array( 'price' => $price, 'original_price' => $original_price );
    }

    // ── Image & Name ─────────────────────────────────────

    public static function extract_product_image( $html ) {
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
                        if ( $img && filter_var( $img, FILTER_VALIDATE_URL ) ) return $img;
                    }
                }
            }
        }

        if ( preg_match( '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)/i', $html, $m ) ) {
            if ( filter_var( $m[1], FILTER_VALIDATE_URL ) ) return $m[1];
        }
        if ( preg_match( '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image/i', $html, $m ) ) {
            if ( filter_var( $m[1], FILTER_VALIDATE_URL ) ) return $m[1];
        }

        return null;
    }

    public static function extract_product_name( $html ) {
        if ( preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches ) ) {
            foreach ( $matches[1] as $json_str ) {
                $data = json_decode( trim( $json_str ), true );
                if ( ! $data ) continue;
                $items = isset( $data['@graph'] ) ? $data['@graph'] : array( $data );
                foreach ( $items as $item ) {
                    $type = $item['@type'] ?? '';
                    if ( in_array( $type, array( 'Product', 'IndividualProduct', 'ProductModel' ), true ) ) {
                        if ( ! empty( $item['name'] ) ) return trim( $item['name'] );
                    }
                }
            }
        }

        if ( preg_match( '/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)/i', $html, $m ) ) {
            return trim( $m[1] );
        }

        return null;
    }

    // ── Debug helpers ────────────────────────────────────

    public static function get_all_jsonld( $html ) {
        $result = array();
        if ( preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches ) ) {
            foreach ( $matches[1] as $json_str ) {
                $data = json_decode( trim( $json_str ), true );
                if ( $data ) $result[] = $data;
            }
        }
        return $result;
    }

    public static function get_price_meta_tags( $html ) {
        $tags = array();
        $names = array( 'product:price:amount', 'product:price:currency', 'og:price:amount', 'og:price:currency', 'og:image', 'og:title' );

        foreach ( $names as $name ) {
            if ( preg_match( '/<meta[^>]+(?:property|name)=["\']' . preg_quote( $name, '/' ) . '["\'][^>]+content=["\']([^"\']+)/i', $html, $m ) ) {
                $tags[ $name ] = $m[1];
            } elseif ( preg_match( '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']' . preg_quote( $name, '/' ) . '/i', $html, $m ) ) {
                $tags[ $name ] = $m[1];
            }
        }

        return $tags;
    }

    // ── Price parser ─────────────────────────────────────

    public static function parse_price_string( $text ) {
        $text = preg_replace( '/[€$£¥\s\x{00a0}]/u', '', $text );
        if ( empty( $text ) ) return null;

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

    public static function format_price( $price ) {
        if ( null === $price || false === $price ) return '—';
        return number_format( (float) $price, 2, ',', '.' );
    }
}
