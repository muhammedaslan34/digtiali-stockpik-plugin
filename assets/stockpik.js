(function () {
    function getLabels() {
        if (window.digtialiStockpik && typeof window.digtialiStockpik === 'object') {
            return {
                copy: window.digtialiStockpik.copy || 'Copy',
                copied: window.digtialiStockpik.copied || 'Copied!',
            };
        }

        return {
            copy: 'Copy',
            copied: 'Copied!',
        };
    }

    function enhanceStockpikSection() {
        var section = document.querySelector('[data-stockpik-access="true"]');
        if (!section) {
            return;
        }

        var cards = section.querySelectorAll('.stockpik-card');
        cards.forEach(function (card, index) {
            card.style.animationDelay = (index * 90) + 'ms';
        });
    }

    /* Password show / hide */
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('.stockpik-toggle-password');
        if (!btn) {
            return;
        }

        var targetId = btn.getAttribute('data-target');
        if (!targetId) {
            return;
        }

        var display = document.getElementById(targetId);
        if (!display) {
            return;
        }

        var isShowing = btn.getAttribute('aria-pressed') === 'true';
        var textSpan  = btn.querySelector('span');

        if (isShowing) {
            /* Hide */
            display.textContent = '••••••••••••';
            btn.setAttribute('aria-pressed', 'false');
            if (textSpan) {
                textSpan.textContent = btn.getAttribute('data-show') || 'Show';
            }
        } else {
            /* Reveal */
            var password = display.getAttribute('data-password') || '';
            display.textContent = password;
            btn.setAttribute('aria-pressed', 'true');
            if (textSpan) {
                textSpan.textContent = btn.getAttribute('data-hide') || 'Hide';
            }
        }
    });

    document.addEventListener(
        'click',
        function (event) {
            var button = event.target.closest('.stockpik-copy');
            if (!button) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            var value = button.getAttribute('data-copy') || '';
            if (!value) {
                return;
            }

            var labels = getLabels();
            var textSpan = button.querySelector('span');
            var originalText = textSpan ? textSpan.textContent : labels.copy;
            var checkIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
            var copyIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';

            button.classList.add('copied');
            if (textSpan) {
                textSpan.textContent = labels.copied;
            }
            var svgEl = button.querySelector('svg');
            if (svgEl) {
                svgEl.outerHTML = checkIcon;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).catch(function () {});
            } else {
                var textarea = document.createElement('textarea');
                textarea.value = value;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                } catch (error) {}
                document.body.removeChild(textarea);
            }

            window.setTimeout(function () {
                button.classList.remove('copied');
                if (textSpan) {
                    textSpan.textContent = originalText;
                }
                var currentSvg = button.querySelector('svg');
                if (currentSvg) {
                    currentSvg.outerHTML = copyIcon;
                }
            }, 1500);
        },
        true
    );

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enhanceStockpikSection);
    } else {
        enhanceStockpikSection();
    }
})();
