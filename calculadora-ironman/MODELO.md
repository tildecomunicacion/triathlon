# Cómo calcula la calculadora de coste real IRONMAN

Este documento es el contrato de la calculadora: qué suma, con qué supuestos y
de dónde sale cada número. Si cambias un precio, cámbialo en
`assets/js/icc-data.js` (o desde el admin) y actualiza aquí la fuente y la fecha.

La calculadora **no da una cifra cerrada**, y es deliberado: devuelve siempre
una horquilla (mínimo – central – máximo). Los precios cambian cada temporada,
de una prueba a otra y según el país.

---

## 1. Las dos cifras que da, y por qué son distintas

| Cifra | Qué es | Para qué sirve |
|---|---|---|
| **Desembolso de la temporada** | El dinero que sale de la cuenta este año: lo que compras, lo que alquilas, lo que gastas y la prueba. | Saber si puedes pagarlo. |
| **Coste real de la temporada** | Lo mismo, pero el material duradero no cuenta su precio entero: cuenta la parte que se gasta este año. | Saber lo que de verdad cuesta hacer esto cada año. |

El material duradero se amortiza así:

```
coste anual = (precio − valor residual) / temporadas de vida útil
```

El **valor residual** es lo que recuperarías vendiéndolo de segunda mano. Una
bici de 5.000 € que dura 7 temporadas y se vende por el 35 % cuesta
`(5000 − 1750) / 7 = 464 €` al año, no 714 €.

De ahí salen tres consecuencias que la calculadora enseña y que la mayoría de
presupuestos se saltan:

1. **Quien ya tiene el material no tiene coste cero.** Si tienes la bici
   pagada, no sale dinero de tu cuenta, pero la bici se gasta: ese año te
   cuesta su parte. Aparece en la columna de coste real con la etiqueta
   «ya lo tienes».
2. **Quien empieza de cero paga mucho más de lo que le cuesta.** Su desembolso
   es alto, pero el coste real del año es bastante menor, porque compra cosas
   que durarán varias temporadas.
3. **Comprar una cabra de gama alta tiene coste todos los años,** aunque la
   pagases hace tres.

Sobre el total se aplica un **margen para imprevistos** (10 % por defecto,
ajustable de 0 a 30 %).

---

## 2. Vida útil y valor residual del material

| Partida | Vida útil | Residual | Base |
|---|---|---|---|
| Bici | 7 temporadas | 35 % | Un cuadro de carbono bien mantenido se cita entre 7 y 10 años; la transmisión se repone antes (va aparte, en consumibles). |
| Acoples | 5 | 20 % | Estimación. |
| Estudio biomecánico | 3 | 0 % | Se rehace al cambiar de bici o de posición. |
| Ruedas de perfil | 6 | 35 % | Estimación. |
| Potenciómetro | 6 | 25 % | Estimación. |
| Casco | 4 | 0 % | Los fabricantes recomiendan reponer cada 3–5 años, y siempre tras un golpe. |
| Pedales + zapatillas de ciclismo | 5 | 10 % | Estimación. |
| Neopreno | 4 | 10 % | Estimación: el cloro, la sal y el sol lo degradan; se habla de varias temporadas con buen cuidado. |
| Mono de competición | 3 | 0 % | Estimación. |
| Rodillo | 6 | 30 % | Estimación. |
| Reloj multideporte | 4 | 15 % | Estimación. |
| Maleta de bici | 8 | 30 % | Estimación. |

Alternativa al alquiler donde tiene sentido (bici, ruedas, neopreno, maleta):
el alquiler cuenta igual en desembolso y en coste real, porque no te queda nada.

---

## 3. Consumibles: los marca el volumen, no el calendario

| Partida | Regla | Base |
|---|---|---|
| Zapatillas de entrenamiento | km de carrera al año ÷ 700 km por par; −15 % de vida por encima de 85 kg | Rango más repetido en fabricantes y tiendas: 500–800 km (Salomon: 500–800; tiendas especializadas: 700–900). El peso acorta la vida. |
| Zapatillas de competición | 1 par por temporada (opcional) | Las de placa de carbono se agotan antes, en torno a 300 km. |
| Cadena | km de bici ÷ 4.000 (horquilla 3.000–6.000) | Rango habitual según lubricación y mantenimiento; lo correcto es medir el desgaste. |
| Cassette y transmisión | 1 cada 3 cadenas | Práctica de taller. |
| Cubiertas | km de bici ÷ 4.500, 2 unidades (horquilla 3.000–6.000) | Las cubiertas de gama alta duran 3.000–6.000 km. |
| Fungibles (cámaras, pastillas, cinta) | 40–110 € al año | Estimación. |
| Revisiones de taller | 2 al año, una antes de la carrera | Estimación de tarifas en España. |
| Nutrición de entrenos | horas de sesión larga × 70 g de hidratos/hora × €/g | Pauta de 60–90 g/h en larga distancia. |
| Nutrición del día de carrera | horas estimadas de carrera × 70 g/h | Igual que arriba. |
| Piscina | abono mensual (30–55 €) o entrada suelta (4,5–8 €) × sesiones | Tarifas de piscinas en España. |

Precio por gramo de hidratos: 0,075–0,11 €/g con producto comercial,
0,05–0,075 €/g mezclando, 0,025–0,045 €/g tirando de comida normal y bebida
preparada en casa. Sale de geles de 25–30 g a 2–3 € y de bebida en polvo a granel.

