/**
 * Calculadora de coste real IRONMAN — tabla de datos.
 *
 * TODO precio, vida útil y consumo vive aquí. El motor (icc-engine.js) no
 * contiene ni un número: así la calculadora se actualiza cambiando esta tabla
 * (o sobreescribiéndola desde el admin de WordPress) sin tocar la lógica.
 *
 * Cada entrada lleva `fuente` y `fecha`. Lo que no está verificado se marca
 * con `fiabilidad: 'estimacion'` y se muestra como tal en la interfaz.
 *
 * precio: [min, max] en euros. La calculadora usa la media como escenario
 * central y los extremos para la horquilla.
 * vida_temporadas: temporadas de uso antes de reponer.
 * residual: fracción del precio que se recupera vendiéndolo de segunda mano.
 */
(function (root) {
    'use strict';

    var DATA = {
        version: '1.0.0',
        actualizado: '2026-10-18',
        moneda: '€',

        /* ------------------------------------------------------------------
         * 1. BIENES DURADEROS — se amortizan entre temporadas
         * ------------------------------------------------------------------ */
        equipo: [
            {
                id: 'bici',
                label: 'Bici',
                grupo: 'material',
                nota: 'Una carretera con acoples bien ajustada termina un IRONMAN. La cabra ahorra tiempo, no es obligatoria.',
                vida_temporadas: 7,
                residual: 0.35,
                alquiler: [180, 400],
                alquiler_unidad: 'semana de carrera',
                opciones: [
                    { id: 'carretera_2mano', label: 'Carretera + acoples (segunda mano)', precio: [900, 1800] },
                    { id: 'carretera_nueva', label: 'Carretera + acoples (nueva)', precio: [1800, 3500] },
                    { id: 'tt_entrada', label: 'Cabra de contrarreloj, gama entrada', precio: [2800, 4500] },
                    { id: 'tt_media', label: 'Cabra de contrarreloj, gama media', precio: [4500, 7500] },
                    { id: 'tt_alta', label: 'Cabra de contrarreloj, gama alta', precio: [8000, 14000] }
                ],
                defecto: { opcion: 'carretera_nueva', situacion: 'tengo' },
                fuente: 'Horquillas de mercado en tiendas especializadas de España. Vida útil: 7–10 años para un cuadro de carbono bien mantenido.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            {
                id: 'acoples',
                label: 'Acoples (si montas carretera)',
                grupo: 'material',
                vida_temporadas: 5,
                residual: 0.2,
                opciones: [
                    { id: 'basicos', label: 'Acoples básicos', precio: [60, 120] },
                    { id: 'buenos', label: 'Acoples con apoyos regulables', precio: [120, 280] }
                ],
                defecto: { opcion: 'basicos', situacion: 'compro' },
                fuente: 'Precio de catálogo de tiendas especializadas.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            {
                id: 'estudio',
                label: 'Estudio biomecánico',
                grupo: 'material',
                nota: 'La inversión que más rinde en 180 km. Se rehace al cambiar de bici o de posición.',
                vida_temporadas: 3,
                residual: 0,
                opciones: [
                    { id: 'basico', label: 'Estudio básico', precio: [90, 150] },
                    { id: 'completo', label: 'Estudio completo con seguimiento', precio: [150, 300] }
                ],
                defecto: { opcion: 'basico', situacion: 'compro' },
                fuente: 'Tarifas publicadas por centros de biomecánica en España.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            {
                id: 'ruedas',
                label: 'Ruedas de perfil',
                grupo: 'material',
                nota: 'Mejora la experiencia, no decide si terminas.',
                vida_temporadas: 6,
                residual: 0.35,
                alquiler: [80, 180],
                alquiler_unidad: 'fin de semana',
                opciones: [
                    { id: 'aluminio', label: 'Perfil medio aluminio/carbono', precio: [350, 800] },
                    { id: 'carbono', label: 'Carbono de perfil alto', precio: [900, 2500] },
                    { id: 'disco', label: 'Rueda lenticular + delantera', precio: [1500, 3500] }
                ],
                defecto: { opcion: 'aluminio', situacion: 'no_uso' },
                fuente: 'Horquillas de mercado en tiendas especializadas.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            {
                id: 'potenciometro',
                label: 'Potenciómetro',
                grupo: 'material',
                nota: 'Ayuda a entrenar y a dosificar. No hace falta para terminar.',
                vida_temporadas: 6,
                residual: 0.25,
                opciones: [
                    { id: 'unilateral', label: 'Unilateral (una biela)', precio: [300, 550] },
                    { id: 'bilateral', label: 'Bilateral / pedales', precio: [600, 1300] }
                ],
                defecto: { opcion: 'unilateral', situacion: 'no_uso' },
                fuente: 'Horquillas de mercado en tiendas especializadas.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            {
                id: 'casco',
                label: 'Casco',
                grupo: 'material',
                nota: 'Los fabricantes recomiendan reponer cada 3–5 años, y siempre después de un golpe.',
                vida_temporadas: 4,
                residual: 0,
                opciones: [
                    { id: 'ruta', label: 'Casco de ruta', precio: [60, 180] },
                    { id: 'aero', label: 'Casco aero / cronoescalada', precio: [150, 400] }
                ],
                defecto: { opcion: 'ruta', situacion: 'tengo' },
                fuente: 'Recomendación habitual de fabricantes (3–5 años) y precios de catálogo.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            {
                id: 'pedales_zapatillas',
                label: 'Pedales + zapatillas de ciclismo',
                grupo: 'material',
                vida_temporadas: 5,
                residual: 0.1,
                opciones: [
                    { id: 'basico', label: 'Conjunto básico', precio: [120, 250] },
                    { id: 'medio', label: 'Conjunto gama media', precio: [250, 500] }
                ],
                defecto: { opcion: 'basico', situacion: 'tengo' },
                fuente: 'Precios de catálogo de tiendas especializadas.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            {
                id: 'neopreno',
                label: 'Neopreno',
                grupo: 'material',
                nota: 'Obligatorio por debajo de 16 °C de agua, prohibido a partir de 24 °C. Se puede alquilar por temporada.',
                vida_temporadas: 4,
                residual: 0.1,
                alquiler: [60, 140],
                alquiler_unidad: 'temporada',
                opciones: [
                    { id: 'entrada', label: 'Gama entrada', precio: [150, 280] },
                    { id: 'medio', label: 'Gama media', precio: [280, 450] },
                    { id: 'alto', label: 'Gama alta', precio: [450, 800] }
                ],
                defecto: { opcion: 'entrada', situacion: 'compro' },
                fuente: 'Precios de catálogo (Orca, Zone3, Decathlon). Norma de temperatura: reglamento de uso de neopreno en triatlón.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            {
                id: 'mono',
                label: 'Mono de competición (trisuit)',
                grupo: 'material',
                vida_temporadas: 3,
                residual: 0,
                opciones: [
                    { id: 'entrada', label: 'Gama entrada', precio: [70, 140] },
                    { id: 'medio', label: 'Gama media/larga distancia', precio: [140, 300] }
                ],
                defecto: { opcion: 'entrada', situacion: 'compro' },
                fuente: 'Precios de catálogo de tiendas especializadas.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            {
                id: 'rodillo',
                label: 'Rodillo',
                grupo: 'material',
                nota: 'Opcional, pero quien entrena en invierno o con poca luz lo acaba usando.',
                vida_temporadas: 6,
                residual: 0.3,
                opciones: [
                    { id: 'simple', label: 'Rodillo de rodillos / básico', precio: [120, 300] },
                    { id: 'interactivo', label: 'Rodillo interactivo (transmisión directa)', precio: [450, 1400] }
                ],
                defecto: { opcion: 'interactivo', situacion: 'no_uso' },
                fuente: 'Horquillas de mercado en tiendas especializadas.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            {
                id: 'reloj',
                label: 'Reloj multideporte',
                grupo: 'material',
                vida_temporadas: 4,
                residual: 0.15,
                opciones: [
                    { id: 'entrada', label: 'Gama entrada con multideporte', precio: [200, 350] },
                    { id: 'medio', label: 'Gama media', precio: [350, 600] },
                    { id: 'alto', label: 'Gama alta / autonomía larga', precio: [600, 1000] }
                ],
                defecto: { opcion: 'entrada', situacion: 'tengo' },
                fuente: 'Precios de catálogo (Garmin, Polar, Coros, Suunto).',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            {
                id: 'maleta',
                label: 'Maleta o caja de bici',
                grupo: 'material',
                nota: 'Solo si viajas en avión. Alquilarla suele salir mejor para una sola prueba.',
                vida_temporadas: 8,
                residual: 0.3,
                alquiler: [40, 90],
                alquiler_unidad: 'viaje',
                opciones: [
                    { id: 'blanda', label: 'Funda blanda', precio: [120, 280] },
                    { id: 'rigida', label: 'Maleta rígida', precio: [300, 700] }
                ],
                defecto: { opcion: 'blanda', situacion: 'no_uso' },
                fuente: 'Precios de catálogo y tarifas de alquiler de tiendas de triatlón.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            }
        ],

        /* ------------------------------------------------------------------
         * 2. CONSUMIBLES — el gasto lo marca el volumen de entrenamiento
         * ------------------------------------------------------------------ */
        consumibles: {
            zapatillas: {
                label: 'Zapatillas de entrenamiento',
                precio: [90, 170],
                vida_km: 700,
                vida_km_rango: [500, 800],
                penalizacion_peso: { desde_kg: 85, factor: 0.85 },
                fuente: 'Rango más repetido en fabricantes y tiendas: 500–800 km. Salomon: 500–800 km; tiendas especializadas: 700–900 km. El peso del corredor acorta la vida útil.',
                fecha: '2026-10',
                fiabilidad: 'contrastado'
            },
            zapatillas_carrera: {
                label: 'Zapatillas de competición (placa de carbono)',
                precio: [200, 290],
                vida_km: 350,
                fuente: 'Las zapatillas con placa de carbono se agotan antes; se citan cifras en torno a 300 km.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            cadena: {
                label: 'Cadena',
                precio: [25, 45],
                vida_km: 4000,
                vida_km_rango: [3000, 6000],
                fuente: 'Rango habitual 3.000–6.000 km según lubricación y mantenimiento; conviene medir el desgaste con un calibre.',
                fecha: '2026-10',
                fiabilidad: 'contrastado'
            },
            cassette: {
                label: 'Cassette y transmisión',
                precio: [50, 140],
                cada_n_cadenas: 3,
                fuente: 'Práctica de taller habitual: un cassette cada 2–3 cadenas.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            cubiertas: {
                label: 'Cubiertas',
                precio: [45, 85],
                unidades: 2,
                vida_km: 4500,
                vida_km_rango: [3000, 6000],
                fuente: 'Cubiertas de gama alta: 3.000–6.000 km.',
                fecha: '2026-10',
                fiabilidad: 'contrastado'
            },
            fungibles_bici: {
                label: 'Cámaras, pastillas, cinta de manillar, cables',
                precio_anual: [40, 110],
                fuente: 'Gasto anual estimado de taller.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            taller: {
                label: 'Revisiones de taller',
                precio: [60, 150],
                veces_anio: 2,
                nota: 'Una de ellas, la puesta a punto antes de la carrera.',
                fuente: 'Tarifas de puesta a punto en talleres de España.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            nutricion: {
                label: 'Nutrición deportiva',
                g_hidratos_hora: 70,
                g_hidratos_hora_rango: [60, 90],
                precio_g: { comercial: [0.075, 0.11], mixto: [0.05, 0.075], casero: [0.025, 0.045] },
                etiquetas: {
                    comercial: 'Solo geles, barritas y bebida de marca',
                    mixto: 'Mezcla de producto comercial y comida normal',
                    casero: 'Sobre todo comida normal y bebida preparada en casa'
                },
                nota: 'Se consume en los entrenos largos, no solo el día de la carrera. Y hay que probarla entrenando.',
                fuente: 'Pauta de 60–90 g de hidratos por hora en larga distancia; precio por gramo calculado sobre geles de 25–30 g a 2–3 € y bebidas en polvo a granel.',
                fecha: '2026-10',
                fiabilidad: 'contrastado'
            },
            natacion: {
                label: 'Piscina y material de natación',
                abono_mes: [30, 55],
                entrada: [4.5, 8],
                fungible_anual: [40, 90],
                fungible_nota: 'Gafas, gorros y bañadores que se gastan con el cloro.',
                fuente: 'Tarifas de piscinas municipales y privadas en España.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            }
        },

        /* ------------------------------------------------------------------
         * 3. RECURRENTES — cada mes de preparación tiene su factura
         * ------------------------------------------------------------------ */
        recurrentes: {
            entrenador: {
                label: 'Entrenador',
                mes: [50, 150],
                mes_premium: [150, 300],
                fuente: 'Entrenamiento online personalizado en España: 50–150 €/mes; planes premium con seguimiento muy cercano hasta 300 €/mes.',
                fecha: '2026-10',
                fiabilidad: 'contrastado'
            },
            fisio: {
                label: 'Fisioterapia',
                sesion: [35, 60],
                sesiones_anio: 8,
                fuente: 'Tarifa media de sesión de fisioterapia deportiva en España.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            licencia: {
                label: 'Licencia federativa anual',
                precio: [60, 130],
                alternativa_dia: [12, 30],
                alternativa_label: 'Seguro de un día (si no tienes licencia)',
                fuente: 'Horquilla de licencias de federaciones autonómicas; el seguro de día lo cobra la organización.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            club: {
                label: 'Cuota de club',
                precio_anual: [60, 180],
                fuente: 'Cuotas habituales de clubes de triatlón.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            suscripciones: {
                label: 'Plataformas de entrenamiento (TrainingPeaks, Zwift, etc.)',
                mes: [10, 25],
                fuente: 'Tarifas públicas de las plataformas.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            },
            seguro_bici: {
                label: 'Seguro de la bici',
                precio_anual: [60, 220],
                fuente: 'Primas habituales para bicis de 2.000–8.000 €.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            }
        },

        /* ------------------------------------------------------------------
         * 4. LA PRUEBA Y EL VIAJE
         * ------------------------------------------------------------------ */
        evento: {
            inscripcion: {
                full: { precio: [550, 900], central: 650 },
                medio: { precio: [280, 480], central: 380 },
                gestion_pct: 9,
                nota: 'El dorsal sube por tramos a medida que se llena el cupo: apuntarse pronto es la única forma de pagar el precio bajo.',
                fuente: 'Medias publicadas para la temporada 2026: ~650 € de media en un IRONMAN completo y 550–900 € en las pruebas europeas, más gastos de gestión. Referencia documentada de tramos: IRONMAN Vitoria pasó de 568 € en apertura a 650 € doce días antes, con un 9,25 % de gastos de gestión.',
                fecha: '2026-10',
                fiabilidad: 'contrastado'
            },
            viaje: {
                coche_eur_km: 0.26,
                coche_fuente: 'Importe por kilómetro exento de tributación en España (0,26 €/km), usado como referencia de coste real de combustible y desgaste.',
                billete_avion: [80, 260],
                tasa_bici_trayecto: [45, 75],
                tasa_bici_fuente: 'Tarifas por trayecto de equipaje especial: Vueling desde 45 €, Iberia 40–50 € en corto y medio radio, Ryanair desde 60 € online.',
                noche: [90, 180],
                noches_defecto: 3,
                noches_nota: 'La recogida del dorsal, la entrega de la bici en boxes y la reunión técnica obligan a llegar con uno o dos días de margen.',
                dietas_dia: [30, 50],
                extras: [40, 150],
                extras_nota: 'Fotos oficiales, ropa de la prueba, la cena de después o una noche más porque el cuerpo no está para conducir.',
                fecha: '2026-10',
                fiabilidad: 'estimacion'
            }
        },

        /* ------------------------------------------------------------------
         * 5. VOLUMEN DE TEMPORADA (valores por defecto del preparatorio)
         * ------------------------------------------------------------------ */
        volumen: {
            full: { semanas: 28, horas_semana: 11, km_bici: 6000, km_run: 1400, km_swim: 200, horas_largas_semana: 5 },
            medio: { semanas: 20, horas_semana: 8, km_bici: 3800, km_run: 1100, km_swim: 150, horas_largas_semana: 3 }
        },

        /* ------------------------------------------------------------------
         * 6. PERFILES — prerrellenan la situación del material
         * ------------------------------------------------------------------ */
        perfiles: {
            cero: {
                label: 'Empiezo de cero',
                descripcion: 'Primer IRONMAN y casi todo por comprar.',
                material: {
                    bici: { opcion: 'carretera_nueva', situacion: 'compro' },
                    acoples: { opcion: 'basicos', situacion: 'compro' },
                    estudio: { opcion: 'basico', situacion: 'compro' },
                    ruedas: { situacion: 'no_uso' },
                    potenciometro: { situacion: 'no_uso' },
                    casco: { opcion: 'ruta', situacion: 'compro' },
                    pedales_zapatillas: { opcion: 'basico', situacion: 'compro' },
                    neopreno: { opcion: 'entrada', situacion: 'compro' },
                    mono: { opcion: 'entrada', situacion: 'compro' },
                    rodillo: { opcion: 'interactivo', situacion: 'no_uso' },
                    reloj: { opcion: 'entrada', situacion: 'compro' },
                    maleta: { situacion: 'no_uso' }
                }
            },
            triatleta: {
                label: 'Ya hago triatlón',
                descripcion: 'Vengo de distancias cortas y subo a larga.',
                material: {
                    bici: { opcion: 'carretera_nueva', situacion: 'tengo' },
                    acoples: { opcion: 'basicos', situacion: 'compro' },
                    estudio: { opcion: 'basico', situacion: 'compro' },
                    ruedas: { situacion: 'no_uso' },
                    potenciometro: { opcion: 'unilateral', situacion: 'no_uso' },
                    casco: { opcion: 'ruta', situacion: 'tengo' },
                    pedales_zapatillas: { opcion: 'basico', situacion: 'tengo' },
                    neopreno: { opcion: 'entrada', situacion: 'tengo' },
                    mono: { opcion: 'entrada', situacion: 'compro' },
                    rodillo: { opcion: 'interactivo', situacion: 'tengo' },
                    reloj: { opcion: 'entrada', situacion: 'tengo' },
                    maleta: { opcion: 'blanda', situacion: 'no_uso' }
                }
            },
            veterano: {
                label: 'Lo tengo casi todo',
                descripcion: 'No es mi primer larga distancia.',
                material: {
                    bici: { opcion: 'tt_media', situacion: 'tengo' },
                    acoples: { situacion: 'no_uso' },
                    estudio: { opcion: 'basico', situacion: 'tengo' },
                    ruedas: { opcion: 'carbono', situacion: 'tengo' },
                    potenciometro: { opcion: 'unilateral', situacion: 'tengo' },
                    casco: { opcion: 'aero', situacion: 'tengo' },
                    pedales_zapatillas: { opcion: 'medio', situacion: 'tengo' },
                    neopreno: { opcion: 'medio', situacion: 'tengo' },
                    mono: { opcion: 'medio', situacion: 'tengo' },
                    rodillo: { opcion: 'interactivo', situacion: 'tengo' },
                    reloj: { opcion: 'medio', situacion: 'tengo' },
                    maleta: { opcion: 'rigida', situacion: 'tengo' }
                }
            }
        },

        situaciones: [
            { id: 'tengo', label: 'Ya lo tengo' },
            { id: 'compro', label: 'Lo compro' },
            { id: 'alquilo', label: 'Lo alquilo' },
            { id: 'no_uso', label: 'No lo uso' }
        ],

        margen_imprevistos_pct: 10
    };

    root.ICC_DATA = DATA;
})(typeof window !== 'undefined' ? window : globalThis);
