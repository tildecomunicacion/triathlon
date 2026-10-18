/**
 * Calculadora de coste real IRONMAN — motor de cálculo.
 *
 * Sin números dentro: todo sale de ICC_DATA y de lo que elige el usuario.
 * Devuelve siempre tres escenarios (min, central, max) porque dar una cifra
 * cerrada en esto es engañar.
 *
 * Dos totales distintos, y la diferencia es el punto de toda la calculadora:
 *   - desembolso: el dinero que sale de la cuenta esta temporada.
 *   - anual: el coste real de la temporada, con el material duradero
 *     amortizado entre las temporadas que va a durar y descontando lo que
 *     recuperarías vendiéndolo.
 */
(function (root) {
    'use strict';

    /* ---------------------------------------------------------------- utils */

    function tri(rango) {
        if (typeof rango === 'number') return { min: rango, central: rango, max: rango };
        var min = rango[0], max = rango[1];
        return { min: min, central: (min + max) / 2, max: max };
    }

    function fijo(valor) {
        var n = Number(valor) || 0;
        return { min: n, central: n, max: n };
    }

    function escala(t, factor) {
        return { min: t.min * factor, central: t.central * factor, max: t.max * factor };
    }

    function suma() {
        var out = { min: 0, central: 0, max: 0 };
        for (var i = 0; i < arguments.length; i++) {
            var t = arguments[i];
            if (!t) continue;
            out.min += t.min; out.central += t.central; out.max += t.max;
        }
        return out;
    }

    var CERO = { min: 0, central: 0, max: 0 };

    /** Amortización: (precio - valor residual) / temporadas de vida. */
    function amortizar(precio, vidaTemporadas, residual) {
        var vida = Math.max(1, Number(vidaTemporadas) || 1);
        var res = Math.min(0.9, Math.max(0, Number(residual) || 0));
        return escala(precio, (1 - res) / vida);
    }

    /* ------------------------------------------------------- 1. duraderos */

    function calcularEquipo(input, data) {
        var partidas = [];

        data.equipo.forEach(function (item) {
            var sel = (input.material && input.material[item.id]) || item.defecto || {};
            var situacion = sel.situacion || 'no_uso';
            if (situacion === 'no_uso') return;

            var opcion = null;
            for (var i = 0; i < item.opciones.length; i++) {
                if (item.opciones[i].id === (sel.opcion || (item.defecto && item.defecto.opcion))) {
                    opcion = item.opciones[i];
                }
            }
            if (!opcion) opcion = item.opciones[0];

            var desembolso = CERO, anual = CERO, detalle = '';

            if (situacion === 'compro') {
                var precio = tri(opcion.precio);
                desembolso = precio;
                anual = amortizar(precio, item.vida_temporadas, item.residual);
                detalle = 'Pagas ahora ' + opcion.label.toLowerCase() + '. Amortizado en ' +
                    item.vida_temporadas + ' temporadas, descontando el ' +
                    Math.round((item.residual || 0) * 100) + ' % que recuperarías de segunda mano.';
            } else if (situacion === 'tengo') {
                var precioT = tri(opcion.precio);
                desembolso = CERO;
                anual = amortizar(precioT, item.vida_temporadas, item.residual);
                detalle = 'Ya lo tienes, así que no sale dinero de la cuenta, pero se gasta: cuenta ' +
                    'su parte de esta temporada.';
            } else if (situacion === 'alquilo') {
                var alq = tri(item.alquiler || [0, 0]);
                desembolso = alq;
                anual = alq;
                detalle = 'Alquiler por ' + (item.alquiler_unidad || 'temporada') + '.';
            }

            partidas.push({
                id: item.id,
                label: item.label,
                grupo: 'material',
                situacion: situacion,
                opcion: opcion.label,
                desembolso: desembolso,
                anual: anual,
                detalle: detalle,
                nota: item.nota || '',
                fuente: item.fuente,
                fecha: item.fecha,
                fiabilidad: item.fiabilidad
            });
        });

        return partidas;
    }

    /* ----------------------------------------------------- 2. consumibles */

    function calcularConsumibles(input, data) {
        var c = data.consumibles;
        var v = input.volumen;
        var partidas = [];

        // Zapatillas de entrenamiento: las marca el kilometraje y el peso.
        var vidaKm = c.zapatillas.vida_km;
        var pen = c.zapatillas.penalizacion_peso;
        if (pen && v.peso >= pen.desde_kg) vidaKm = vidaKm * pen.factor;
        var vidaRango = c.zapatillas.vida_km_rango || [vidaKm, vidaKm];
        var paresMin = v.km_run / Math.max(1, vidaRango[1]);
        var paresMax = v.km_run / Math.max(1, vidaRango[0]);
        var paresCentral = v.km_run / Math.max(1, vidaKm);
        var precioZap = tri(c.zapatillas.precio);
        partidas.push({
            id: 'zapatillas',
            label: c.zapatillas.label,
            grupo: 'consumibles',
            desembolso: { min: paresMin * precioZap.min, central: paresCentral * precioZap.central, max: paresMax * precioZap.max },
            anual: { min: paresMin * precioZap.min, central: paresCentral * precioZap.central, max: paresMax * precioZap.max },
            detalle: Math.round(paresCentral * 10) / 10 + ' pares al año: ' + v.km_run +
                ' km de carrera y ' + Math.round(vidaKm) + ' km de vida por par' +
                (pen && v.peso >= pen.desde_kg ? ' (ajustado por peso)' : '') + '.',
            fuente: c.zapatillas.fuente,
            fecha: c.zapatillas.fecha,
            fiabilidad: c.zapatillas.fiabilidad
        });

        // Zapatillas de competición, solo si las usa.
        if (input.consumibles && input.consumibles.zapatillas_carrera) {
            var precioComp = tri(c.zapatillas_carrera.precio);
            partidas.push({
                id: 'zapatillas_carrera',
                label: c.zapatillas_carrera.label,
                grupo: 'consumibles',
                desembolso: precioComp,
                anual: precioComp,
                detalle: 'Un par por temporada: se agotan en torno a los ' + c.zapatillas_carrera.vida_km + ' km.',
                fuente: c.zapatillas_carrera.fuente,
                fecha: c.zapatillas_carrera.fecha,
                fiabilidad: c.zapatillas_carrera.fiabilidad
            });
        }

        // Transmisión y cubiertas: por kilómetro de bici.
        var cadenasCentral = v.km_bici / c.cadena.vida_km;
        var cadenaRango = c.cadena.vida_km_rango || [c.cadena.vida_km, c.cadena.vida_km];
        var precioCadena = tri(c.cadena.precio);
        var cadenaCoste = {
            min: (v.km_bici / cadenaRango[1]) * precioCadena.min,
            central: cadenasCentral * precioCadena.central,
            max: (v.km_bici / cadenaRango[0]) * precioCadena.max
        };
        var precioCassette = tri(c.cassette.precio);
        var cassetteCoste = escala(precioCassette, cadenasCentral / c.cassette.cada_n_cadenas);
        var cubRango = c.cubiertas.vida_km_rango || [c.cubiertas.vida_km, c.cubiertas.vida_km];
        var precioCub = escala(tri(c.cubiertas.precio), c.cubiertas.unidades);
        var cubCoste = {
            min: (v.km_bici / cubRango[1]) * precioCub.min,
            central: (v.km_bici / c.cubiertas.vida_km) * precioCub.central,
            max: (v.km_bici / cubRango[0]) * precioCub.max
        };
        var fungibles = tri(c.fungibles_bici.precio_anual);
        var taller = escala(tri(c.taller.precio), c.taller.veces_anio);

        partidas.push({
            id: 'transmision',
            label: 'Transmisión, cubiertas y fungibles de la bici',
            grupo: 'consumibles',
            desembolso: suma(cadenaCoste, cassetteCoste, cubCoste, fungibles),
            anual: suma(cadenaCoste, cassetteCoste, cubCoste, fungibles),
            detalle: Math.round(cadenasCentral * 10) / 10 + ' cadenas y ' +
                (Math.round((v.km_bici / c.cubiertas.vida_km) * 10) / 10) + ' juegos de cubiertas para ' +
                v.km_bici + ' km de bici al año.',
            fuente: c.cadena.fuente + ' ' + c.cubiertas.fuente,
            fecha: c.cadena.fecha,
            fiabilidad: 'contrastado'
        });

        partidas.push({
            id: 'taller',
            label: c.taller.label,
            grupo: 'consumibles',
            desembolso: taller,
            anual: taller,
            detalle: c.taller.veces_anio + ' revisiones al año. ' + (c.taller.nota || ''),
            fuente: c.taller.fuente,
            fecha: c.taller.fecha,
            fiabilidad: c.taller.fiabilidad
        });

        // Nutrición: gramos de hidratos por hora de sesión larga + día de carrera.
        var n = c.nutricion;
        var tipo = (input.consumibles && input.consumibles.nutricion_tipo) || 'mixto';
        var precioG = tri(n.precio_g[tipo] || n.precio_g.mixto);
        var horasLargasAnio = v.horas_largas_semana * input.prueba.semanas;
        var gramosRango = n.g_hidratos_hora_rango || [n.g_hidratos_hora, n.g_hidratos_hora];
        var gramosEntreno = {
            min: horasLargasAnio * gramosRango[0],
            central: horasLargasAnio * n.g_hidratos_hora,
            max: horasLargasAnio * gramosRango[1]
        };
        var horasCarrera = input.prueba.horas_carrera;
        var gramosCarrera = {
            min: horasCarrera * gramosRango[0],
            central: horasCarrera * n.g_hidratos_hora,
            max: horasCarrera * gramosRango[1]
        };
        var nutriEntreno = {
            min: gramosEntreno.min * precioG.min,
            central: gramosEntreno.central * precioG.central,
            max: gramosEntreno.max * precioG.max
        };
        var nutriCarrera = {
            min: gramosCarrera.min * precioG.min,
            central: gramosCarrera.central * precioG.central,
            max: gramosCarrera.max * precioG.max
        };

        partidas.push({
            id: 'nutricion',
            label: n.label + ' (entrenos)',
            grupo: 'consumibles',
            desembolso: nutriEntreno,
            anual: nutriEntreno,
            detalle: Math.round(horasLargasAnio) + ' horas de sesión larga a ' + n.g_hidratos_hora +
                ' g de hidratos por hora. ' + (n.etiquetas[tipo] || ''),
            nota: n.nota,
            fuente: n.fuente,
            fecha: n.fecha,
            fiabilidad: n.fiabilidad
        });

        partidas.push({
            id: 'nutricion_carrera',
            label: n.label + ' (día de carrera)',
            grupo: 'evento',
            desembolso: nutriCarrera,
            anual: nutriCarrera,
            detalle: 'Unas ' + horasCarrera + ' horas de carrera a ' + n.g_hidratos_hora + ' g/h.',
            fuente: n.fuente,
            fecha: n.fecha,
            fiabilidad: n.fiabilidad
        });

        // Piscina.
        var nat = c.natacion;
        var meses = input.prueba.semanas / 4.33;
        var costeNat;
        var detalleNat;
        if (input.recurrentes && input.recurrentes.natacion_modo === 'entrada') {
            var sesiones = (input.recurrentes.sesiones_natacion_semana || 2) * input.prueba.semanas;
            costeNat = escala(tri(nat.entrada), sesiones);
            detalleNat = Math.round(sesiones) + ' entradas de piscina.';
        } else if (input.recurrentes && input.recurrentes.natacion_modo === 'ninguno') {
            costeNat = CERO;
            detalleNat = 'Sin gasto de piscina.';
        } else {
            costeNat = escala(tri(nat.abono_mes), meses);
            detalleNat = Math.round(meses) + ' meses de abono.';
        }
        var fungibleNat = tri(nat.fungible_anual);
        partidas.push({
            id: 'natacion',
            label: nat.label,
            grupo: 'entreno',
            desembolso: suma(costeNat, fungibleNat),
            anual: suma(costeNat, fungibleNat),
            detalle: detalleNat + ' ' + nat.fungible_nota,
            fuente: nat.fuente,
            fecha: nat.fecha,
            fiabilidad: nat.fiabilidad
        });

        return partidas;
    }

    /* ----------------------------------------------------- 3. recurrentes */

    function calcularRecurrentes(input, data) {
        var r = data.recurrentes;
        var inp = input.recurrentes || {};
        var meses = input.prueba.semanas / 4.33;
        var partidas = [];

        if (inp.entrenador !== 'no') {
            var tarifa = inp.entrenador === 'premium' ? tri(r.entrenador.mes_premium) : tri(r.entrenador.mes);
            partidas.push({
                id: 'entrenador',
                label: r.entrenador.label,
                grupo: 'entreno',
                desembolso: escala(tarifa, meses),
                anual: escala(tarifa, meses),
                detalle: Math.round(meses) + ' meses de cuota durante la preparación.',
                fuente: r.entrenador.fuente,
                fecha: r.entrenador.fecha,
                fiabilidad: r.entrenador.fiabilidad
            });
        }

        var sesionesFisio = inp.fisio_sesiones !== undefined ? inp.fisio_sesiones : r.fisio.sesiones_anio;
        if (sesionesFisio > 0) {
            partidas.push({
                id: 'fisio',
                label: r.fisio.label,
                grupo: 'entreno',
                desembolso: escala(tri(r.fisio.sesion), sesionesFisio),
                anual: escala(tri(r.fisio.sesion), sesionesFisio),
                detalle: sesionesFisio + ' sesiones durante la temporada. Si aparece una lesión, suben.',
                fuente: r.fisio.fuente,
                fecha: r.fisio.fecha,
                fiabilidad: r.fisio.fiabilidad
            });
        }

        if (inp.licencia === 'anual') {
            partidas.push({
                id: 'licencia',
                label: r.licencia.label,
                grupo: 'evento',
                desembolso: tri(r.licencia.precio),
                anual: tri(r.licencia.precio),
                detalle: 'Licencia federativa de la temporada.',
                fuente: r.licencia.fuente,
                fecha: r.licencia.fecha,
                fiabilidad: r.licencia.fiabilidad
            });
        } else if (inp.licencia === 'dia') {
            partidas.push({
                id: 'licencia',
                label: r.licencia.alternativa_label,
                grupo: 'evento',
                desembolso: tri(r.licencia.alternativa_dia),
                anual: tri(r.licencia.alternativa_dia),
                detalle: 'El gasto pequeño que casi nadie incluye en sus cuentas.',
                fuente: r.licencia.fuente,
                fecha: r.licencia.fecha,
                fiabilidad: r.licencia.fiabilidad
            });
        }

        if (inp.club) {
            partidas.push({
                id: 'club', label: r.club.label, grupo: 'entreno',
                desembolso: tri(r.club.precio_anual), anual: tri(r.club.precio_anual),
                detalle: 'Cuota anual.', fuente: r.club.fuente, fecha: r.club.fecha, fiabilidad: r.club.fiabilidad
            });
        }

        if (inp.suscripciones) {
            partidas.push({
                id: 'suscripciones', label: r.suscripciones.label, grupo: 'entreno',
                desembolso: escala(tri(r.suscripciones.mes), 12), anual: escala(tri(r.suscripciones.mes), 12),
                detalle: '12 meses de suscripción.', fuente: r.suscripciones.fuente,
                fecha: r.suscripciones.fecha, fiabilidad: r.suscripciones.fiabilidad
            });
        }

        if (inp.seguro_bici) {
            partidas.push({
                id: 'seguro_bici', label: r.seguro_bici.label, grupo: 'material',
                desembolso: tri(r.seguro_bici.precio_anual), anual: tri(r.seguro_bici.precio_anual),
                detalle: 'Prima anual.', fuente: r.seguro_bici.fuente,
                fecha: r.seguro_bici.fecha, fiabilidad: r.seguro_bici.fiabilidad
            });
        }

        return partidas;
    }

    /* ------------------------------------------------- 4. prueba y viaje */

    function calcularEvento(input, data) {
        var e = data.evento;
        var p = input.prueba;
        var vj = input.viaje || {};
        var partidas = [];

        // Inscripción: lo que paga el usuario, o la media publicada si no lo sabe.
        var base = p.inscripcion ? fijo(p.inscripcion) : tri(e.inscripcion[p.distancia].precio);
        var gestionPct = (p.gestion_pct !== undefined ? p.gestion_pct : e.inscripcion.gestion_pct) / 100;
        partidas.push({
            id: 'inscripcion',
            label: 'Inscripción',
            grupo: 'evento',
            desembolso: escala(base, 1 + gestionPct),
            anual: escala(base, 1 + gestionPct),
            detalle: p.inscripcion
                ? 'Dorsal de ' + p.inscripcion + ' € más ' + Math.round(gestionPct * 100) + ' % de gastos de gestión.'
                : 'Sin precio concreto: se usa la horquilla publicada para la distancia, más gastos de gestión.',
            nota: e.inscripcion.nota,
            fuente: e.inscripcion.fuente,
            fecha: e.inscripcion.fecha,
            fiabilidad: e.inscripcion.fiabilidad
        });

        // Desplazamiento.
        var v = e.viaje;
        if (vj.modo === 'coche') {
            var km = (vj.km_coche || 0) * 2;
            var coste = fijo(km * v.coche_eur_km + (vj.peajes || 0) * 2);
            partidas.push({
                id: 'desplazamiento', label: 'Desplazamiento en coche', grupo: 'viaje',
                desembolso: coste, anual: coste,
                detalle: km + ' km ida y vuelta a ' + v.coche_eur_km + ' €/km, más peajes.',
                fuente: v.coche_fuente, fecha: v.fecha, fiabilidad: v.fiabilidad
            });
        } else if (vj.modo === 'avion') {
            var billete = vj.billete ? fijo(vj.billete) : tri(v.billete_avion);
            var billetes = escala(billete, 1 + (vj.acompanantes || 0));
            var tasa = escala(vj.tasa_bici ? fijo(vj.tasa_bici) : tri(v.tasa_bici_trayecto), 2);
            partidas.push({
                id: 'desplazamiento', label: 'Vuelos y tasa de la bici', grupo: 'viaje',
                desembolso: suma(billetes, tasa), anual: suma(billetes, tasa),
                detalle: (1 + (vj.acompanantes || 0)) + ' billete(s) ida y vuelta más la bici como equipaje especial en los dos trayectos.',
                fuente: v.tasa_bici_fuente, fecha: v.fecha, fiabilidad: v.fiabilidad
            });
        }

        // Alojamiento.
        var noches = vj.noches !== undefined ? vj.noches : v.noches_defecto;
        if (noches > 0) {
            var precioNoche = vj.precio_noche ? fijo(vj.precio_noche) : tri(v.noche);
            var personas = Math.max(1, vj.personas_hab || 1);
            var alojamiento = escala(precioNoche, noches / personas);
            partidas.push({
                id: 'alojamiento', label: 'Alojamiento', grupo: 'viaje',
                desembolso: alojamiento, anual: alojamiento,
                detalle: noches + ' noches' + (personas > 1 ? ', dividiendo entre ' + personas + ' personas' : '') + '. ' + v.noches_nota,
                fuente: 'Precios de hotel en fin de semana de prueba.', fecha: v.fecha, fiabilidad: v.fiabilidad
            });
        }

        // Dietas y extras.
        var dias = vj.dias !== undefined ? vj.dias : noches + 1;
        if (dias > 0) {
            var dietas = escala(tri(v.dietas_dia), dias * (1 + (vj.acompanantes || 0)));
            partidas.push({
                id: 'dietas', label: 'Comidas del viaje', grupo: 'viaje',
                desembolso: dietas, anual: dietas,
                detalle: dias + ' días' + ((vj.acompanantes || 0) ? ' por ' + (1 + vj.acompanantes) + ' personas' : '') + '.',
                fuente: 'Estimación de gasto diario en destino.', fecha: v.fecha, fiabilidad: v.fiabilidad
            });
        }

        if (vj.extras !== false) {
            var extras = vj.extras_eur ? fijo(vj.extras_eur) : tri(v.extras);
            partidas.push({
                id: 'extras', label: 'Extras del fin de semana', grupo: 'viaje',
                desembolso: extras, anual: extras,
                detalle: v.extras_nota, fuente: 'Estimación.', fecha: v.fecha, fiabilidad: 'estimacion'
            });
        }

        return partidas;
    }

    /* -------------------------------------------------------- 5. agregado */

    function calcular(input, data) {
        data = data || root.ICC_DATA;

        var partidas = []
            .concat(calcularEquipo(input, data))
            .concat(calcularConsumibles(input, data))
            .concat(calcularRecurrentes(input, data))
            .concat(calcularEvento(input, data));

        var desembolso = suma.apply(null, partidas.map(function (p) { return p.desembolso; }));
        var anual = suma.apply(null, partidas.map(function (p) { return p.anual; }));

        var margen = (input.margen_pct !== undefined ? input.margen_pct : data.margen_imprevistos_pct) / 100;
        var desembolsoConMargen = escala(desembolso, 1 + margen);
        var anualConMargen = escala(anual, 1 + margen);

        // Por grupos, sobre el coste anual (el honesto).
        var grupos = {};
        partidas.forEach(function (p) {
            if (!grupos[p.grupo]) grupos[p.grupo] = { desembolso: CERO, anual: CERO };
            grupos[p.grupo] = {
                desembolso: suma(grupos[p.grupo].desembolso, p.desembolso),
                anual: suma(grupos[p.grupo].anual, p.anual)
            };
        });

        var evento = partidas.filter(function (p) { return p.grupo === 'evento' || p.grupo === 'viaje'; });
        var costeEvento = suma.apply(null, evento.map(function (p) { return p.anual; }));
        var inscripcion = partidas.filter(function (p) { return p.id === 'inscripcion'; })[0];

        var horasAnio = input.volumen.horas_semana * input.prueba.semanas;
        var kmTotales = input.volumen.km_bici + input.volumen.km_run + input.volumen.km_swim;

        return {
            partidas: partidas,
            grupos: grupos,
            totales: {
                desembolso: desembolsoConMargen,
                anual: anualConMargen,
                evento: costeEvento,
                margen_pct: margen * 100
            },
            metricas: {
                coste_hora: anualConMargen.central / Math.max(1, horasAnio),
                coste_km: anualConMargen.central / Math.max(1, kmTotales),
                horas_anio: horasAnio,
                km_totales: kmTotales,
                pct_inscripcion: inscripcion ? (inscripcion.anual.central / Math.max(1, anualConMargen.central)) * 100 : 0,
                inscripcion: inscripcion ? inscripcion.anual.central : 0,
                coste_mes: anualConMargen.central / Math.max(1, input.prueba.semanas / 4.33)
            }
        };
    }

    root.ICC_ENGINE = {
        calcular: calcular,
        tri: tri,
        amortizar: amortizar,
        suma: suma
    };
})(typeof window !== 'undefined' ? window : globalThis);
