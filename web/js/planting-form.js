/**
 * Лес Любви — форма посадки:
 * камера/загрузка фото, предпросмотр, EXIF (exifr, резерв — сервер), предложение координат из фото,
 * синхронизация маркера и полей, поиск породы, динамический список ссылок, блокировка отправки без координат и породы.
 */
(function (window, document) {
    'use strict';

    function csrf() {
        var p = document.querySelector('meta[name="csrf-param"]');
        var t = document.querySelector('meta[name="csrf-token"]');
        return p && t ? { param: p.content, token: t.content } : null;
    }

    function toDateString(d) {
        if (!(d instanceof Date) || isNaN(d.getTime())) { return null; }
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }

    function formatSize(bytes) {
        return bytes > 1048576 ? (bytes / 1048576).toFixed(1) + ' МБ' : Math.round(bytes / 1024) + ' КБ';
    }

    // ---------------------------------------------------------------- EXIF

    function readExif(file, fallbackUrl) {
        var exifr = window.exifr;
        if (exifr) {
            return Promise.all([
                exifr.gps(file).catch(function () { return null; }),
                exifr.parse(file, { pick: ['DateTimeOriginal', 'CreateDate', 'ModifyDate'], reviveValues: true }).catch(function () { return null; })
            ]).then(function (res) {
                var gps = res[0] || {};
                var tags = res[1] || {};
                var date = tags.DateTimeOriginal || tags.CreateDate || tags.ModifyDate || null;
                return {
                    latitude: isFinite(gps.latitude) ? gps.latitude : null,
                    longitude: isFinite(gps.longitude) ? gps.longitude : null,
                    date: toDateString(date instanceof Date ? date : (date ? new Date(date) : null))
                };
            }).then(function (r) {
                // Если клиент не извлёк дату или координаты (HEIC, особенности сборки exifr) — дополняем с сервера
                if ((r.latitude === null || r.date === null) && fallbackUrl) {
                    return serverExif(file, fallbackUrl).then(function (s) {
                        return {
                            latitude: r.latitude !== null ? r.latitude : s.latitude,
                            longitude: r.longitude !== null ? r.longitude : s.longitude,
                            date: r.date || s.date
                        };
                    });
                }
                return r;
            });
        }
        return fallbackUrl ? serverExif(file, fallbackUrl) : Promise.resolve({ latitude: null, longitude: null, date: null });
    }

    function serverExif(file, endpoint) {
        var body = new FormData();
        var c = csrf();
        if (c) { body.append(c.param, c.token); }
        body.append('file', file);
        return fetch(endpoint, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : {}; })
            .then(function (d) { return { latitude: d.latitude ?? null, longitude: d.longitude ?? null, date: d.date ?? null }; })
            .catch(function () { return { latitude: null, longitude: null, date: null }; });
    }

    // ---------------------------------------------------------------- Фото

    function initPhotoCapture(root) {
        var upload = root.querySelector('[data-photo-input="upload"]');
        var camera = root.querySelector('[data-photo-input="camera"]');
        var preview = root.querySelector('[data-photo-preview]');
        var info = root.querySelector('[data-photo-info]');
        var takenAt = root.dataset.takenAt ? document.getElementById(root.dataset.takenAt) : null;
        var source = root.dataset.source ? document.getElementById(root.dataset.source) : null;
        var maxSize = parseInt(root.dataset.maxSize || '0', 10);

        root.querySelector('[data-photo-camera]').addEventListener('click', function () { camera.click(); });
        root.querySelector('[data-photo-upload]').addEventListener('click', function () { upload.click(); });

        function handle(file, src) {
            if (!file) { return; }
            if (maxSize && file.size > maxSize) {
                info.innerHTML = '<span class="text-danger">Файл слишком большой (' + formatSize(file.size) + '). Максимум — ' + formatSize(maxSize) + '.</span>';
                upload.value = '';
                return;
            }
            if (source) { source.value = src; }
            var isHeic = /heic|heif/i.test(file.type) || /\.heic$/i.test(file.name);
            if (!isHeic && preview) {
                preview.src = URL.createObjectURL(file);
                preview.classList.remove('d-none');
            } else if (preview) {
                preview.classList.add('d-none');
            }
            info.textContent = file.name + ' · ' + formatSize(file.size) + ' · читаем данные фото…';

            readExif(file, root.dataset.exifUrl).then(function (exif) {
                var parts = [file.name + ' · ' + formatSize(file.size)];
                if (exif.date) {
                    parts.push('снято ' + exif.date.split('-').reverse().join('.'));
                    if (takenAt && !takenAt.value) { takenAt.value = exif.date; }
                } else {
                    parts.push('дата съёмки не найдена — укажите её');
                }
                parts.push(exif.latitude !== null ? 'есть координаты' : 'координат в фото нет');
                info.textContent = parts.join(' · ');
                root.dispatchEvent(new CustomEvent('photo:exif', { bubbles: true, detail: exif }));
            });
        }

        upload.addEventListener('change', function () { handle(upload.files[0], root.dataset.sourceUpload); });
        camera.addEventListener('change', function () {
            var file = camera.files[0];
            if (!file) { return; }
            // Переносим снимок с камеры в отправляемое поле
            try {
                var dt = new DataTransfer();
                dt.items.add(file);
                upload.files = dt.files;
            } catch (e) {
                // Старые браузеры: отправляем через поле камеры
                camera.name = upload.name;
                upload.name = '';
            }
            handle(file, root.dataset.sourceCamera);
        });
    }

    // ---------------------------------------------------------------- Форма посадки

    function initPlantingForm(form) {
        var mapEl = form.querySelector('.forest-map--picker');
        var offer = form.querySelector('[data-exif-offer]');
        var lat = form.querySelector('[data-coord="lat"]');
        var lng = form.querySelector('[data-coord="lng"]');
        var species = form.querySelector('[data-species-select]');
        var plantedAt = form.querySelector('input[name="PlantingForm[planted_at]"]');
        var pendingExif = null;

        function mapApi() { return mapEl && mapEl.forestMap; }

        form.addEventListener('photo:exif', function (e) {
            var exif = e.detail || {};
            if (exif.date && plantedAt && !plantedAt.value) { plantedAt.value = exif.date; }
            if (exif.latitude === null || exif.longitude === null) { return; }
            pendingExif = exif;
            var api = mapApi();
            if (api && !api.hasPoint()) {
                // Точки ещё нет — подставляем сразу, пользователь может уточнить
                api.setPoint(exif.latitude, exif.longitude, 'exif');
                if (offer) {
                    offer.classList.add('d-none');
                }
                showNote('Координаты взяты из фото. Уточните точку на карте, если нужно.');
            } else if (offer) {
                offer.classList.remove('d-none');
                offer.classList.add('d-flex');
            }
        });

        function showNote(text) {
            var note = form.querySelector('[data-exif-note]');
            if (!note && mapEl) {
                note = document.createElement('div');
                note.className = 'small text-success mt-1';
                note.setAttribute('data-exif-note', '');
                mapEl.insertAdjacentElement('afterend', note);
            }
            if (note) { note.textContent = text; }
        }

        if (offer) {
            offer.querySelector('[data-exif-accept]').addEventListener('click', function () {
                var api = mapApi();
                if (api && pendingExif) { api.setPoint(pendingExif.latitude, pendingExif.longitude, 'exif'); }
                offer.classList.add('d-none'); offer.classList.remove('d-flex');
            });
            offer.querySelector('[data-exif-decline]').addEventListener('click', function () {
                offer.classList.add('d-none'); offer.classList.remove('d-flex');
            });
        }

        // Поиск породы
        var search = form.querySelector('[data-species-search]');
        if (search && species) {
            var all = Array.prototype.map.call(species.options, function (o) { return { value: o.value, text: o.text }; });
            search.addEventListener('input', function () {
                var q = search.value.trim().toLowerCase();
                var current = species.value;
                species.innerHTML = '';
                all.forEach(function (o) {
                    if (o.value === '' || !q || o.text.toLowerCase().indexOf(q) >= 0) {
                        var opt = new Option(o.text, o.value, false, o.value === current);
                        species.add(opt);
                    }
                });
                var matches = species.options.length - 1;
                if (matches === 1) { species.selectedIndex = 1; species.dispatchEvent(new Event('change', { bubbles: true })); }
            });
        }

        // Блокировка отправки без координат и породы (дублируется серверной валидацией)
        var buttons = form.querySelectorAll('[data-requires-valid]');
        var hint = form.querySelector('[data-valid-hint]');
        function validate() {
            var ok = lat && lng && lat.value.trim() !== '' && lng.value.trim() !== '' && species && species.value !== '';
            buttons.forEach(function (b) { b.disabled = !ok; });
            if (hint) { hint.classList.toggle('d-none', !!ok); }
        }
        [lat, lng, species].forEach(function (el) {
            if (el) { el.addEventListener('change', validate); el.addEventListener('input', validate); }
        });
        form.addEventListener('map:point', validate);
        validate();

        // Защита от двойной отправки
        form.addEventListener('submit', function (e) {
            if (form.dataset.submitting) { e.preventDefault(); return; }
            form.dataset.submitting = '1';
            setTimeout(function () { delete form.dataset.submitting; }, 15000);
        });
    }

    // ---------------------------------------------------------------- Список ссылок

    function initLinksInput(root) {
        var rows = root.querySelector('[data-links-rows]');
        var template = root.querySelector('[data-links-template]');
        var max = parseInt(root.dataset.max || '50', 10);

        function reindex() {
            rows.querySelectorAll('[data-links-row]').forEach(function (row, i) {
                row.querySelectorAll('input').forEach(function (input) {
                    input.name = input.name.replace(/\[(\d+|__INDEX__)\]\[(url|label)\]$/, '[' + i + '][$2]');
                });
            });
            var addBtn = root.querySelector('[data-links-add]');
            if (addBtn) { addBtn.disabled = rows.children.length >= max; }
        }

        root.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn || !root.contains(btn)) { return; }
            var row = btn.closest('[data-links-row]');
            if (btn.hasAttribute('data-links-add')) {
                if (rows.children.length >= max) { return; }
                var html = template.innerHTML.replace(/__INDEX__/g, String(rows.children.length));
                rows.insertAdjacentHTML('beforeend', html);
                var last = rows.lastElementChild.querySelector('input');
                if (last) { last.focus(); }
            } else if (btn.hasAttribute('data-links-remove') && row) {
                if (rows.children.length > 1) { row.remove(); } else { row.querySelectorAll('input').forEach(function (i) { i.value = ''; }); }
            } else if (btn.hasAttribute('data-links-up') && row && row.previousElementSibling) {
                rows.insertBefore(row, row.previousElementSibling);
            } else if (btn.hasAttribute('data-links-down') && row && row.nextElementSibling) {
                rows.insertBefore(row.nextElementSibling, row);
            } else {
                return;
            }
            e.preventDefault();
            reindex();
        });
        reindex();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-photo-capture]').forEach(initPhotoCapture);
        document.querySelectorAll('[data-planting-form]').forEach(initPlantingForm);
        document.querySelectorAll('[data-links-input]').forEach(initLinksInput);
    });
})(window, document);
