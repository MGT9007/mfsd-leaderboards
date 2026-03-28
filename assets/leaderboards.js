/* ================================================================
   MFSD Leaderboards — JS
   Minimal — future hooks for refresh, filtering, etc.
   ================================================================ */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('mfsd-lbs-root');
        if (!root) return;

        /* Stagger card entrance animation */
        var cards = root.querySelectorAll('.mfsd-lbs-card');
        cards.forEach(function (card, i) {
            card.style.opacity = '0';
            card.style.transform = 'translateY(12px)';
            card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            setTimeout(function () {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 80 * i);
        });
    });
})();
