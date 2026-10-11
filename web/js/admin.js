/**
 * Лес Любви — админка.
 */
(function (document) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Лайтбокс
        if (window.GLightbox) { window.GLightbox({ selector: '.glightbox' }); }

        // «Выбрать все»
        document.querySelectorAll('[data-select-all]').forEach(function (master) {
            master.addEventListener('change', function () {
                var name = master.getAttribute('data-select-all');
                document.querySelectorAll('input[type="checkbox"][name="' + name + '"]').forEach(function (cb) {
                    cb.checked = master.checked;
                });
            });
        });

        // Тип размещения: точка / баннер
        var typeSelect = document.querySelector('[data-placement-type]');
        if (typeSelect) {
            var toggle = function () {
                document.querySelectorAll('[data-placement-section]').forEach(function (s) {
                    s.classList.toggle('d-none', s.getAttribute('data-placement-section') !== typeSelect.value);
                });
                window.dispatchEvent(new Event('resize')); // для пересчёта карты
            };
            typeSelect.addEventListener('change', toggle);
        }

        // Сортировка пород перетаскиванием
        var table = document.querySelector('table[data-sortable]');
        if (table) {
            var tbody = table.querySelector('tbody');
            var dragged = null;
            tbody.querySelectorAll('tr').forEach(function (tr) {
                tr.draggable = true;
                tr.addEventListener('dragstart', function () { dragged = tr; tr.classList.add('opacity-50'); });
                tr.addEventListener('dragend', function () {
                    tr.classList.remove('opacity-50');
                    var ids = Array.prototype.map.call(tbody.querySelectorAll('tr[data-id]'), function (r) { return r.getAttribute('data-id'); });
                    var body = new FormData();
                    var p = document.querySelector('meta[name="csrf-param"]');
                    var t = document.querySelector('meta[name="csrf-token"]');
                    if (p && t) { body.append(p.content, t.content); }
                    ids.forEach(function (id) { body.append('order[]', id); });
                    fetch(table.getAttribute('data-sortable'), { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                });
                tr.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    if (!dragged || dragged === tr) { return; }
                    var rect = tr.getBoundingClientRect();
                    var after = (e.clientY - rect.top) > rect.height / 2;
                    tbody.insertBefore(dragged, after ? tr.nextSibling : tr);
                });
            });
        }
    });
})(document);
