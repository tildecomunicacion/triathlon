<?php
/**
 * Test local del scraper — interfaz web para probar URLs y archivos HTML locales.
 * Ejecutar: php -S localhost:8888 -t test-scraper
 */

require_once __DIR__ . '/scraper.php';

$url    = isset( $_GET['url'] ) ? trim( $_GET['url'] ) : '';
$sample = isset( $_GET['sample'] ) ? trim( $_GET['sample'] ) : '';
$action = isset( $_GET['action'] ) ? $_GET['action'] : '';
$result = null;
$source = '';

// Handle sample files
if ( $action === 'sample' && $sample ) {
    $file = __DIR__ . '/samples/' . basename( $sample );
    if ( file_exists( $file ) ) {
        $html   = file_get_contents( $file );
        $result = Scraper::scrape_html( $html, 'sample: ' . $sample );
        $source = $sample;
    } else {
        $result = array( 'error' => 'Archivo no encontrado: ' . $sample );
    }
}

// Handle URL scrape
if ( $action === 'scrape' && $url ) {
    $result = Scraper::scrape( $url );
    $source = $url;
}

// Handle pasted HTML
if ( $action === 'paste' && ! empty( $_POST['html'] ) ) {
    $html   = $_POST['html'];
    $result = Scraper::scrape_html( $html, 'HTML pegado (' . strlen($html) . ' bytes)' );
    $source = 'HTML pegado';
}

