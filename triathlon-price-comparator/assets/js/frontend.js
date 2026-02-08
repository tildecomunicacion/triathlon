/**
 * Triathlon Price Comparator - Frontend JavaScript
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initCouponCopy();
        initShowMore();
    });

    /**
     * Copy coupon code to clipboard on click.
     */
    function initCouponCopy() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.tpc-comparator__coupon-copy');
            if (!btn) return;

            var code = btn.getAttribute('data-code');
            if (!code) return;

            navigator.clipboard.writeText(code).then(function () {
                btn.classList.add('copied');
                var originalTitle = btn.getAttribute('title');
                btn.setAttribute('title', 'Copiado!');

                setTimeout(function () {
                    btn.classList.remove('copied');
                    btn.setAttribute('title', originalTitle || 'Copiar código');
                }, 2000);
            });
        });
    }

    /**
     * Show more entries on button click.
     */
    function initShowMore() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.tpc-comparator__more-btn');
            if (!btn) return;

            var comparator = btn.closest('.tpc-comparator');
            if (!comparator) return;

            var hiddenRows = comparator.querySelectorAll('.tpc-comparator__row--hidden');
            hiddenRows.forEach(function (row) {
                row.classList.remove('tpc-comparator__row--hidden');
            });

            btn.closest('.tpc-comparator__more-wrap').style.display = 'none';
        });
    }
})();
