/**
 * Triathlon Price Comparator - Admin JavaScript
 */
(function ($) {
    'use strict';

    var entryIndex = 0;

    $(function () {
        initExistingEntries();
        initSortable();
        initAddEntry();
        initDelegatedEvents();
    });

    function initExistingEntries() {
        var $entries = $('#tpc-entries-list .tpc-entry');
        entryIndex = $entries.length;
        $entries.addClass('collapsed');
    }

    function initSortable() {
        $('#tpc-entries-list').sortable({
            handle: '.tpc-entry-drag',
            placeholder: 'tpc-entry ui-sortable-placeholder',
            tolerance: 'pointer',
            update: function () {
                reindexEntries();
            }
        });
    }

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

    function initAddEntry() {
        $('#tpc-add-entry').on('click', function () {
            var template = $('#tpc-entry-template').html();
            template = template.replace(/\{\{INDEX\}\}/g, entryIndex);
            var $newEntry = $(template);
            $('#tpc-entries-list').append($newEntry);
            entryIndex++;

            $('html, body').animate({
                scrollTop: $newEntry.offset().top - 50
            }, 300);
        });
    }

    function initDelegatedEvents() {
        var $wrap = $('#tpc-comparator-wrap');

        // Toggle collapse
        $wrap.on('click', '.tpc-entry-toggle, .tpc-entry-title', function (e) {
            e.stopPropagation();
            $(this).closest('.tpc-entry').toggleClass('collapsed');
        });

        // Remove entry
        $wrap.on('click', '.tpc-entry-remove', function (e) {
            e.stopPropagation();
            if (confirm(tpcAdmin.confirmRemove)) {
                $(this).closest('.tpc-entry').slideUp(200, function () {
                    $(this).remove();
                    reindexEntries();
                });
            }
        });

        // Update title when store selection changes
        $wrap.on('change', '.tpc-store-select', function () {
            var $entry = $(this).closest('.tpc-entry');
            var selectedText = $(this).find('option:selected').text().trim();
            if (selectedText && !selectedText.match(/^—/)) {
                $entry.find('.tpc-entry-title').text(selectedText);
            } else {
                $entry.find('.tpc-entry-title').text('Nueva tienda');
            }
        });

        // Test scrape button
        $wrap.on('click', '.tpc-test-scrape', function () {
            var $entry = $(this).closest('.tpc-entry');
            var url = $entry.find('.tpc-affiliate-url').val().trim();
            var storeSlug = $entry.find('.tpc-store-select').val();
            var $result = $entry.find('.tpc-scrape-result');

            if (!url || !storeSlug) {
                $result.html('<span style="color:#dc3232;">Selecciona una tienda e introduce la URL primero.</span>');
                return;
            }

            $result.html('<span style="color:#666;">' + tpcAdmin.scraping + '</span>');

            $.post(tpcAdmin.ajaxUrl, {
                action: 'tpc_test_scrape',
                nonce: tpcAdmin.nonce,
                url: url,
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

        // Refresh all prices button
        $wrap.on('click', '#tpc-refresh-all-prices', function () {
            var $btn = $(this);
            var postId = $btn.data('post-id');

            $btn.prop('disabled', true).text('Refrescando...');

            $.post(tpcAdmin.ajaxUrl, {
                action: 'tpc_refresh_prices',
                nonce: tpcAdmin.nonce,
                post_id: postId
            }, function (response) {
                if (response.success) {
                    // Reload the page to show updated prices in the meta box.
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
    }

})(jQuery);
