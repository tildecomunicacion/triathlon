/**
 * Triathlon Price Comparator - Admin JavaScript
 */
(function ($) {
    'use strict';

    var entryIndex = 0;

    /**
     * Initialize when DOM is ready.
     */
    $(function () {
        initExistingEntries();
        initSortable();
        initAddEntry();
        initDelegatedEvents();
    });

    /**
     * Set the initial entry index based on existing entries.
     */
    function initExistingEntries() {
        var $entries = $('#tpc-entries-list .tpc-entry');
        entryIndex = $entries.length;

        // Start all existing entries collapsed
        $entries.addClass('collapsed');
    }

    /**
     * Make the entries list sortable via drag and drop.
     */
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

    /**
     * Reindex all entries after reordering.
     */
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

    /**
     * Add new entry button handler.
     */
    function initAddEntry() {
        $('#tpc-add-entry').on('click', function () {
            var template = $('#tpc-entry-template').html();
            template = template.replace(/\{\{INDEX\}\}/g, entryIndex);
            var $newEntry = $(template);
            $('#tpc-entries-list').append($newEntry);
            entryIndex++;

            // Scroll to the new entry
            $('html, body').animate({
                scrollTop: $newEntry.offset().top - 50
            }, 300);
        });
    }

    /**
     * Setup delegated event handlers.
     */
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
            if (selectedText && selectedText !== '— Seleccionar tienda —') {
                $entry.find('.tpc-entry-title').text(selectedText);
            } else {
                $entry.find('.tpc-entry-title').text('Nueva tienda');
            }
        });
    }

})(jQuery);
