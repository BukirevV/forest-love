/**
 * Лес Любви — карта (Leaflet + markercluster).
 * Режимы: public | picker | static | campaign | select.
 * Инициализация: ForestMap.init(elementId, config) — вызывается из MapWidget.
 */
(function (window, document) {
    'use strict';

    var L = window.L;

    function csrf() {
        var param = document.querySelector('meta[name="csrf-param"]');
        var token = document.querySelector('meta[name="csrf-token"]');
        return param && token ? { param: param.content, token: token.content } : null;
    }

    function post(url, data) {
        var body = new FormData();
        var c = csrf();
        if (c) { body.append(c.param, c.token); }
        Object.keys(data || {}).forEach(function (k) {
            var v = data[k];
            if (Array.isArray(v)) { v.forEach(function (x) { body.append(k + '[]', x); }); } else { body.append(k, v); }
        });
        return fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    }

    function debounce(fn, ms) {
        var t;
        return function () {
            var args = arguments, self = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(self, args); }, ms);
        };
    }

    function makeIcons(cfg) {
        return {
            tree: L.icon({ iconUrl: cfg.icons.tree, iconSize: [26, 34], iconAnchor: [13, 34], popupAnchor: [0, -30] }),
            named: L.icon({ iconUrl: cfg.icons.named, iconSize: [34, 44], iconAnchor: [17, 44], popupAnchor: [0, -40], className: 'marker-named' }),
            ad: L.icon({ iconUrl: cfg.icons.ad, iconSize: [32, 40], iconAnchor: [16, 40], popupAnchor: [0, -36] })
        };
    }

    function baseMap(el, cfg, options) {
        var map = L.map(el, Object.assign({ zoomControl: true, worldCopyJump: true }, options || {}))
            .setView(cfg.center, cfg.zoom);
        L.tileLayer(cfg.tileUrl, { attribution: cfg.tileAttribution, maxZoom: 19 }).addTo(map);
        return map;
    }

    function clusterGroup() {
        return L.markerClusterGroup({
            chunkedLoading: true,
            showCoverageOnHover: false,
            maxClusterRadius: 50,
            iconCreateFunction: function (cluster) {
                var n = cluster.getChildCount();
                var named = cluster.getAllChildMarkers().some(function (m) { return m.options.named; });
                var size = n < 10 ? 'small' : (n < 100 ? 'medium' : 'large');
                return L.divIcon({
                    html: '<div><span>' + n + '</span></div>',
                    className: 'marker-cluster marker-cluster-' + size + (named ? ' marker-cluster--named' : ''),
                    iconSize: L.point(40, 40)
                });
            }
        });
    }

    function url(template, id) {
        return template.replace('__ID__', encodeURIComponent(id));
    }

    // ------------------------------------------------------------------ public

    function initPublic(el, cfg) {
        var map = baseMap(el, cfg);
        var icons = makeIcons(cfg);
        var trees = clusterGroup().addTo(map);
        var ads = L.layerGroup().addTo(map);
        var panel = cfg.sidePanel ? document.querySelector(cfg.sidePanel) : null;
        var filters = cfg.filtersForm ? document.querySelector(cfg.filtersForm) : null;
        var statusEl = document.querySelector('[data-map-status]');
        var controller = null;
        var adsController = null;

        function bboxParam() {
            var b = map.getBounds();
            return [b.getSouth(), b.getWest(), b.getNorth(), b.getEast()].map(function (v) { return v.toFixed(5); }).join(',');
        }

        function filterParams() {
            var params = new URLSearchParams();
            if (filters) {
                var species = filters.querySelector('[name="species_id"]');
                var named = filters.querySelector('[name="named"]');
                if (species && species.value) { params.set('species_id', species.value); }
                if (named && named.checked) { params.set('named', '1'); }
            }
            return params;
        }

        function setStatus(text) {
            if (!statusEl) { return; }
            statusEl.textContent = text || '';
            statusEl.hidden = !text;
        }

        function loadTrees() {
            if (controller) { controller.abort(); }
            controller = new AbortController();
            var params = filterParams();
            params.set('bbox', bboxParam());
            params.set('zoom', map.getZoom());
            fetch(cfg.urls.points + (cfg.urls.points.indexOf('?') >= 0 ? '&' : '?') + params.toString(), { signal: controller.signal, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    trees.clearLayers();
                    var markers = (data.features || []).map(function (f) {
                        var c = f.geometry.coordinates;
                        var m = L.marker([c[1], c[0]], {
                            icon: f.properties.named ? icons.named : icons.tree,
                            named: !!f.properties.named,
                            zIndexOffset: f.properties.named ? 1000 : 0,
                            title: f.properties.named ? 'Именное дерево' : 'Дерево',
                            keyboard: true
                        });
                        m.on('click', function () { openTree(f.properties.id, m); });
                        return m;
                    });
                    trees.addLayers(markers);
                    setStatus(data.meta && data.meta.truncated ? 'Показаны не все деревья — приблизьте карту.' : '');
                })
                .catch(function (e) { if (e.name !== 'AbortError') { setStatus('Не удалось загрузить точки. Проверьте соединение.'); } });
        }

        function loadAds() {
            if (!cfg.urls.ads || cfg.preview) { return; }
            if (adsController) { adsController.abort(); }
            adsController = new AbortController();
            fetch(cfg.urls.ads + '?bbox=' + encodeURIComponent(bboxParam()), { signal: adsController.signal, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    ads.clearLayers();
                    (data.features || []).forEach(function (f) {
                        var c = f.geometry.coordinates;
                        var p = f.properties;
                        var icon = p.icon ? L.icon({ iconUrl: p.icon, iconSize: [36, 36], iconAnchor: [18, 36] }) : icons.ad;
                        var m = L.marker([c[1], c[0]], { icon: icon, zIndexOffset: 2000, title: p.advertiser + ' — реклама' }).addTo(ads);
                        m.bindTooltip(p.advertiser, { direction: 'top', offset: [0, -34] });
                        m.on('click', function () {
                            post(url(cfg.urls.adClick, p.id), {}).finally(function () {
                                if (p.external) { window.open(p.url, '_blank', 'noopener'); } else { window.location.href = p.url; }
                            });
                        });
                    });
                })
                .catch(function () { /* реклама не критична */ });
        }

        function openTree(id, marker) {
            fetch(url(cfg.urls.tree, id), { credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
                .then(function (data) {
                    if (panel) {
                        panel.querySelector('[data-panel-content]').innerHTML = data.popup;
                        panel.hidden = false;
                        panel.classList.add('is-open');
                    } else if (marker) {
                        marker.bindPopup(data.popup, { maxWidth: 280 }).openPopup();
                    }
                    if (window.history && window.history.replaceState) {
                        var u = new URL(window.location.href);
                        u.searchParams.set('tree', id);
                        window.history.replaceState(null, '', u.toString());
                    }
                    return data;
                })
                .catch(function () { setStatus('Карточка недоступна.'); });
        }

        if (panel) {
            var close = panel.querySelector('[data-panel-close]');
            if (close) {
                close.addEventListener('click', function () {
                    panel.classList.remove('is-open');
                    panel.hidden = true;
                    if (window.history && window.history.replaceState) {
                        var u = new URL(window.location.href);
                        u.searchParams.delete('tree');
                        window.history.replaceState(null, '', u.toString());
                    }
                });
            }
        }

        var reload = debounce(function () { loadTrees(); loadAds(); }, 300);
        map.on('moveend', reload);
        if (filters) { filters.addEventListener('change', function () { loadTrees(); }); }

        // Кнопка «Моё местоположение»
        locateControl(map, function (latlng) { map.setView(latlng, Math.max(map.getZoom(), 13)); });

        if (cfg.focusTreeId) {
            fetch(url(cfg.urls.tree, cfg.focusTreeId), { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) { if (d) { map.setView([d.latitude, d.longitude], 16); openTree(cfg.focusTreeId); } else { loadTrees(); loadAds(); } });
        } else {
            loadTrees(); loadAds();
        }
        return { map: map, reload: reload, openTree: openTree };
    }

    function locateControl(map, onFound, onError) {
        var Control = L.Control.extend({
            options: { position: 'topleft' },
            onAdd: function () {
                var btn = L.DomUtil.create('a', 'leaflet-bar leaflet-control map-locate');
                btn.href = '#';
                btn.title = 'Моё местоположение';
                btn.setAttribute('role', 'button');
                btn.innerHTML = '<i class="bi bi-crosshair"></i>';
                L.DomEvent.on(btn, 'click', function (e) {
                    L.DomEvent.preventDefault(e);
                    L.DomEvent.stopPropagation(e);
                    if (!navigator.geolocation) {
                        (onError || alertError)('Геолокация не поддерживается браузером. Отметьте точку на карте вручную.');
                        return;
                    }
                    btn.classList.add('is-loading');
                    navigator.geolocation.getCurrentPosition(function (pos) {
                        btn.classList.remove('is-loading');
                        onFound(L.latLng(pos.coords.latitude, pos.coords.longitude), pos.coords.accuracy);
                    }, function (err) {
                        btn.classList.remove('is-loading');
                        var msg = err.code === 1
                            ? 'Доступ к геолокации запрещён. Разрешите его в настройках браузера или отметьте точку на карте вручную.'
                            : 'Не удалось определить местоположение. Отметьте точку на карте вручную.';
                        (onError || alertError)(msg);
                    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 });
                });
                return btn;
            }
        });
        map.addControl(new Control());
    }

    function alertError(msg) {
        var box = document.createElement('div');
        box.className = 'alert alert-warning alert-dismissible fade show map-toast';
        box.setAttribute('role', 'alert');
        box.textContent = msg;
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close';
        close.setAttribute('data-bs-dismiss', 'alert');
        box.appendChild(close);
        document.body.appendChild(box);
        setTimeout(function () { box.remove(); }, 8000);
    }

    // ------------------------------------------------------------------ picker

    function initPicker(el, cfg) {
        var map = baseMap(el, cfg);
        var icons = makeIcons(cfg);
        var latInput = cfg.latInput ? document.getElementById(cfg.latInput) : null;
        var lngInput = cfg.lngInput ? document.getElementById(cfg.lngInput) : null;
        var sourceInput = cfg.sourceInput ? document.getElementById(cfg.sourceInput) : null;
        var marker = null;

        function setPoint(lat, lng, source, pan) {
            lat = Number(lat); lng = Number(lng);
            if (!isFinite(lat) || !isFinite(lng)) { return; }
            var ll = L.latLng(lat, lng);
            if (!marker) {
                marker = L.marker(ll, { draggable: true, icon: icons.tree, autoPan: true }).addTo(map);
                marker.on('dragend', function () {
                    var p = marker.getLatLng().wrap();
                    writeInputs(p.lat, p.lng, 'manual');
                });
            } else {
                marker.setLatLng(ll);
            }
            writeInputs(lat, lng, source);
            if (pan !== false) { map.setView(ll, Math.max(map.getZoom(), 16)); }
        }

        function writeInputs(lat, lng, source) {
            if (latInput) { latInput.value = Number(lat).toFixed(7); }
            if (lngInput) { lngInput.value = Number(lng).toFixed(7); }
            if (sourceInput && source) { sourceInput.value = source; }
            el.dispatchEvent(new CustomEvent('map:point', { bubbles: true, detail: { lat: lat, lng: lng, source: source } }));
            [latInput, lngInput].forEach(function (i) { if (i) { i.dispatchEvent(new Event('change', { bubbles: true })); } });
        }

        map.on('click', function (e) {
            var p = e.latlng.wrap();
            setPoint(p.lat, p.lng, 'manual', false);
        });

        function onInputChange(e) {
            if (!e.isTrusted) { return; } // изменения, внесённые самим скриптом
            var lat = parseFloat(String(latInput.value).replace(',', '.'));
            var lng = parseFloat(String(lngInput.value).replace(',', '.'));
            if (isFinite(lat) && isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) {
                setPoint(lat, lng, 'manual');
            }
        }
        if (latInput && lngInput) {
            latInput.addEventListener('change', onInputChange);
            lngInput.addEventListener('change', onInputChange);
        }

        locateControl(map, function (latlng, accuracy) {
            setPoint(latlng.lat, latlng.lng, 'device');
            if (accuracy) { L.circle(latlng, { radius: accuracy, weight: 1, fillOpacity: 0.08 }).addTo(map); }
        });

        if (cfg.marker) { setPoint(cfg.marker[0], cfg.marker[1], null, false); }

        // Пересчёт размеров, если карта была скрыта (вкладки, модальные окна)
        setTimeout(function () { map.invalidateSize(); }, 200);

        var api = { map: map, setPoint: setPoint, hasPoint: function () { return !!marker; } };
        el.forestMap = api;
        return api;
    }

    // ------------------------------------------------------------------ static

    function initStatic(el, cfg) {
        var map = baseMap(el, cfg, { scrollWheelZoom: false, dragging: !L.Browser.mobile, tap: false });
        var icons = makeIcons(cfg);
        if (cfg.marker) {
            L.marker(cfg.marker, { icon: cfg.markerNamed ? icons.named : icons.tree, interactive: false }).addTo(map);
        }
        return { map: map };
    }

    // ------------------------------------------------------------------ campaign

    function initCampaign(el, cfg) {
        var map = baseMap(el, cfg, { scrollWheelZoom: false });
        var icons = makeIcons(cfg);
        var group = clusterGroup().addTo(map);
        var markers = (cfg.points || []).map(function (p) {
            var m = L.marker([p.lat, p.lng], { icon: p.named ? icons.named : icons.tree, named: !!p.named });
            m.on('click', function () { window.location.href = url(cfg.urls.treePage, p.id); });
            return m;
        });
        group.addLayers(markers);
        if (markers.length) { map.fitBounds(group.getBounds(), { padding: [30, 30], maxZoom: 15 }); }
        return { map: map };
    }

    // ------------------------------------------------------------------ select (админка)

    function initSelect(el, cfg) {
        var map = baseMap(el, cfg, { boxZoom: false });
        var input = cfg.selectionInput ? document.getElementById(cfg.selectionInput) : null;
        var counter = cfg.counter ? document.querySelector(cfg.counter) : null;
        var selected = {};
        var layers = [];
        var styleOn = { radius: 7, color: '#b45309', fillColor: '#f59e0b', fillOpacity: 0.9, weight: 2 };
        var styleOff = { radius: 6, color: '#2f6b3a', fillColor: '#4ade80', fillOpacity: 0.6, weight: 1 };

        function sync() {
            var ids = Object.keys(selected).filter(function (k) { return selected[k]; });
            if (input) { input.value = ids.join(','); }
            if (counter) { counter.textContent = ids.length; }
        }

        (cfg.points || []).forEach(function (p) {
            selected[p.id] = !!p.selected;
            var m = L.circleMarker([p.lat, p.lng], p.selected ? styleOn : styleOff).addTo(map);
            m.pointId = p.id;
            m.bindTooltip('#' + p.id + (p.status !== 'approved' ? ' (' + p.status + ')' : ''));
            m.on('click', function () {
                selected[p.id] = !selected[p.id];
                m.setStyle(selected[p.id] ? styleOn : styleOff);
                sync();
            });
            layers.push(m);
        });

        // Выделение прямоугольником с Shift
        var start = null, rect = null;
        map.on('mousedown', function (e) {
            if (!e.originalEvent.shiftKey) { return; }
            map.dragging.disable();
            start = e.latlng;
            rect = L.rectangle([start, start], { color: '#b45309', weight: 1, dashArray: '4' }).addTo(map);
        });
        map.on('mousemove', function (e) { if (start && rect) { rect.setBounds([start, e.latlng]); } });
        map.on('mouseup', function () {
            if (!start || !rect) { return; }
            var bounds = rect.getBounds();
            layers.forEach(function (m) {
                if (bounds.contains(m.getLatLng())) {
                    selected[m.pointId] = true;
                    m.setStyle(styleOn);
                }
            });
            map.removeLayer(rect);
            rect = null; start = null;
            map.dragging.enable();
            sync();
        });

        if (layers.length) { map.fitBounds(L.featureGroup(layers).getBounds(), { padding: [20, 20] }); }
        sync();
        return { map: map };
    }

    // ------------------------------------------------------------------ API

    var ForestMap = {
        instances: {},

        init: function (id, cfg) {
            var el = document.getElementById(id);
            if (!el || !L) { return null; }
            var inst;
            switch (cfg.mode) {
                case 'picker': inst = initPicker(el, cfg); break;
                case 'static': inst = initStatic(el, cfg); break;
                case 'campaign': inst = initCampaign(el, cfg); break;
                case 'select': inst = initSelect(el, cfg); break;
                default: inst = initPublic(el, cfg);
            }
            ForestMap.instances[id] = inst;
            return inst;
        },

        get: function (id) { return ForestMap.instances[id] || null; },

        /** Учёт показов баннеров (пакетно) и кликов */
        registerImpressions: function (opts) {
            if (!opts || !opts.ids || !opts.ids.length) { return; }
            var key = 'adImpr:' + opts.ids.join(',');
            try {
                if (window.sessionStorage.getItem(key)) { return; } // один показ на сессию
                window.sessionStorage.setItem(key, '1');
            } catch (e) { /* ignore */ }
            post(opts.url, { ids: opts.ids });
        }
    };

    // Клики по баннерам и закрытие баннеров
    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-ad-click]');
        if (link) {
            post(link.getAttribute('data-ad-click'), {});
        }
        var close = e.target.closest('[data-banner-close]');
        if (close) {
            var banner = close.closest('.map-banner');
            if (banner) { banner.remove(); }
        }
    });

    window.ForestMap = ForestMap;
})(window, document);
