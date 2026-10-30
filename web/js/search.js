/**
 * Поиск деревьев в шапке сайта.
 * - при наборе — подсказки (GET /api/search/suggest?q=…);
 * - выбор подсказки на странице с картой — карта перемещается к дереву и открывает его карточку без перезагрузки;
 *   на остальных страницах — переход на карту с этим деревом;
 * - Enter без выбранной подсказки — страница результатов /search?q=….
 */
(function (window, document) {
    'use strict';

    var MIN_LENGTH = 2;

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function highlight(text, q) {
        var safe = escapeHtml(text);
        if (!q) { return safe; }
        var norm = function (s) { return s.toLowerCase().replace(/ё/g, 'е'); };
        var i = norm(text).indexOf(norm(q));
        if (i < 0) { return safe; }
        return escapeHtml(text.slice(0, i)) + '<mark>' + escapeHtml(text.slice(i, i + q.length)) + '</mark>' + escapeHtml(text.slice(i + q.length));
    }

    /** Публичная карта на текущей странице (если есть). */
    function publicMap() {
        var all = (window.ForestMap && window.ForestMap.instances) || {};
        for (var id in all) {
            if (Object.prototype.hasOwnProperty.call(all, id) && all[id] && typeof all[id].focusTree === 'function') {
                return all[id];
            }
        }
        return null;
    }

    function init(form) {
        var input = form.querySelector('input[name="q"]');
        var list = form.querySelector('.site-search__list');
        if (!input || !list) { return; }

        var suggestUrl = form.getAttribute('data-suggest-url');
        var homeUrl = form.getAttribute('data-home-url') || '/';
        var items = [];
        var active = -1;
        var timer = null;
        var controller = null;
        var lastQuery = null;

        function open() {
            list.classList.add('show');
            input.setAttribute('aria-expanded', 'true');
        }

        function close() {
            list.classList.remove('show');
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            active = -1;
        }

        function render(q) {
            if (!items.length) {
                list.innerHTML = '<li><span class="dropdown-item-text small text-muted">Ничего не найдено. Нажмите Enter, чтобы открыть поиск.</span></li>';
                open();
                return;
            }
            list.innerHTML = items.map(function (it, i) {
                return '<li><a href="' + escapeHtml(it.url) + '" class="dropdown-item site-search__item" role="option" id="site-search-opt-' + i + '" data-index="' + i + '">'
                    + '<span class="site-search__title">' + highlight(it.title, q) + '</span>'
                    + (it.named ? ' <span class="badge text-bg-warning">Именное</span>' : '')
                    + (it.species ? '<span class="site-search__species">' + escapeHtml(it.species) + '</span>' : '')
                    + '</a></li>';
            }).join('') + '<li><hr class="dropdown-divider"></li>'
                + '<li><a class="dropdown-item small site-search__all" href="' + escapeHtml(form.action + (form.action.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q)) + '">Все результаты для «' + escapeHtml(q) + '»</a></li>';
            open();
        }

        function setActive(i) {
            var options = list.querySelectorAll('.site-search__item');
            if (!options.length) { return; }
            active = (i + options.length) % options.length;
            options.forEach(function (el, n) { el.classList.toggle('active', n === active); });
            input.setAttribute('aria-activedescendant', options[active].id);
            options[active].scrollIntoView({ block: 'nearest' });
        }

        function fetchSuggestions() {
            var q = input.value.trim();
            if (q.length < MIN_LENGTH) { items = []; lastQuery = null; close(); return; }
            if (q === lastQuery) { if (items.length || list.innerHTML) { open(); } return; }
            if (controller) { controller.abort(); }
            controller = new AbortController();
            fetch(suggestUrl + (suggestUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                signal: controller.signal
            })
                .then(function (r) { return r.ok ? r.json() : { items: [] }; })
                .then(function (data) {
                    if (input.value.trim() !== q) { return; }
                    lastQuery = q;
                    items = data.items || [];
                    active = -1;
                    render(q);
                })
                .catch(function (e) { if (e.name !== 'AbortError') { close(); } });
        }

        function collapseNavbar() {
            var collapse = form.closest('.navbar-collapse.show');
            if (collapse && window.bootstrap && window.bootstrap.Collapse) {
                window.bootstrap.Collapse.getOrCreateInstance(collapse).hide();
            }
        }

        function choose(it) {
            close();
            input.value = it.title;
            lastQuery = it.title;
            var map = publicMap();
            if (map) {
                collapseNavbar();
                input.blur();
                map.focusTree(it.id, it.latitude, it.longitude);
            } else {
                window.location.href = homeUrl + (homeUrl.indexOf('?') >= 0 ? '&' : '?') + 'tree=' + it.id;
            }
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            // Подсказки от прежнего текста убираем сразу, чтобы не выбрать устаревшую
            if (input.value.trim() !== lastQuery) { items = []; close(); }
            timer = setTimeout(fetchSuggestions, 250);
        });
        input.addEventListener('focus', function () {
            if (input.value.trim().length >= MIN_LENGTH) { fetchSuggestions(); }
        });
        input.addEventListener('keydown', function (e) {
            var shown = list.classList.contains('show');
            if (e.key === 'ArrowDown') { e.preventDefault(); if (!shown) { fetchSuggestions(); } else { setActive(active + 1); } }
            else if (e.key === 'ArrowUp') { if (shown) { e.preventDefault(); setActive(active - 1); } }
            else if (e.key === 'Escape') { close(); }
            else if (e.key === 'Enter' && shown && active >= 0 && items[active]) { e.preventDefault(); choose(items[active]); }
        });
        list.addEventListener('mousedown', function (e) { e.preventDefault(); }); // не терять фокус до клика
        list.addEventListener('click', function (e) {
            var a = e.target.closest('.site-search__item');
            if (!a) { return; }
            var it = items[+a.getAttribute('data-index')];
            if (!it) { return; }
            // Ctrl/Cmd/средняя кнопка — открыть страницу дерева в новой вкладке как обычную ссылку
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) { return; }
            e.preventDefault();
            choose(it);
        });
        input.addEventListener('blur', function () { setTimeout(close, 150); });
        form.addEventListener('submit', function (e) {
            if (input.value.trim().length === 0) { e.preventDefault(); input.focus(); return; }
            close();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-site-search]').forEach(init);
    });
})(window, document);
