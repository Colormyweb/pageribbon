/**
 * PageRibbon — Settings page interactions
 *
 * Met à jour la preview de couleur quand l'utilisateur change le select.
 * Vanilla JS, pas de dépendance jQuery même si on est dans l'admin WP.
 */
(function () {
    'use strict';

    function updatePreview(select) {
        var target = select.getAttribute('data-target');
        if (!target) {
            return;
        }
        var preview = document.querySelector('.pageribbon-preview[data-preview="' + target + '"]');
        if (!preview) {
            return;
        }

        var selectedOption = select.options[select.selectedIndex];
        var index = parseInt(select.value, 10);

        if (index < 0 || !selectedOption) {
            // "Aucune couleur" : preview vide avec bordure pointillée
            preview.style.background = '#fff';
            preview.style.borderColor = '#d1d5db';
            preview.style.borderStyle = 'dashed';
            return;
        }

        var bg = selectedOption.getAttribute('data-bg');
        var border = selectedOption.getAttribute('data-border');
        if (bg) {
            preview.style.background = bg;
        }
        if (border) {
            preview.style.borderColor = border;
        }
        preview.style.borderStyle = 'solid';
    }

    function init() {
        var selects = document.querySelectorAll('.pageribbon-color-select');
        for (var i = 0; i < selects.length; i++) {
            selects[i].addEventListener('change', function (e) {
                updatePreview(e.target);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
