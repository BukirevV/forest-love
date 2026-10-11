/**
 * Лес Любви — общие скрипты.
 */
(function (document) {
    'use strict';

    // Копирование ссылки
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-copy]');
        if (!btn) { return; }
        var text = btn.getAttribute('data-copy');
        var done = function () {
            var old = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check2"></i> Скопировано';
            setTimeout(function () { btn.innerHTML = old; }, 2000);
        };
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            var ta = document.createElement('textarea');
            ta.value = text; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); done(); } catch (err) { /* ignore */ }
            ta.remove();
        }
    });

    // Обратный отсчёт до повторной отправки кода
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-resend-in]').forEach(function (btn) {
            var left = parseInt(btn.getAttribute('data-resend-in'), 10) || 0;
            var label = btn.textContent;
            if (left <= 0) { return; }
            btn.disabled = true;
            var timer = setInterval(function () {
                left--;
                btn.textContent = label + ' (' + left + ' с)';
                if (left <= 0) {
                    clearInterval(timer);
                    btn.disabled = false;
                    btn.textContent = label;
                }
            }, 1000);
        });
    });
})(document);
