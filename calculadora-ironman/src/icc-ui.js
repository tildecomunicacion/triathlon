    /**
     * Calculadora de coste real IRONMAN — interfaz.
     *
     * Monta el formulario desde ICC_DATA y recalcula en cada cambio. El markup
     * y las clases son los de la calculadora de sudoración (card, field, stat,
     * level-badge, btn-calc...) para que las dos se vean igual.
     */
    (function (root, doc) {
      'use strict';

      var DATA, ENGINE;

      var eur = function (n) {
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

      /* ------------------------------------------------------------ estado */

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

      /* -------------------------------------------------------- plantillas */

      function campoNum(label, path, opts) {
        opts = opts || {};
        return '<div class="field' + (opts.ancho ? ' field--full' : '') + '">' +
          '<label class="field__label" for="f-' + path + '">' + esc(label) +
          (opts.ayuda ? '<span class="field__help" title="' + esc(opts.ayuda) + '">?</span>' : '') + '</label>' +
          (opts.hint ? '<p class="field__hint">' + esc(opts.hint) + '</p>' : '') +
          '<span class="field__wrap">' +
          '<input class="field__input" type="number" id="f-' + path + '" data-path="' + path + '"' +
          ' min="' + (opts.min !== undefined ? opts.min : 0) + '"' +
          (opts.max !== undefined ? ' max="' + opts.max + '"' : '') +
          (opts.step ? ' step="' + opts.step + '"' : '') +
          (opts.placeholder ? ' placeholder="' + esc(opts.placeholder) + '"' : '') + '>' +
          (opts.sufijo ? '<span class="field__suffix">' + esc(opts.sufijo) + '</span>' : '') +
          '</span></div>';
      }

      function campoSelect(label, path, opciones, opts) {
        opts = opts || {};
        return '<div class="field' + (opts.ancho ? ' field--full' : '') + '">' +
          '<label class="field__label" for="f-' + path + '">' + esc(label) + '</label>' +
          (opts.hint ? '<p class="field__hint">' + esc(opts.hint) + '</p>' : '') +
          '<select class="field__input" id="f-' + path + '" data-path="' + path + '">' +
          opciones.map(function (o) {
            return '<option value="' + esc(o.id) + '">' + esc(o.label) + '</option>';
          }).join('') +
          '</select></div>';
      }

      function campoCheck(label, path) {
        return '<label class="check"><input type="checkbox" data-path="' + path + '"><span>' + esc(label) + '</span></label>';
      }

      function filaMaterial(item) {
        var min = Math.min.apply(null, item.opciones.map(function (o) { return o.precio[0]; }));
        var max = Math.max.apply(null, item.opciones.map(function (o) { return o.precio[1]; }));
        var situaciones = DATA.situaciones.filter(function (s) {
          return s.id !== 'alquilo' || item.alquiler;
        });
        return '<div class="gear" data-item="' + esc(item.id) + '">' +
          '<div class="gear__head">' +
            '<span class="gear__name">' + esc(item.label) + '</span>' +
            '<span class="gear__range">' + eur(min) + ' – ' + eur(max) + '</span>' +
          '</div>' +
          (item.nota ? '<p class="gear__note">' + esc(item.nota) + '</p>' : '') +
          '<div class="gear__controls">' +
            '<select class="field__input" data-path="material.' + item.id + '.opcion">' +
              item.opciones.map(function (o) {
                return '<option value="' + esc(o.id) + '">' + esc(o.label) + ' · ' +
                  num(o.precio[0]) + '–' + num(o.precio[1]) + ' €</option>';
              }).join('') +
            '</select>' +
            '<div class="seg" data-seg="material.' + item.id + '.situacion">' +
              situaciones.map(function (s) {
                return '<button type="button" data-value="' + esc(s.id) + '">' + esc(s.label) + '</button>';
              }).join('') +
            '</div>' +
          '</div>' +
          '<p class="gear__out"></p>' +
        '</div>';
      }

      function render(host) {
        var tel = host.getAttribute('data-whatsapp') || '';
        host.innerHTML =

        /* 1. la prueba */
        '<div class="card">' +
          '<div class="card__header">' +
            '<p class="card__tag">Tu prueba</p>' +
            '<p class="card__header-title">Elige <span>tu carrera</span></p>' +
            '<p class="card__header-sub">Si no sabes el precio del dorsal, déjalo vacío y usamos la horquilla publicada</p>' +
          '</div>' +
          '<div class="card__body"><div class="fields-grid">' +
            campoSelect('Distancia', 'prueba.distancia', [
              { id: 'full', label: 'IRONMAN completo (3,8 / 180 / 42,195)' },
              { id: 'medio', label: 'Medio IRONMAN (1,9 / 90 / 21,1)' }
            ], { ancho: true }) +
            campoNum('Inscripción', 'prueba.inscripcion', { sufijo: '€', placeholder: 'No lo sé aún', hint: 'El dorsal sube por tramos según se llena el cupo' }) +
            campoNum('Gastos de gestión', 'prueba.gestion_pct', { sufijo: '%', step: 0.25, hint: 'Se cobran aparte del dorsal' }) +
            campoNum('Semanas de preparación', 'prueba.semanas', { min: 4, max: 60, hint: 'Marcan los meses de cuotas' }) +
            campoNum('Horas que esperas tardar', 'prueba.horas_carrera', { min: 3, max: 17, sufijo: 'h', hint: 'Marca la nutrición del día de carrera' }) +
          '</div></div>' +
        '</div>' +

        /* 2. material */
        '<div class="card">' +
          '<div class="card__header">' +
            '<p class="card__tag">Tu material</p>' +
            '<p class="card__header-title">Lo que <span>ya tienes</span></p>' +
            '<p class="card__header-sub">Lo que ya tienes no sale de tu cuenta, pero se gasta: cuenta su parte del año</p>' +
          '</div>' +
          '<div class="card__body">' +
            '<div class="profiles">' +
              Object.keys(DATA.perfiles).map(function (k) {
                var p = DATA.perfiles[k];
                return '<button type="button" class="profile" data-perfil="' + esc(k) + '">' +
                  '<strong>' + esc(p.label) + '</strong><span>' + esc(p.descripcion) + '</span></button>';
              }).join('') +
            '</div>' +
            DATA.equipo.map(filaMaterial).join('') +
          '</div>' +
        '</div>' +

        /* 3. temporada */
        '<div class="card">' +
          '<div class="card__header">' +
            '<p class="card__tag">Tu temporada</p>' +
            '<p class="card__header-title">Lo que <span>entrenas</span></p>' +
            '<p class="card__header-sub">Los consumibles salen de aquí: zapatillas, transmisión, nutrición y piscina</p>' +
          '</div>' +
          '<div class="card__body"><div class="fields-grid">' +
            campoNum('Horas a la semana', 'volumen.horas_semana', { min: 3, max: 30, step: 0.5 }) +
            campoNum('Horas de sesión larga', 'volumen.horas_largas_semana', { min: 0, max: 20, step: 0.5, hint: 'A la semana. Las que llevan geles y bebida' }) +
            campoNum('Km de bici al año', 'volumen.km_bici', { step: 100 }) +
            campoNum('Km de carrera al año', 'volumen.km_run', { step: 50 }) +
            campoNum('Km de natación al año', 'volumen.km_swim', { step: 10 }) +
            campoNum('Tu peso', 'volumen.peso', { min: 40, max: 140, sufijo: 'kg', hint: 'Por encima de 85 kg las zapatillas duran menos' }) +
            campoSelect('Nutrición', 'consumibles.nutricion_tipo', Object.keys(DATA.consumibles.nutricion.precio_g).map(function (k) {
              return { id: k, label: DATA.consumibles.nutricion.etiquetas[k] };
            }), { ancho: true }) +
            campoSelect('Entrenador', 'recurrentes.entrenador', [
              { id: 'si', label: 'Sí, cuota mensual' },
              { id: 'premium', label: 'Sí, seguimiento premium' },
              { id: 'no', label: 'No, me lo planifico yo' }
            ]) +
            campoNum('Sesiones de fisio al año', 'recurrentes.fisio_sesiones', { max: 60 }) +
            campoSelect('Licencia o seguro', 'recurrentes.licencia', [
              { id: 'anual', label: 'Licencia federativa anual' },
              { id: 'dia', label: 'Seguro de un día' },
              { id: 'no', label: 'Ninguno' }
            ]) +
            campoSelect('Piscina', 'recurrentes.natacion_modo', [
              { id: 'abono', label: 'Abono mensual' },
              { id: 'entrada', label: 'Entrada suelta' },
              { id: 'ninguno', label: 'Gratis o aguas abiertas' }
            ]) +
            campoNum('Sesiones de piscina', 'recurrentes.sesiones_natacion_semana', { max: 14, hint: 'A la semana. Solo cuenta si pagas por entrada' }) +
          '</div>' +
          '<div class="checks">' +
            campoCheck('Zapatillas de competición con placa de carbono', 'consumibles.zapatillas_carrera') +
            campoCheck('Cuota de club', 'recurrentes.club') +
            campoCheck('Plataformas de entrenamiento (TrainingPeaks, Zwift…)', 'recurrentes.suscripciones') +
            campoCheck('Seguro de la bici', 'recurrentes.seguro_bici') +
          '</div></div>' +
        '</div>' +

        /* 4. viaje */
        '<div class="card">' +
          '<div class="card__header">' +
            '<p class="card__tag">El viaje</p>' +
            '<p class="card__header-title">Cómo <span>llegas allí</span></p>' +
            '<p class="card__header-sub">Una prueba cerca de casa sale más barata que otra con una inscripción menor</p>' +
          '</div>' +
          '<div class="card__body"><div class="fields-grid">' +
            campoSelect('Cómo vas', 'viaje.modo', [
              { id: 'coche', label: 'En coche' },
              { id: 'avion', label: 'En avión' },
              { id: 'casa', label: 'Es en mi ciudad' }
            ], { ancho: true }) +
            campoNum('Km de ida', 'viaje.km_coche', { step: 10, sufijo: 'km', hint: 'Ida y vuelta a ' + DATA.evento.viaje.coche_eur_km + ' €/km' }) +
            campoNum('Peajes de ida', 'viaje.peajes', { sufijo: '€' }) +
            campoNum('Billete ida y vuelta', 'viaje.billete', { sufijo: '€', placeholder: 'No lo sé' }) +
            campoNum('Tasa de la bici', 'viaje.tasa_bici', { sufijo: '€', placeholder: 'No lo sé', hint: 'Por trayecto: se cobra en los dos' }) +
            campoNum('Noches de hotel', 'viaje.noches', { max: 14, hint: 'Dorsal, boxes y reunión técnica piden llegar antes' }) +
            campoNum('Precio por noche', 'viaje.precio_noche', { sufijo: '€', placeholder: 'No lo sé' }) +
            campoNum('Personas por habitación', 'viaje.personas_hab', { min: 1, max: 6 }) +
            campoNum('Días fuera', 'viaje.dias', { max: 14 }) +
            campoNum('Acompañantes', 'viaje.acompanantes', { max: 8, hint: 'Multiplican billetes y comidas' }) +
          '</div>' +
          '<div class="checks">' +
            campoCheck('Contar extras: fotos oficiales, ropa de la prueba, la cena de después', 'viaje.extras') +
          '</div></div>' +
        '</div>' +

        /* resultado */
        '<div class="results">' +
          '<div class="results__header">' +
            '<p class="results__header-tag">Tu resultado</p>' +
            '<p class="results__header-title">Lo que <span>te cuesta</span> de verdad</p>' +
          '</div>' +
          '<div class="results__body">' +
            '<div class="level-badge"><span class="level-badge__dot"></span><span data-out="banda">—</span></div>' +
            '<div class="stats-grid">' +
              '<div class="stat stat--highlight">' +
                '<p class="stat__label">Sale de tu cuenta</p>' +
                '<p class="stat__value" data-out="desembolso">—</p>' +
                '<p class="stat__foot">Lo que pagas esta temporada</p>' +
              '</div>' +
              '<div class="stat">' +
                '<p class="stat__label">Coste real del año</p>' +
                '<p class="stat__value" data-out="anual">—</p>' +
                '<p class="stat__foot">Con el material amortizado</p>' +
              '</div>' +
              '<div class="stat">' +
                '<p class="stat__label">Solo la prueba y el viaje</p>' +
                '<p class="stat__value" data-out="evento">—</p>' +
                '<p class="stat__foot" data-out="evento_pie">—</p>' +
              '</div>' +
              '<div class="stat">' +
                '<p class="stat__label">Al mes de preparación</p>' +
                '<p class="stat__value" data-out="mes">—</p>' +
                '<p class="stat__foot" data-out="hora">—</p>' +
              '</div>' +
            '</div>' +
            '<div class="margin-row">' +
              '<label for="f-margen">Margen para imprevistos: <strong data-out="margen">10 %</strong></label>' +
              '<input type="range" id="f-margen" min="0" max="30" step="1" data-path="margen_pct">' +
            '</div>' +
            '<div class="breakdown" data-out="desglose"></div>' +
            '<div class="interpretation" data-out="interpretacion"></div>' +
            (tel ? '<a class="btn-calc" data-out="wa" href="#" target="_blank" rel="noopener">Enviarme el desglose →</a>' : '') +
            '<button type="button" class="btn-reset" data-action="copiar">Copiar el desglose</button>' +
            '<details class="sources"><summary>Ver supuestos y fuentes</summary><div data-out="fuentes"></div></details>' +
            '<p class="disclaimer">Tabla de precios v' + esc(DATA.version) + ', revisada en ' + esc(DATA.actualizado) + '. ' +
              'Los precios cambian cada temporada, de una prueba a otra y según el país, así que esto es una horquilla de trabajo y no un presupuesto cerrado. ' +
              'Para el número real, consulta el precio de tu prueba con fecha.</p>' +
          '</div>' +
        '</div>';
      }

      /* ---------------------------------------------------------- binding */

      function get(obj, path) {
        return path.split('.').reduce(function (o, k) { return o === undefined || o === null ? o : o[k]; }, obj);
      }
      function set(obj, path, value) {
        var keys = path.split('.');
        var last = keys.pop();
        var target = keys.reduce(function (o, k) { return o[k] = o[k] || {}; }, obj);
        target[last] = value;
      }

      function sincronizar(host, estado) {
        host.querySelectorAll('[data-path]').forEach(function (el) {
          var v = get(estado, el.getAttribute('data-path'));
          if (el.type === 'checkbox') {
            el.checked = !!v;
          } else {
            el.value = (v === null || v === undefined) ? '' : v;
          }
        });
        host.querySelectorAll('[data-seg]').forEach(function (seg) {
          var v = get(estado, seg.getAttribute('data-seg'));
          seg.querySelectorAll('button').forEach(function (b) {
            b.classList.toggle('is-on', b.getAttribute('data-value') === v);
          });
        });
        host.querySelectorAll('.profile').forEach(function (b) {
          b.classList.toggle('is-on', b.getAttribute('data-perfil') === estado.perfil);
        });

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
        var campo = el.closest('.field');
        if (campo) campo.hidden = !visible;
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

        out('banda').textContent = 'Entre ' + eur(r.totales.desembolso.min) + ' y ' + eur(r.totales.desembolso.max);
        out('desembolso').textContent = eur(r.totales.desembolso.central);
        out('anual').textContent = eur(r.totales.anual.central);
        out('evento').textContent = eur(r.totales.evento.central);
        out('evento_pie').textContent = 'La inscripción es el ' + num(r.metricas.pct_inscripcion, 0) + ' % del total';
        out('mes').textContent = eur(r.metricas.coste_mes);
        out('hora').textContent = eur(r.metricas.coste_hora) + ' por hora de entreno';
        out('margen').textContent = num(estado.margen_pct) + ' %';

        // Desglose por grupos.
        var html = '';
        Object.keys(GRUPOS).forEach(function (g) {
          var items = r.partidas.filter(function (p) { return p.grupo === g; });
          if (!items.length) return;
          var totalG = ENGINE.suma.apply(null, items.map(function (p) { return p.anual; }));
          var pct = (totalG.central / Math.max(1, r.totales.anual.central)) * 100;
          html += '<div class="group">' +
            '<div class="group__head"><span>' + esc(GRUPOS[g]) + '</span>' +
            '<span class="group__tot">' + eur(totalG.central) + '</span></div>' +
            '<div class="group__bar"><i style="width:' + Math.min(100, pct).toFixed(1) + '%"></i></div>' +
            items.sort(function (a, b) { return b.anual.central - a.anual.central; }).map(function (p) {
              var dif = Math.abs(p.desembolso.central - p.anual.central) > 1;
              return '<div class="line">' +
                '<span class="line__name">' + esc(p.label) +
                  (p.situacion === 'tengo' ? ' <span class="tag tag--tengo">ya lo tienes</span>' : '') +
                  (p.situacion === 'alquilo' ? ' <span class="tag tag--alquiler">alquiler</span>' : '') +
                  '<span class="line__det">' + esc(p.detalle) + '</span></span>' +
                '<span class="line__val"><b>' + eur(p.anual.central) + '</b>' +
                  (dif ? '<span class="line__alt">pagas ahora ' + eur(p.desembolso.central) + '</span>' : '') +
                '</span></div>';
            }).join('') +
          '</div>';
        });
        out('desglose').innerHTML = html;

        // Interpretación.
        var material = r.partidas.filter(function (p) { return p.grupo === 'material' && p.situacion === 'tengo'; });
        var yaTienes = material.length ? ENGINE.suma.apply(null, material.map(function (p) { return p.anual; })).central : 0;
        var meses = Math.round(estado.prueba.semanas / 4.33);
        var texto = '<strong>La inscripción es el ' + num(r.metricas.pct_inscripcion, 0) + ' % de lo que te cuesta la temporada.</strong> ' +
          'El resto son ' + eur(r.totales.anual.central - r.metricas.inscripcion) + ' repartidos entre material, entrenamiento, nutrición y viaje, ' +
          'o ' + eur(r.metricas.coste_mes) + ' al mes durante los ' + meses + ' meses de preparación. ';
        if (yaTienes > 0) {
          texto += 'Del material que ya tienes se gastan <strong>' + eur(yaTienes) + '</strong> este año aunque no pagues nada por él: ' +
            'esa es la diferencia entre lo que sale de tu cuenta y lo que de verdad cuesta.';
        } else {
          texto += 'Como lo compras casi todo ahora, tu desembolso es mayor que el coste real del año: lo que compras durará varias temporadas.';
        }
        out('interpretacion').innerHTML = texto;

        // Fuentes.
        var vistas = {};
        out('fuentes').innerHTML = '<ul>' + r.partidas.map(function (p) {
          if (!p.fuente || vistas[p.fuente]) return '';
          vistas[p.fuente] = true;
          return '<li><strong>' + esc(p.label) + '</strong> ' +
            '<span class="tag tag--' + esc(p.fiabilidad || 'estimacion') + '">' +
            (p.fiabilidad === 'contrastado' ? 'dato contrastado' : 'estimación') + '</span>' +
            '<span>' + esc(p.fuente) + '</span>' +
            '<small>Revisado: ' + esc(p.fecha) + '</small></li>';
        }).join('') + '</ul>';

        // Resumen para WhatsApp y portapapeles.
        var resumen = resumenTexto(r, estado);
        host.__iccResumen = resumen;
        var wa = out('wa');
        if (wa) {
          wa.href = 'https://wa.me/' + host.getAttribute('data-whatsapp').replace(/[^0-9]/g, '') +
            '?text=' + encodeURIComponent(resumen);
        }

        // Coste de cada pieza de material, en su fila.
        r.partidas.forEach(function (p) {
          var fila = host.querySelector('.gear[data-item="' + p.id + '"] .gear__out');
          if (!fila) return;
          fila.className = 'gear__out';
          fila.innerHTML = '<strong>' + eur(p.anual.central) + '</strong> esta temporada' +
            (Math.abs(p.desembolso.central - p.anual.central) > 1
              ? ' · ' + eur(p.desembolso.central) + ' de desembolso ahora' : '');
        });
        DATA.equipo.forEach(function (item) {
          if (estado.material[item.id].situacion !== 'no_uso') return;
          var fila = host.querySelector('.gear[data-item="' + item.id + '"] .gear__out');
          if (fila) { fila.className = 'gear__out gear__out--off'; fila.textContent = 'No lo cuentas'; }
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

      function init(host) {
        DATA = root.ICC_DATA;
        ENGINE = root.ICC_ENGINE;
        if (!DATA || !ENGINE) return;

        var estado = estadoInicial();
        render(host);
        sincronizar(host, estado);
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
          sincronizar(host, estado);
          pintar(host, estado);
        });

        host.addEventListener('change', function (e) {
          if (e.target.tagName !== 'SELECT') return;
          var path = e.target.getAttribute('data-path');
          if (path) set(estado, path, e.target.value);
          sincronizar(host, estado);
          pintar(host, estado);
        });

        host.addEventListener('click', function (e) {
          var seg = e.target.closest('.seg button');
          if (seg) {
            set(estado, seg.parentNode.getAttribute('data-seg'), seg.getAttribute('data-value'));
            estado.perfil = '';
            sincronizar(host, estado);
            pintar(host, estado);
            return;
          }
          var perfil = e.target.closest('.profile');
          if (perfil) {
            var def = DATA.perfiles[perfil.getAttribute('data-perfil')];
            estado.perfil = perfil.getAttribute('data-perfil');
            Object.keys(def.material).forEach(function (id) {
              if (!estado.material[id]) return;
              if (def.material[id].opcion) estado.material[id].opcion = def.material[id].opcion;
              estado.material[id].situacion = def.material[id].situacion;
            });
            sincronizar(host, estado);
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

      function boot() { doc.querySelectorAll('.icc').forEach(init); }

      if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
      } else {
        boot();
      }

      root.ICC_UI = { init: init };
    })(window, document);
