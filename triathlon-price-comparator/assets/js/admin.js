/**
 * Triathlon Price Comparator - Admin JavaScript v2.1.0
 * Uses $(document) for event delegation to work with Gutenberg.
 */
(function ($) {
    'use strict';

    var entryIndex = 0;

    $(function () {
        var $entries = $('#tpc-entries-list .tpc-entry');
        entryIndex = $entries.length;
        $entries.addClass('collapsed');

        $('#tpc-entries-list').sortable({
            handle: '.tpc-entry-drag',
            placeholder: 'tpc-entry ui-sortable-placeholder',
            tolerance: 'pointer',
            update: function () { reindexEntries(); }
        });
    });

    function reindexEntries() {
        $('#tpc-entries-list .tpc-entry').each(function (i) {
            $(this).attr('data-index', i);
            $(this).find('[name]').each(function () {
                var name = $(this).attr('name');
                name = name.replace(/tpc_entries\[\d+\]/, 'tpc_entries[' + i + ']');
                $(this).attr('name', name);
            });
        });
    }

    // Add entry
    $(document).on('click', '#tpc-add-entry', function () {
        var template = $('#tpc-entry-template').html();
        template = template.replace(/\{\{INDEX\}\}/g, String(entryIndex));
        var $newEntry = $($.parseHTML(template.trim()));
        $('#tpc-entries-list').append($newEntry);
        entryIndex++;
        $('html, body').animate({ scrollTop: $newEntry.offset().top - 50 }, 300);
    });

    // Toggle collapse
    $(document).on('click', '.tpc-entry-toggle, .tpc-entry-title', function (e) {
        e.stopPropagation();
        $(this).closest('.tpc-entry').toggleClass('collapsed');
    });

    // Remove entry
    $(document).on('click', '.tpc-entry-remove', function (e) {
        e.stopPropagation();
        if (confirm(tpcAdmin.confirmRemove)) {
            $(this).closest('.tpc-entry').slideUp(200, function () {
                $(this).remove();
                reindexEntries();
            });
        }
    });

    // Update title on store change
    $(document).on('change', '.tpc-store-select', function () {
        var $entry = $(this).closest('.tpc-entry');
        var selectedText = $(this).find('option:selected').text().trim();
        if (selectedText && !selectedText.match(/^—/)) {
            $entry.find('.tpc-entry-title').text(selectedText);
        } else {
            $entry.find('.tpc-entry-title').text('Nueva tienda');
        }
    });

    // Test scrape
    $(document).on('click', '.tpc-test-scrape', function () {
        var $btn = $(this);
        // Walk up to the table, then search within it
        var $table = $btn.closest('table.tpc-entry-fields');
        var $entry = $btn.closest('.tpc-entry');

        // Try multiple ways to find the elements
        var $urlInput = $table.find('input[type="url"]');
        var $storeSelect = $table.find('select');
        var url = $urlInput.length ? $urlInput.val() : '';
        var storeSlug = $storeSelect.length ? $storeSelect.val() : '';

        // Fallback: search within .tpc-entry
        if (!url && $entry.length) {
            url = $entry.find('input[type="url"]').val() || '';
        }
        if (!storeSlug && $entry.length) {
            storeSlug = $entry.find('select').first().val() || '';
        }

        var $result = $btn.siblings('.tpc-scrape-result');
        if (!$result.length) {
            $result = $btn.parent().find('.tpc-scrape-result');
        }

        console.log('TPC Debug: url="' + url + '", storeSlug="' + storeSlug + '"');
        console.log('TPC Debug: $table found=' + $table.length + ', $entry found=' + $entry.length);
        console.log('TPC Debug: $urlInput found=' + $urlInput.length + ', $storeSelect found=' + $storeSelect.length);

        if (!url || !storeSlug) {
            $result.html('<span style="color:#dc3232;">Selecciona una tienda e introduce la URL primero. (debug: url=' + (url ? 'OK' : 'VACÍO') + ', tienda=' + (storeSlug ? 'OK' : 'VACÍO') + ')</span>');
            return;
        }

        $result.html('<span style="color:#666;">' + tpcAdmin.scraping + '</span>');

        $.post(tpcAdmin.ajaxUrl, {
            action: 'tpc_test_scrape',
            nonce: tpcAdmin.nonce,
            url: url.trim(),
            store_slug: storeSlug
        }, function (response) {
            if (response.success) {
                var html = '<span style="color:#2e7d32;font-weight:bold;">' + tpcAdmin.scrapeOk + ' ' + response.data.price + ' &euro;</span>';
                if (response.data.discount) {
                    html += ' <span style="background:#e8f5e9;color:#2e7d32;padding:2px 6px;border-radius:3px;font-size:12px;">' + response.data.discount + '</span>';
                }
                $result.html(html);
            } else {
                $result.html('<span style="color:#dc3232;">' + (response.data || tpcAdmin.scrapeFail) + '</span>');
            }
        }).fail(function () {
            $result.html('<span style="color:#dc3232;">Error de conexión.</span>');
        });
    });

    // Refresh all prices
    $(document).on('click', '#tpc-refresh-all-prices', function () {
        var $btn = $(this);
        var postId = $btn.data('post-id');
        $btn.prop('disabled', true).text('Refrescando...');

        $.post(tpcAdmin.ajaxUrl, {
            action: 'tpc_refresh_prices',
            nonce: tpcAdmin.nonce,
            post_id: postId
        }, function (response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data || 'Error al refrescar.');
                $btn.prop('disabled', false).html('&#x21bb; Refrescar todos los precios');
            }
        }).fail(function () {
            alert('Error de conexión.');
            $btn.prop('disabled', false).html('&#x21bb; Refrescar todos los precios');
        });
    });

})(jQuery);
