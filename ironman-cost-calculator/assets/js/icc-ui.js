/**
 * Calculadora de coste real IRONMAN — interfaz.
 *
 * Monta el formulario a partir de ICC_DATA (no hay markup duplicado en PHP),
 * recalcula en cada cambio y deja el resumen listo para enviar por WhatsApp.
 *
 * Uso: <div class="icc" data-whatsapp="34600000000"></div>
 */
(function (root, doc) {
    'use strict';

    var DATA, ENGINE;

    var eur = function (n) {
        // useGrouping 'always': en es-ES el separador de miles se omite por
        // defecto en cifras de cuatro dígitos y queda incoherente (5542 € junto
        // a 12.501 €).
        return new Intl.NumberFormat('es-ES', {
            style: 'currency', currency: 'EUR', maximumFractionDigits: 0, useGrouping: 'always'
        }).format(Math.round(n));
    };
    var num = function (n, dec) {
        return new Intl.NumberFormat('es-ES', { maximumFractionDigits: dec === undefined ? 0 : dec }).format(n);
    };
    var esc = function (s) {
        return String(s === undefined || s === null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };

    var GRUPOS = {
        material: 'Material',
        consumibles: 'Consumibles',
        entreno: 'Entrenamiento',
        evento: 'La prueba',
        viaje: 'Viaje y alojamiento'
    };

    /* --------------------------------------------------------- estado */

    function estadoInicial() {
        var vol = DATA.volumen.full;
        var material = {};
        DATA.equipo.forEach(function (item) {
            material[item.id] = {
                opcion: (item.defecto && item.defecto.opcion) || item.opciones[0].id,
                situacion: (item.defecto && item.defecto.situacion) || 'no_uso'
            };
        });
        return {
            perfil: '',
            prueba: { distancia: 'full', inscripcion: null, gestion_pct: DATA.evento.inscripcion.gestion_pct, semanas: vol.semanas, horas_carrera: 12 },
            volumen: { horas_semana: vol.horas_semana, km_bici: vol.km_bici, km_run: vol.km_run, km_swim: vol.km_swim, horas_largas_semana: vol.horas_largas_semana, peso: 75 },
            material: material,
            consumibles: { nutricion_tipo: 'mixto', zapatillas_carrera: false },
            recurrentes: { entrenador: 'si', fisio_sesiones: DATA.recurrentes.fisio.sesiones_anio, licencia: 'anual', natacion_modo: 'abono', sesiones_natacion_semana: 2, club: false, suscripciones: false, seguro_bici: false },
            viaje: { modo: 'coche', km_coche: 300, peajes: 0, billete: null, tasa_bici: null, noches: DATA.evento.viaje.noches_defecto, precio_noche: null, personas_hab: 2, dias: 4, acompanantes: 1, extras: true },
            margen_pct: DATA.margen_imprevistos_pct
        };
    }

    /* --------------------------------------------------------- plantillas */

    function campoNum(label, path, opts) {
        opts = opts || {};
        return '<label class="icc-f">' +
            '<span class="icc-f__label">' + esc(label) +
            (opts.ayuda ? '<span class="icc-f__help" title="' + esc(opts.ayuda) + '">?</span>' : '') + '</span>' +
            '<span class="icc-f__input">' +
            '<input type="number" data-path="' + path + '" min="' + (opts.min !== undefined ? opts.min : 0) + '"' +
            (opts.max !== undefined ? ' max="' + opts.max + '"' : '') +
            (opts.step ? ' step="' + opts.step + '"' : '') +
            (opts.placeholder ? ' placeholder="' + esc(opts.placeholder) + '"' : '') + '>' +
            (opts.sufijo ? '<span class="icc-f__suffix">' + esc(opts.sufijo) + '</span>' : '') +
            '</span>' +
            (opts.nota ? '<span class="icc-f__note">' + esc(opts.nota) + '</span>' : '') +
            '</label>';
    }

    function campoSelect(label, path, opciones, opts) {
        opts = opts || {};
        return '<label class="icc-f">' +
            '<span class="icc-f__label">' + esc(label) + '</span>' +
            '<span class="icc-f__input"><select data-path="' + path + '">' +
            opciones.map(function (o) {
                return '<option value="' + esc(o.id) + '">' + esc(o.label) + '</option>';
            }).join('') +
            '</select></span>' +
            (opts.nota ? '<span class="icc-f__note">' + esc(opts.nota) + '</span>' : '') +
            '</label>';
    }

    function campoCheck(label, path) {
        return '<label class="icc-check"><input type="checkbox" data-path="' + path + '"><span>' + esc(label) + '</span></label>';
    }

    function filaMaterial(item) {
        var rango = item.opciones.map(function (o) { return o.precio[0]; }).reduce(function (a, b) { return Math.min(a, b); });
        var rangoMax = item.opciones.map(function (o) { return o.precio[1]; }).reduce(function (a, b) { return Math.max(a, b); });
        var situaciones = DATA.situaciones.filter(function (s) {
            return s.id !== 'alquilo' || item.alquiler;
        });
        return '<div class="icc-mat" data-item="' + esc(item.id) + '">' +
            '<div class="icc-mat__head">' +
                '<span class="icc-mat__name">' + esc(item.label) + '</span>' +
                '<span class="icc-mat__range">' + eur(rango) + ' – ' + eur(rangoMax) + '</span>' +
            '</div>' +
            (item.nota ? '<p class="icc-mat__note">' + esc(item.nota) + '</p>' : '') +
            '<div class="icc-mat__controls">' +
                '<select data-path="material.' + item.id + '.opcion">' +
                    item.opciones.map(function (o) {
                        return '<option value="' + esc(o.id) + '">' + esc(o.label) + ' · ' +
                            num(o.precio[0]) + '–' + num(o.precio[1]) + ' €</option>';
                    }).join('') +
                '</select>' +
                '<div class="icc-seg" data-seg="material.' + item.id + '.situacion">' +
                    situaciones.map(function (s) {
                        return '<button type="button" data-value="' + esc(s.id) + '">' + esc(s.label) + '</button>';
                    }).join('') +
                '</div>' +
            '</div>' +
            '<div class="icc-mat__out"></div>' +
        '</div>';
    }

    function render(host) {
        var tel = host.getAttribute('data-whatsapp') || '';
        host.innerHTML =
        '<div class="icc-wrap">' +
            '<form class="icc-form" novalidate>' +

            /* 1. la prueba */
            '<section class="icc-card">' +
                '<h3 class="icc-card__title"><span>1</span> Tu prueba</h3>' +
                '<div class="icc-grid">' +
                    campoSelect('Distancia', 'prueba.distancia', [
                        { id: 'full', label: 'IRONMAN completo (3,8 / 180 / 42,195)' },
                        { id: 'medio', label: 'Medio IRONMAN (1,9 / 90 / 21,1)' }
                    ]) +
                    campoNum('Inscripción', 'prueba.inscripcion', { sufijo: '€', placeholder: 'No lo sé aún', nota: 'Si lo dejas vacío usamos la horquilla publicada para la distancia.' }) +
                    campoNum('Gastos de gestión', 'prueba.gestion_pct', { sufijo: '%', step: 0.25, nota: 'Se cobran aparte del dorsal.' }) +
                    campoNum('Semanas de preparación', 'prueba.semanas', { min: 4, max: 60 }) +
                    campoNum('Horas que esperas tardar', 'prueba.horas_carrera', { min: 3, max: 17, sufijo: 'h', nota: 'Marca la nutrición del día de carrera.' }) +
                '</div>' +
            '</section>' +

            /* 2. material */
            '<section class="icc-card">' +
                '<h3 class="icc-card__title"><span>2</span> Tu material</h3>' +
                '<p class="icc-card__intro">Lo que ya tienes no sale de tu cuenta este año, pero se gasta: cuenta su parte de la temporada. Lo que compras lo repartimos entre las temporadas que va a durar, descontando lo que recuperarías vendiéndolo.</p>' +
                '<div class="icc-perfiles">' +
                    Object.keys(DATA.perfiles).map(function (k) {
                        var p = DATA.perfiles[k];
                        return '<button type="button" class="icc-perfil" data-perfil="' + esc(k) + '">' +
                            '<strong>' + esc(p.label) + '</strong><span>' + esc(p.descripcion) + '</span></button>';
                    }).join('') +
                '</div>' +
                '<div class="icc-mats">' + DATA.equipo.map(filaMaterial).join('') + '</div>' +
            '</section>' +

            /* 3. temporada */
            '<section class="icc-card">' +
                '<h3 class="icc-card__title"><span>3</span> Tu temporada</h3>' +
                '<div class="icc-grid">' +
                    campoNum('Horas de entreno a la semana', 'volumen.horas_semana', { min: 3, max: 30, step: 0.5 }) +
                    campoNum('Horas de sesión larga a la semana', 'volumen.horas_largas_semana', { min: 0, max: 20, step: 0.5, nota: 'Las que llevan geles y bebida.' }) +
                    campoNum('Km de bici al año', 'volumen.km_bici', { step: 100 }) +
                    campoNum('Km de carrera al año', 'volumen.km_run', { step: 50 }) +
                    campoNum('Km de natación al año', 'volumen.km_swim', { step: 10 }) +
                    campoNum('Tu peso', 'volumen.peso', { min: 40, max: 140, sufijo: 'kg', nota: 'Por encima de 85 kg las zapatillas duran menos.' }) +
                    campoSelect('Nutrición', 'consumibles.nutricion_tipo', Object.keys(DATA.consumibles.nutricion.precio_g).map(function (k) {
                        return { id: k, label: DATA.consumibles.nutricion.etiquetas[k] };
                    })) +
                    campoSelect('Entrenador', 'recurrentes.entrenador', [
                        { id: 'si', label: 'Sí, cuota mensual' },
                        { id: 'premium', label: 'Sí, seguimiento premium' },
                        { id: 'no', label: 'No, me lo planifico yo' }
                    ]) +
                    campoNum('Sesiones de fisio al año', 'recurrentes.fisio_sesiones', { max: 60 }) +
                    campoSelect('Licencia / seguro', 'recurrentes.licencia', [
                        { id: 'anual', label: 'Licencia federativa anual' },
                        { id: 'dia', label: 'Seguro de un día' },
                        { id: 'no', label: 'Ninguno' }
                    ]) +
                    campoSelect('Piscina', 'recurrentes.natacion_modo', [
                        { id: 'abono', label: 'Abono mensual' },
                        { id: 'entrada', label: 'Entrada suelta' },
                        { id: 'ninguno', label: 'Gratis / aguas abiertas' }
                    ]) +
                    campoNum('Sesiones de piscina a la semana', 'recurrentes.sesiones_natacion_semana', { max: 14, nota: 'Solo cuenta si pagas por entrada.' }) +
                '</div>' +
                '<div class="icc-checks">' +
                    campoCheck('Zapatillas de competición con placa de carbono', 'consumibles.zapatillas_carrera') +
                    campoCheck('Cuota de club', 'recurrentes.club') +
                    campoCheck('Plataformas de entrenamiento (TrainingPeaks, Zwift…)', 'recurrentes.suscripciones') +
                    campoCheck('Seguro de la bici', 'recurrentes.seguro_bici') +
                '</div>' +
            '</section>' +

            /* 4. viaje */
            '<section class="icc-card">' +
                '<h3 class="icc-card__title"><span>4</span> El viaje</h3>' +
                '<p class="icc-card__intro">Una prueba cerca de casa puede salir más barata que otra con una inscripción menor.</p>' +
                '<div class="icc-grid">' +
                    campoSelect('Cómo vas', 'viaje.modo', [
                        { id: 'coche', label: 'En coche' },
                        { id: 'avion', label: 'En avión' },
                        { id: 'casa', label: 'Es en mi ciudad' }
                    ]) +
                    campoNum('Km de ida', 'viaje.km_coche', { step: 10, sufijo: 'km', nota: 'Se cuenta ida y vuelta a ' + DATA.evento.viaje.coche_eur_km + ' €/km.' }) +
                    campoNum('Peajes (ida)', 'viaje.peajes', { sufijo: '€' }) +
                    campoNum('Billete de avión (ida y vuelta)', 'viaje.billete', { sufijo: '€', placeholder: 'No lo sé' }) +
                    campoNum('Tasa de la bici por trayecto', 'viaje.tasa_bici', { sufijo: '€', placeholder: 'No lo sé' }) +
                    campoNum('Noches de hotel', 'viaje.noches', { max: 14 }) +
                    campoNum('Precio por noche', 'viaje.precio_noche', { sufijo: '€', placeholder: 'No lo sé' }) +
                    campoNum('Personas por habitación', 'viaje.personas_hab', { min: 1, max: 6 }) +
                    campoNum('Días fuera', 'viaje.dias', { max: 14 }) +
                    campoNum('Acompañantes', 'viaje.acompanantes', { max: 8, nota: 'Multiplican billetes y comidas.' }) +
                '</div>' +
                '<div class="icc-checks">' +
                    campoCheck('Contar extras: fotos oficiales, ropa de la prueba, la cena de después', 'viaje.extras') +
                '</div>' +
            '</section>' +
            '</form>' +

            /* resultado */
            '<aside class="icc-res">' +
                '<div class="icc-res__inner">' +
                    '<div class="icc-head">' +
                        '<p class="icc-head__kicker">Lo que sale de tu cuenta esta temporada</p>' +
                        '<p class="icc-head__big" data-out="desembolso">—</p>' +
                        '<p class="icc-head__band" data-out="desembolso_band">—</p>' +
                    '</div>' +
                    '<div class="icc-head icc-head--alt">' +
                        '<p class="icc-head__kicker">Coste real de la temporada <span class="icc-f__help" title="El material duradero reparte su precio entre las temporadas que va a durar, descontando lo que recuperarías vendiéndolo. Es lo que de verdad te cuesta el año, tengas la bici pagada o no.">?</span></p>' +
                        '<p class="icc-head__big" data-out="anual">—</p>' +
                        '<p class="icc-head__band" data-out="anual_band">—</p>' +
                    '</div>' +
                    '<ul class="icc-kpis">' +
                        '<li><span data-out="kpi_evento">—</span><small>solo la prueba y el viaje</small></li>' +
                        '<li><span data-out="kpi_pct">—</span><small>del total es la inscripción</small></li>' +
                        '<li><span data-out="kpi_mes">—</span><small>al mes de preparación</small></li>' +
                        '<li><span data-out="kpi_hora">—</span><small>por hora de entreno</small></li>' +
                    '</ul>' +
                    '<div class="icc-margen">' +
                        '<label>Margen para imprevistos: <strong data-out="margen">10 %</strong>' +
                        '<input type="range" min="0" max="30" step="1" data-path="margen_pct"></label>' +
                    '</div>' +
                    '<div class="icc-desglose" data-out="desglose"></div>' +
                    '<div class="icc-acciones">' +
                        (tel ? '<a class="icc-btn icc-btn--wa" data-out="wa" href="#" target="_blank" rel="noopener">Enviarme el desglose por WhatsApp</a>' : '') +
                        '<button type="button" class="icc-btn icc-btn--ghost" data-action="copiar">Copiar el desglose</button>' +
                    '</div>' +
                    '<details class="icc-supuestos"><summary>Ver supuestos y fuentes</summary><div data-out="fuentes"></div></details>' +
                    '<p class="icc-legal">Tabla de precios v' + esc(DATA.version) + ', actualizada en ' + esc(DATA.actualizado) + '. ' +
                        'Los precios cambian cada temporada y de una prueba a otra: esto es una horquilla de trabajo, no un presupuesto cerrado. ' +
                        'Para el número real, consulta el precio de tu prueba con fecha.</p>' +
                '</div>' +
            '</aside>' +
        '</div>';
    }

    /* --------------------------------------------------------- binding */

    function get(obj, path) {
        return path.split('.').reduce(function (o, k) { return o === undefined || o === null ? o : o[k]; }, obj);
    }
    function set(obj, path, value) {
        var keys = path.split('.');
        var last = keys.pop();
        var target = keys.reduce(function (o, k) { return o[k] = o[k] || {}; }, obj);
        target[last] = value;
    }

    function sincronizarFormulario(host, estado) {
        host.querySelectorAll('[data-path]').forEach(function (el) {
            var v = get(estado, el.getAttribute('data-path'));
            if (el.type === 'checkbox') {
                el.checked = !!v;
            } else if (el.type === 'number' || el.type === 'range') {
                el.value = (v === null || v === undefined) ? '' : v;
            } else {
                el.value = v === null || v === undefined ? '' : v;
            }
        });
        host.querySelectorAll('[data-seg]').forEach(function (seg) {
            var v = get(estado, seg.getAttribute('data-seg'));
            seg.querySelectorAll('button').forEach(function (b) {
                b.classList.toggle('is-on', b.getAttribute('data-value') === v);
            });
        });
        host.querySelectorAll('.icc-perfil').forEach(function (b) {
            b.classList.toggle('is-on', b.getAttribute('data-perfil') === estado.perfil);
        });

        // Campos que solo aplican según el modo de viaje / piscina.
        var modo = estado.viaje.modo;
        ['viaje.km_coche', 'viaje.peajes'].forEach(function (p) { toggleCampo(host, p, modo === 'coche'); });
        ['viaje.billete', 'viaje.tasa_bici'].forEach(function (p) { toggleCampo(host, p, modo === 'avion'); });
        ['viaje.noches', 'viaje.precio_noche', 'viaje.personas_hab', 'viaje.dias', 'viaje.acompanantes'].forEach(function (p) {
            toggleCampo(host, p, modo !== 'casa');
        });
        toggleCampo(host, 'recurrentes.sesiones_natacion_semana', estado.recurrentes.natacion_modo === 'entrada');
    }

    function toggleCampo(host, path, visible) {
        var el = host.querySelector('[data-path="' + path + '"]');
        if (!el) return;
        var campo = el.closest('.icc-f');
        if (campo) campo.style.display = visible ? '' : 'none';
    }

    function construirInput(estado) {
        return {
            prueba: estado.prueba,
            volumen: estado.volumen,
            material: estado.material,
            consumibles: estado.consumibles,
            recurrentes: estado.recurrentes,
            viaje: {
                modo: estado.viaje.modo,
                km_coche: estado.viaje.km_coche,
                peajes: estado.viaje.peajes,
                billete: estado.viaje.billete,
                tasa_bici: estado.viaje.tasa_bici,
                noches: estado.viaje.modo === 'casa' ? 0 : estado.viaje.noches,
                precio_noche: estado.viaje.precio_noche,
                personas_hab: estado.viaje.personas_hab,
                dias: estado.viaje.modo === 'casa' ? 0 : estado.viaje.dias,
                acompanantes: estado.viaje.acompanantes,
                extras: estado.viaje.extras,
                extras_eur: null
            },
            margen_pct: estado.margen_pct
        };
    }

    function pintar(host, estado) {
        var r = ENGINE.calcular(construirInput(estado), DATA);
        var out = function (k) { return host.querySelector('[data-out="' + k + '"]'); };

        out('desembolso').textContent = eur(r.totales.desembolso.central);
        out('desembolso_band').textContent = 'entre ' + eur(r.totales.desembolso.min) + ' y ' + eur(r.totales.desembolso.max);
        out('anual').textContent = eur(r.totales.anual.central);
        out('anual_band').textContent = 'entre ' + eur(r.totales.anual.min) + ' y ' + eur(r.totales.anual.max);
        out('kpi_evento').textContent = eur(r.totales.evento.central);
        out('kpi_pct').textContent = num(r.metricas.pct_inscripcion, 0) + ' %';
        out('kpi_mes').textContent = eur(r.metricas.coste_mes);
        out('kpi_hora').textContent = eur(r.metricas.coste_hora);
        out('margen').textContent = num(estado.margen_pct) + ' %';

        // Desglose por grupos.
        var html = '';
        Object.keys(GRUPOS).forEach(function (g) {
            var items = r.partidas.filter(function (p) { return p.grupo === g; });
            if (!items.length) return;
            var totalG = ENGINE.suma.apply(null, items.map(function (p) { return p.anual; }));
            var pct = (totalG.central / Math.max(1, r.totales.anual.central)) * 100;
            html += '<div class="icc-grupo">' +
                '<div class="icc-grupo__head">' +
                    '<span>' + esc(GRUPOS[g]) + '</span>' +
                    '<span class="icc-grupo__tot">' + eur(totalG.central) + '</span>' +
                '</div>' +
                '<div class="icc-bar"><i style="width:' + Math.min(100, pct).toFixed(1) + '%"></i></div>' +
                '<table class="icc-tabla"><tbody>' +
                items.sort(function (a, b) { return b.anual.central - a.anual.central; }).map(function (p) {
                    var dif = Math.abs(p.desembolso.central - p.anual.central) > 1;
                    return '<tr>' +
                        '<th>' + esc(p.label) +
                            (p.situacion === 'tengo' ? ' <em class="icc-tag">ya lo tienes</em>' : '') +
                            (p.situacion === 'alquilo' ? ' <em class="icc-tag">alquiler</em>' : '') +
                            '<span class="icc-tabla__det">' + esc(p.detalle) + '</span>' +
                        '</th>' +
                        '<td>' + eur(p.anual.central) +
                            (dif ? '<span class="icc-tabla__alt">pagas ahora ' + eur(p.desembolso.central) + '</span>' : '') +
                        '</td>' +
                    '</tr>';
                }).join('') +
                '</tbody></table></div>';
        });
        out('desglose').innerHTML = html;

        // Fuentes.
        var vistas = {};
        out('fuentes').innerHTML = '<ul class="icc-fuentes">' + r.partidas.map(function (p) {
            if (!p.fuente || vistas[p.fuente]) return '';
            vistas[p.fuente] = true;
            return '<li><strong>' + esc(p.label) + '</strong> ' +
                '<em class="icc-tag icc-tag--' + esc(p.fiabilidad || 'estimacion') + '">' +
                (p.fiabilidad === 'contrastado' ? 'dato contrastado' : 'estimación') + '</em>' +
                '<span>' + esc(p.fuente) + '</span>' +
                '<small>Revisado: ' + esc(p.fecha) + '</small></li>';
        }).join('') + '</ul>';

        // Resumen de texto para WhatsApp y portapapeles.
        var resumen = resumenTexto(r, estado);
        host.__iccResumen = resumen;
        var wa = out('wa');
        if (wa) {
            var tel = host.getAttribute('data-whatsapp').replace(/[^0-9]/g, '');
            wa.href = 'https://wa.me/' + tel + '?text=' + encodeURIComponent(resumen);
        }

        // Coste por material en cada fila.
        r.partidas.forEach(function (p) {
            var fila = host.querySelector('.icc-mat[data-item="' + p.id + '"] .icc-mat__out');
            if (!fila) return;
            fila.innerHTML = '<strong>' + eur(p.anual.central) + '</strong> esta temporada' +
                (Math.abs(p.desembolso.central - p.anual.central) > 1
                    ? ' · <span>' + eur(p.desembolso.central) + ' de desembolso ahora</span>' : '');
        });
        DATA.equipo.forEach(function (item) {
            if (estado.material[item.id].situacion !== 'no_uso') return;
            var fila = host.querySelector('.icc-mat[data-item="' + item.id + '"] .icc-mat__out');
            if (fila) fila.innerHTML = '<span class="icc-mat__off">No lo cuentas</span>';
        });
    }

    function resumenTexto(r, estado) {
        var L = [];
        L.push('Mi presupuesto para el ' + (estado.prueba.distancia === 'full' ? 'IRONMAN' : 'medio IRONMAN') + ':');
        L.push('');
        L.push('Sale de mi cuenta esta temporada: ' + eur(r.totales.desembolso.central) +
            ' (entre ' + eur(r.totales.desembolso.min) + ' y ' + eur(r.totales.desembolso.max) + ')');
        L.push('Coste real de la temporada, con el material amortizado: ' + eur(r.totales.anual.central));
        L.push('Solo la prueba y el viaje: ' + eur(r.totales.evento.central));
        L.push('La inscripción es el ' + num(r.metricas.pct_inscripcion, 0) + ' % del total.');
        L.push('');
        L.push('Por partidas (coste de la temporada):');
        Object.keys(GRUPOS).forEach(function (g) {
            var items = r.partidas.filter(function (p) { return p.grupo === g; });
            if (!items.length) return;
            var t = ENGINE.suma.apply(null, items.map(function (p) { return p.anual; }));
            L.push('- ' + GRUPOS[g] + ': ' + eur(t.central));
        });
        L.push('');
        L.push('Incluye un ' + num(estado.margen_pct) + ' % de margen para imprevistos.');
        L.push('Calculado con ' + estado.prueba.semanas + ' semanas de preparación y ' +
            num(r.metricas.horas_anio) + ' horas de entreno.');
        return L.join('\n');
    }

    /* --------------------------------------------------------- arranque */

    /** Mezcla profunda de objetos planos (los arrays se sustituyen enteros). */
    function fusionar(destino, origen) {
        Object.keys(origen).forEach(function (k) {
            var v = origen[k];
            if (v && typeof v === 'object' && !Array.isArray(v) && destino[k] && typeof destino[k] === 'object' && !Array.isArray(destino[k])) {
                fusionar(destino[k], v);
            } else {
                destino[k] = v;
            }
        });
    }

    /**
     * Sustituye la horquilla de una gama por el precio real de un producto
     * concreto, scrapeado de la tienda. Formato:
     * { 'bici.tt_media': { precio: 4299, modelo: '...', tienda: '...', fecha: '...' } }
     */
    function aplicarPreciosReales(data, precios) {
        Object.keys(precios).forEach(function (clave) {
            var ref = precios[clave];
            if (!ref || !ref.precio) return;
            var partes = clave.split('.');
            data.equipo.forEach(function (item) {
                if (item.id !== partes[0]) return;
                item.opciones.forEach(function (op) {
                    if (op.id !== partes[1]) return;
                    op.precio = [ref.precio, ref.precio];
                    if (ref.modelo) op.label = op.label + ' — ' + ref.modelo;
                });
                item.fuente = 'Precio real de ' + (ref.modelo || 'un modelo de referencia') +
                    (ref.tienda ? ' en ' + ref.tienda : '') + ', leído automáticamente de la tienda.';
                item.fecha = ref.fecha || item.fecha;
                item.fiabilidad = 'contrastado';
            });
        });
    }

    function init(host) {
        DATA = root.ICC_DATA;
        ENGINE = root.ICC_ENGINE;
        if (!DATA || !ENGINE) return;

        // Sobreescrituras desde WordPress: ajustes del admin y precios reales
        // scrapeados de tienda (ICC_CONFIG.precios), que mandan sobre la horquilla.
        if (root.ICC_CONFIG) {
            DATA = JSON.parse(JSON.stringify(DATA));
            if (root.ICC_CONFIG.data) fusionar(DATA, root.ICC_CONFIG.data);
            if (root.ICC_CONFIG.precios) aplicarPreciosReales(DATA, root.ICC_CONFIG.precios);
        }

        var estado = estadoInicial();
        render(host);
        sincronizarFormulario(host, estado);
        pintar(host, estado);

        host.addEventListener('input', function (e) {
            var el = e.target;
            var path = el.getAttribute && el.getAttribute('data-path');
            if (!path) return;
            var valor;
            if (el.type === 'checkbox') {
                valor = el.checked;
            } else if (el.type === 'number' || el.type === 'range') {
                valor = el.value === '' ? null : Number(el.value);
            } else {
                valor = el.value;
            }
            set(estado, path, valor);

            // Cambiar de distancia recarga los volúmenes de referencia.
            if (path === 'prueba.distancia') {
                var vol = DATA.volumen[valor];
                estado.volumen.horas_semana = vol.horas_semana;
                estado.volumen.km_bici = vol.km_bici;
                estado.volumen.km_run = vol.km_run;
                estado.volumen.km_swim = vol.km_swim;
                estado.volumen.horas_largas_semana = vol.horas_largas_semana;
                estado.prueba.semanas = vol.semanas;
                estado.prueba.horas_carrera = valor === 'full' ? 12 : 6;
            }
            sincronizarFormulario(host, estado);
            pintar(host, estado);
        });

        host.addEventListener('change', function (e) {
            if (e.target.tagName === 'SELECT') {
                host.dispatchEvent(new Event('input'));
                sincronizarFormulario(host, estado);
                pintar(host, estado);
            }
        });

        host.addEventListener('click', function (e) {
            var seg = e.target.closest('.icc-seg button');
            if (seg) {
                set(estado, seg.parentNode.getAttribute('data-seg'), seg.getAttribute('data-value'));
                estado.perfil = '';
                sincronizarFormulario(host, estado);
                pintar(host, estado);
                return;
            }
            var perfil = e.target.closest('.icc-perfil');
            if (perfil) {
                var key = perfil.getAttribute('data-perfil');
                var def = DATA.perfiles[key];
                estado.perfil = key;
                Object.keys(def.material).forEach(function (id) {
                    if (!estado.material[id]) return;
                    if (def.material[id].opcion) estado.material[id].opcion = def.material[id].opcion;
                    estado.material[id].situacion = def.material[id].situacion;
                });
                sincronizarFormulario(host, estado);
                pintar(host, estado);
                return;
            }
            var copiar = e.target.closest('[data-action="copiar"]');
            if (copiar) {
                var txt = host.__iccResumen || '';
                var done = function () {
                    copiar.textContent = 'Copiado';
                    setTimeout(function () { copiar.textContent = 'Copiar el desglose'; }, 1800);
                };
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(txt).then(done, done);
                } else {
                    var ta = doc.createElement('textarea');
                    ta.value = txt; doc.body.appendChild(ta); ta.select();
                    doc.execCommand('copy'); doc.body.removeChild(ta); done();
                }
            }
        });
    }

    function boot() {
        doc.querySelectorAll('.icc').forEach(init);
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    root.ICC_UI = { init: init };
})(window, document);