// List sample files
$samples = array();
foreach ( glob( __DIR__ . '/samples/*.html' ) as $f ) {
    $name = basename( $f, '.html' );
    $samples[ basename( $f ) ] = ucwords( str_replace( '-', ' ', $name ) );
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Scraper — Triathlon Price Comparator</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f0f2f5; color: #1a1a1a; padding: 20px; }
        .container { max-width: 960px; margin: 0 auto; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .subtitle { color: #666; margin-bottom: 20px; font-size: 14px; }

        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom: 20px; }
        .card h2 { font-size: 16px; margin-bottom: 12px; color: #374151; }

        .search-box form { display: flex; gap: 10px; }
        .search-box input[type="url"] { flex: 1; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; outline: none; transition: border .2s; }
        .search-box input[type="url"]:focus { border-color: #2563eb; }
        button, .btn { padding: 10px 20px; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; white-space: nowrap; text-decoration: none; display: inline-block; }
        button:hover, .btn:hover { background: #1d4ed8; }
        .btn-sm { padding: 8px 14px; font-size: 13px; }
        .btn-green { background: #16a34a; }
        .btn-green:hover { background: #15803d; }
        .btn-gray { background: #6b7280; }
        .btn-gray:hover { background: #4b5563; }

        .samples-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
        .sample-card { display: block; padding: 14px; background: #f8fafc; border: 2px solid #e5e7eb; border-radius: 8px; text-decoration: none; color: #1a1a1a; transition: all .15s; }
        .sample-card:hover { border-color: #2563eb; background: #eff6ff; }
        .sample-card .name { font-weight: 600; font-size: 14px; margin-bottom: 2px; }
        .sample-card .file { font-size: 11px; color: #9ca3af; }

        .tabs { display: flex; gap: 4px; margin-bottom: 16px; }
        .tab { padding: 8px 16px; border-radius: 6px 6px 0 0; cursor: pointer; font-size: 14px; font-weight: 500; background: #e5e7eb; color: #6b7280; border: none; }
        .tab.active { background: #fff; color: #1a1a1a; box-shadow: 0 -1px 3px rgba(0,0,0,.05); }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .price-card { display: flex; gap: 20px; align-items: flex-start; padding: 20px; background: #f8fafc; border-radius: 8px; margin-bottom: 16px; }
        .price-card img { width: 120px; height: 120px; object-fit: contain; border-radius: 8px; background: #fff; padding: 8px; border: 1px solid #e5e7eb; }
        .price-card .info { flex: 1; }
        .price-card .product-name { font-size: 16px; font-weight: 600; margin-bottom: 8px; }
        .price-card .price { font-size: 32px; font-weight: 700; color: #16a34a; }
        .price-card .original-price { font-size: 18px; color: #999; text-decoration: line-through; margin-left: 10px; }
        .price-card .discount { display: inline-block; background: #dcfce7; color: #16a34a; padding: 3px 10px; border-radius: 4px; font-size: 14px; font-weight: 600; margin-left: 8px; }
        .price-card .method { font-size: 12px; color: #6b7280; margin-top: 8px; }
        .price-card .method strong { color: #2563eb; }

        .debug { margin-top: 16px; }
        .debug summary { cursor: pointer; font-size: 14px; font-weight: 600; color: #6b7280; padding: 8px 0; user-select: none; }
        .debug pre { background: #1e293b; color: #e2e8f0; padding: 16px; border-radius: 8px; overflow-x: auto; font-size: 13px; line-height: 1.5; margin-top: 8px; max-height: 400px; overflow-y: auto; }

        .error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 16px; border-radius: 8px; }
        .no-price { background: #fffbeb; border: 1px solid #fed7aa; color: #92400e; padding: 16px; border-radius: 8px; }
        .success-badge { display: inline-block; background: #dcfce7; color: #16a34a; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .fail-badge { display: inline-block; background: #fef2f2; color: #991b1b; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }

        .urls-info { display: grid; grid-template-columns: auto 1fr; gap: 4px 12px; font-size: 13px; margin-bottom: 12px; }
        .urls-info dt { font-weight: 600; color: #6b7280; }
        .urls-info dd { word-break: break-all; }

        textarea { width: 100%; min-height: 150px; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 13px; font-family: monospace; resize: vertical; outline: none; }
        textarea:focus { border-color: #2563eb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Triathlon Price Comparator — Test Scraper</h1>
        <p class="subtitle">Comprueba que el scraper extrae correctamente precios, imágenes y nombres de producto.</p>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab <?= (!$action || $action === 'sample') ? 'active' : '' ?>" onclick="showTab('samples')">Ejemplos locales</button>
            <button class="tab <?= $action === 'scrape' ? 'active' : '' ?>" onclick="showTab('url')">URL externa</button>
            <button class="tab <?= $action === 'paste' ? 'active' : '' ?>" onclick="showTab('paste')">Pegar HTML</button>
        </div>

        <!-- Tab 1: Local Samples -->
        <div class="card tab-content <?= (!$action || $action === 'sample') ? 'active' : '' ?>" id="tab-samples">
            <h2>Archivos HTML de ejemplo (simulan tiendas reales)</h2>
            <div class="samples-grid">
                <?php foreach ( $samples as $file => $name ): ?>
                <a href="?action=sample&sample=<?= urlencode($file) ?>" class="sample-card">
                    <div class="name"><?= htmlspecialchars($name) ?></div>
                    <div class="file"><?= htmlspecialchars($file) ?></div>
                </a>
                <?php endforeach; ?>
            </div>
            <p style="margin-top:12px;font-size:13px;color:#6b7280;">Puedes añadir más archivos .html en <code>test-scraper/samples/</code></p>
        </div>

        <!-- Tab 2: External URL -->
        <div class="card search-box tab-content <?= $action === 'scrape' ? 'active' : '' ?>" id="tab-url">
            <h2>Scrape URL externa</h2>
            <form method="get">
                <input type="hidden" name="action" value="scrape">
                <input type="url" name="url" placeholder="https://www.nike.com/es/... o URL de AWIN..." value="<?= htmlspecialchars($url) ?>" required>
                <button type="submit">Scrape</button>
            </form>
            <p style="margin-top:8px;font-size:12px;color:#9ca3af;">Nota: necesitas conexión a Internet y que la tienda no bloquee el request.</p>
        </div>

        <!-- Tab 3: Paste HTML -->
        <div class="card tab-content <?= $action === 'paste' ? 'active' : '' ?>" id="tab-paste">
            <h2>Pegar HTML directamente</h2>
            <p style="font-size:13px;color:#6b7280;margin-bottom:10px;">Abre la web de una tienda en tu navegador, haz clic derecho → "Ver código fuente" → copia todo y pégalo aquí.</p>
            <form method="post" action="?action=paste">
                <textarea name="html" placeholder="Pega aquí el HTML de la página de producto..."><?= htmlspecialchars($_POST['html'] ?? '') ?></textarea>
                <button type="submit" style="margin-top:10px;" class="btn-green">Analizar HTML</button>
            </form>
        </div>

        <!-- Result -->
        <?php if ( $result ): ?>
        <div class="card" style="margin-top: 4px;">
            <h2>Resultado <?= !empty($result['price']) ? '<span class="success-badge">PRECIO ENCONTRADO</span>' : (isset($result['error']) ? '<span class="fail-badge">ERROR</span>' : '<span class="fail-badge">SIN PRECIO</span>') ?></h2>

            <?php if ( isset( $result['error'] ) ): ?>
                <div class="error">
                    <strong>Error:</strong> <?= htmlspecialchars( $result['error'] ) ?>
                </div>
            <?php else: ?>

                <dl class="urls-info">
                    <dt>Fuente:</dt>
                    <dd><?= htmlspecialchars( $result['affiliate_url'] ) ?></dd>
                    <?php if ( isset($result['product_url']) && $result['product_url'] !== $result['affiliate_url'] ): ?>
                    <dt>URL producto (resuelta):</dt>
                    <dd><?= htmlspecialchars( $result['product_url'] ) ?></dd>
                    <?php endif; ?>
                    <dt>HTML analizado:</dt>
                    <dd><?= number_format( $result['html_length'] ) ?> bytes</dd>
                </dl>

                <?php if ( ! empty( $result['price'] ) ): ?>
                <div class="price-card">
                    <?php if ( ! empty( $result['image'] ) ): ?>
                    <img src="<?= htmlspecialchars( $result['image'] ) ?>" alt="Producto" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div class="info">
                        <?php if ( ! empty( $result['product_name'] ) ): ?>
                        <div class="product-name"><?= htmlspecialchars( $result['product_name'] ) ?></div>
                        <?php endif; ?>
                        <div>
                            <span class="price"><?= Scraper::format_price( $result['price'] ) ?> &euro;</span>
                            <?php if ( ! empty( $result['original_price'] ) ): ?>
                            <span class="original-price"><?= Scraper::format_price( $result['original_price'] ) ?> &euro;</span>
                            <?php endif; ?>
                            <?php if ( ! empty( $result['discount'] ) ): ?>
                            <span class="discount"><?= htmlspecialchars( $result['discount'] ) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="method">Método de extracción: <strong><?= htmlspecialchars( $result['method'] ?? 'ninguno' ) ?></strong></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="no-price">
                    <strong>No se encontró precio.</strong> La página se descargó (<?= number_format( $result['html_length'] ) ?> bytes) pero no se detectó precio. Revisa los datos raw de abajo.
                </div>
                <?php endif; ?>

                <details class="debug" open>
                    <summary>Datos raw de depuración</summary>
                    <h3 style="font-size:14px;margin:12px 0 4px;">JSON-LD encontrados (<?= count( $result['jsonld_raw'] ?? [] ) ?>):</h3>
                    <pre><?= htmlspecialchars( json_encode( $result['jsonld_raw'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ?></pre>

                    <h3 style="font-size:14px;margin:12px 0 4px;">Meta tags de precio:</h3>
                    <pre><?= htmlspecialchars( json_encode( $result['meta_tags'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ?></pre>

                    <h3 style="font-size:14px;margin:12px 0 4px;">Resultado completo:</h3>
                    <pre><?= htmlspecialchars( json_encode( array_diff_key( $result, array_flip(['jsonld_raw','meta_tags']) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ?></pre>
                </details>

            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>

    <script>
    function showTab(name) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.getElementById('tab-' + name).classList.add('active');
        event.target.classList.add('active');
    }
    </script>
</body>
</html>