---

## 4. Recurrentes

| Partida | Valor | Base |
|---|---|---|
| Entrenador | 50–150 €/mes; premium 150–300 €/mes | Entrenamiento online personalizado en España; los planes premium con seguimiento muy cercano llegan a 300 €/mes. |
| Fisioterapia | 35–60 €/sesión, 8 sesiones/temporada por defecto | Estimación de tarifa de sesión deportiva en España. |
| Licencia federativa | 60–130 €/año, o seguro de día 12–30 € | Estimación: varía por federación autonómica. |
| Club | 60–180 €/año | Estimación. |
| Plataformas (TrainingPeaks, Zwift…) | 10–25 €/mes | Tarifas públicas. |
| Seguro de la bici | 60–220 €/año | Estimación para bicis de 2.000–8.000 €. |

Los meses de cuota se calculan con las semanas de preparación que indique el
usuario (28 por defecto en completo, 20 en medio), no con el año entero.

---

## 5. La prueba y el viaje

- **Inscripción.** Si el usuario sabe el precio, se usa el suyo. Si no, la
  horquilla publicada: 550–900 € en un IRONMAN completo europeo (media en torno
  a 650 € para 2026) y 280–480 € en un medio. Encima se suma un **9 % de gastos
  de gestión** por defecto. Referencia documentada de cómo sube por tramos:
  IRONMAN Vitoria pasó de 568 € en apertura a 650 € doce días antes de la
  prueba, con un 9,25 % de gastos de gestión.
- **Coche.** 0,26 €/km ida y vuelta, más peajes. El 0,26 €/km es el importe
  por kilómetro exento de tributación en España, que sirve como referencia
  razonable de combustible más desgaste.
- **Avión.** Billete (80–260 € si no lo sabe) más la bici como equipaje
  especial en los **dos** trayectos: Vueling desde 45 €, Iberia 40–50 € en
  corto y medio radio, Ryanair desde 60 € online. Por defecto 45–75 €/trayecto.
- **Alojamiento.** 3 noches por defecto, 90–180 €/noche, divisible entre las
  personas de la habitación. La recogida del dorsal, la entrega de la bici en
  boxes y la reunión técnica obligan a llegar con uno o dos días de margen.
- **Comidas.** 30–50 €/día × días × personas.
- **Extras.** 40–150 €: fotos oficiales, ropa de la prueba, la cena de después,
  la noche de más porque el cuerpo no está para conducir.
- **Acompañantes.** Multiplican billetes y comidas, no el alojamiento (se
  reparte por habitación).

---

## 6. Honestidad de los datos

Cada partida lleva en la tabla de datos su `fuente`, su `fecha` y una etiqueta
de `fiabilidad`, que la interfaz muestra en «Ver supuestos y fuentes»:

- **`contrastado`**: el rango está respaldado por fuentes concretas y
  coincidentes (vida útil de zapatillas, cadena y cubiertas; gramos de hidratos
  por hora; medias de inscripción 2026; tarifas de entrenador online; tasas de
  bici en avión).
- **`estimacion`**: horquilla de mercado o práctica habitual, sin una fuente
  única que la fije (precios de material, vida útil de casi todo el material,
  licencias, fisio, alojamiento, extras).

**Lo que conviene verificar antes de publicar**, porque son estimaciones con
impacto grande en el total:

1. Precio real de las gamas de bici que se quieran ofrecer (se puede resolver
   con los precios de referencia, ver abajo).
2. Licencia federativa de la federación que corresponda y seguro de día de la
   prueba concreta.
3. Precio de inscripción de las pruebas que de verdad vayan a usar los
   lectores, con fecha y tramo.
4. Vida útil del neopreno y del casco: no hay un dato público sólido, solo
   recomendación de fabricante.

---

## 7. Qué hay que revisar cada temporada

La calculadora envejece por donde envejece cualquier artículo con precios. Para
que siga siendo fiable, una vez al año (o cuando cambie algo gordo) hay que
revisar en `src/icc-data.js`:

- las horquillas de precio del material y su `fecha`,
- la media y el rango de inscripción de la distancia,
- las tarifas de avión con bici y el €/km del coche,
- el precio por gramo de hidratos si cambian los geles de referencia.

Cada entrada lleva `fuente`, `fecha` y `fiabilidad`, y eso es lo que sale en
«Ver supuestos y fuentes». Al cambiar un número, cambia también su fecha: es lo
único que sostiene que el resultado sea honesto.

---

## 8. Ficheros

```
index.html                     Página autónoma para probar y compartir
page-calculadora-ironman.php   Plantilla de página de WordPress (wp_head/wp_footer)
build.py                       Genera las dos desde src/
src/shell.html                 Hero, intro y punto de montaje de la calculadora
src/icc-styles.css             Estilos (mismos tokens que la calculadora de sudoración)
src/icc-data.js                TODOS los precios, vidas útiles y consumos, con fuentes
src/icc-engine.js              Motor de cálculo (sin un solo número dentro)
src/icc-ui.js                  Interfaz, montada desde la tabla de datos
MODELO.md                      Este documento
```

Para generar las dos salidas, con el botón de WhatsApp apuntando a un número:

```
python3 build.py --whatsapp 34600000000
```

La plantilla se sube al tema (o al tema hijo), se crea una página y se le
asigna la plantilla «Calculadora Coste IRONMAN», igual que la de sudoración.
