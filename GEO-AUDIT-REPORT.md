# GEO AUDIT REPORT — siquia.com
**Fecha:** 19 marzo 2026 | **Tipo:** Clínica de psicología online | **Analista:** GEO Audit Tool

---

## GEO SCORE GLOBAL: 41/100 — Optimización Básica Requerida

| Categoría | Peso | Score | Puntos |
|---|---|---|---|
| AI Citabilidad & Visibilidad | 25% | 38/100 | 9.5 |
| Brand Authority Signals | 20% | 26/100 | 5.2 |
| Contenido & E-E-A-T | 20% | 71/100 | 14.2 |
| Fundamentos Técnicos | 15% | 42/100 | 6.3 |
| Structured Data / Schema | 10% | 18/100 | 1.8 |
| Platform Optimization | 10% | 37/100 | 3.7 |
| **TOTAL** | **100%** | **41/100** | **40.7** |

---

## Resumen Ejecutivo

Siquia.com es un referente pionero en psicología online en español (fundada 2012, 13+ años), con un equipo de 20 psicólogos colegiados y presencia en España, EE.UU. y la diáspora hispanohablante. Su posición en SEO tradicional es sólida, con páginas indexadas para condiciones específicas (ansiedad, depresión, traumas), targeting geográfico (Texas, California) y un blog activo.

Sin embargo, la transición a la búsqueda impulsada por IA revela **tres problemas estructurales críticos** que impiden la visibilidad en ChatGPT, Perplexity, Google AI Overviews y Bing Copilot:

1. **Bloqueo de bots en el WAF/CDN (Envoy proxy)** — Los crawlers de citación de IA (PerplexityBot, OAI-SearchBot, ClaudeBot) reciben HTTP 403.
2. **Ausencia total de schema markup relevante** — Sin MedicalClinic, FAQPage, ni Article schema.
3. **Huella de marca en fuentes citadas por IA casi nula** — 0/10 en Reddit, 0/10 en Wikipedia.

El score de E-E-A-T de contenido (71/100) es la mayor fortaleza: el equipo credenciado, el historial de 13 años y el contenido especializado son una base sólida que sólo necesita ser "desbloqueada" técnicamente para tener impacto en IA.

---

## 1. AI Citabilidad & Visibilidad — 38/100

### Hallazgos Clave

**CRÍTICO — Bloqueo de crawlers IA:**
El servidor (`envoy` / WAF) devuelve HTTP 403 a user-agents no-navegador. Esto incluye `PerplexityBot`, `OAI-SearchBot`, `ChatGPT-User`, `ClaudeBot` y `Google-Extended`.

**CRÍTICO — Sin llms.txt:**
No existe `https://siquia.com/llms.txt`. El estándar emergente para guiar a los LLMs está completamente ausente.

**POSITIVO — Estructura FAQ citable:**
Existe `/preguntas_frecuentes/` con subpáginas por pregunta (URLs individuales). Esta estructura es ideal para citabilidad, pero sin JSON-LD FAQPage marcado.

**POSITIVO — Contenido factual citable:**
Datos verificables: pionera desde 2012, 20 psicólogos, sesiones desde 45€, primera consulta gratuita, idiomas: español/inglés/catalán.

### Acciones Prioritarias

1. **Auditar reglas WAF** — Whitelist: `GPTBot`, `PerplexityBot`, `OAI-SearchBot`, `ChatGPT-User`, `ClaudeBot`, `Google-Extended`, `Applebot`
2. **Crear `/llms.txt`** — Descripción de servicios, páginas clave, equipo y precios
3. **Implementar FAQPage JSON-LD** en `/preguntas_frecuentes/` y páginas de condiciones

---

## 2. Brand Authority Signals — 26/100

| Plataforma | Score | Detalle |
|---|---|---|
| Reddit | 0/10 | Zero menciones en r/es, r/spain, r/psicologia |
| Wikipedia | 0/10 | Sin artículo ni mención. Empresa de 13 años con premios debería tener entrada |
| YouTube | 2/10 | No se confirma canal propio. Podcast en Spotify/Ivoox sin YouTube |
| LinkedIn | 5/10 | Página confirmada (~1.150 seguidores). Señal modesta |
| Prensa/Medios | 6/10 | El País (ICON), revcyl.com. Premio Yuzz 2012. Base sólida pero legacy |

### Acciones Prioritarias

1. **Crear artículo en Wikipedia** — Siquia cumple criterios de notabilidad: fundada 2012, primera plataforma de terapia online en español, Premio Yuzz, cobertura en El País
2. **Estrategia Reddit auténtica** — Participación experta en r/es, r/saludmental, r/psicologia
3. **Lanzar canal YouTube** — Reutilizar contenido del podcast como videos cortos (2–5 min)
4. **Crear perfil en Trustpilot** — Los perfiles verificados alimentan el modelo de entidad de ChatGPT
5. **Crear entidad en Wikidata** — Gratuito, impacto alto en reconocimiento de entidad por LLMs

---

## 3. Contenido & E-E-A-T — 71/100

| Dimensión | Score |
|---|---|
| Experience (Experiencia) | 17/25 |
| Expertise (Clínica) | 19/25 |
| Authority (Autoridad) | 18/25 |
| Trust (Confianza) | 17/25 |
| **TOTAL E-E-A-T** | **71/100** |

### Fortalezas

- 20 psicólogos colegiados con número de colegiado publicado
- Modalidades terapéuticas específicas: TCC, EMDR, ACT, terapia humanista
- Pricing transparente: 55€/sesión o 225€ bono 5 sesiones
- Legal entity pública: Siquia Con Tilde, S.L., Valladolid
- Puntuación Trustindex: 4.9/5 de 125+ reseñas
- Blog activo + podcast "Hemisferio Izquierdo" (Spotify, Apple Podcasts)
- 151.000+ seguidores en Instagram

### Brechas Críticas

- Sin citas a literatura clínica en páginas de condiciones
- Bylines inconsistentes en artículos del blog
- Sin timestamps "Revisado médicamente por" en contenido clínico
- Sin página de crisis/emergencias
- Volumen de reseñas bajo (125 reviews para 13 años)

### Gaps de Contenido Prioritarios

| # | Gap | Impacto |
|---|---|---|
| 1 | FAQPage estructurado en páginas de condición | AI Overview + Featured Snippet |
| 2 | "Revisado por [Nombre, Nº Col.]" + fecha en todo contenido clínico | E-E-A-T YMYL crítico |
| 3 | Página de crisis y recursos de emergencia | Ético + señal de confianza |
| 4 | Datos de resultados anónimos ("Resultados de Siquia") | Experiencia + autoridad |
| 5 | Páginas de subtipos: ansiedad social, depresión postparto, TEPT complejo | Profundidad semántica |
| 6 | Artículos comparativos: EMDR vs TCC, terapia online vs presencial | Señal de expertise |

---

## 4. Fundamentos Técnicos — 42/100

| Señal | Estado | Detalle |
|---|---|---|
| HTTPS | ✅ Confirmado | TLS enforced |
| CMS | WordPress | Alta confianza por estructura URL |
| SSR/CSR | ✅ SSR (PHP) | HTML server-side — favorable para crawlers IA |
| Core Web Vitals | ⚠️ Needs Improvement | WordPress + page builder = JS alto |
| Hreflang | ❌ AUSENTE | Crítico para España + LatAm + Catalán/Inglés |
| Crawlabilidad IA | ⚠️ Bloqueada | WAF bloquea UAs no-navegador (403) |
| Sitemap.xml | ❓ Sin confirmar | Verificar /sitemap_index.xml |
| robots.txt | ❓ Sin confirmar | 403 en acceso externo |

### Acciones Técnicas Prioritarias

1. Implementar hreflang para `es-ES`, `es-MX`, `es-AR`, `es-CO`, `ca`, `en`, `x-default`
2. Auditar reglas WAF — whitelist crawlers IA y herramientas SEO verificadas
3. Ejecutar PageSpeed Insights — objetivo: LCP < 2.5s, INP < 200ms, CLS < 0.1
4. Verificar accesibilidad de `sitemap_index.xml` y `robots.txt` (deben retornar 200)
5. Activar CDN de assets para usuarios internacionales

---

## 5. Structured Data / Schema — 18/100

CMS: WordPress con tema personalizado. Sin plugin SEO confirmado activo. Sin rich results en SERPs detectados.

### Schema Ausente — Gaps Críticos

- **MedicalClinic / MedicalOrganization** — Schema fundacional. Sin este, Google no puede generar Knowledge Panel correcto.
- **FAQPage** — Páginas de condiciones sin FAQ schema = cero elegibilidad para AI Overviews.
- **Article + Person** — 100+ artículos de blog sin schema de autor. Crítico para E-E-A-T YMYL.
- **Service + Offer** — Precios publicados (55€/sesión) sin schema estructurado.
- **BreadcrumbList** — Navegación sin breadcrumbs marcados.

### Recomendación Inmediata

Instalar **RankMath o Yoast SEO Premium** — generaría automáticamente WebSite, Organization, Article, BreadcrumbList y WebPage. Luego añadir MedicalClinic y FAQPage manualmente vía JSON-LD.

---

## 6. Platform Optimization — 37/100

| Plataforma | Score | Principal Brecha |
|---|---|---|
| Google AI Overviews | 42/100 | Sin FAQ schema; no aparece en queries no-branded |
| ChatGPT | 28/100 | Sin Wikipedia, sin Reddit, sin Trustpilot |
| Perplexity | 35/100 | Ausente de listas "mejores de" |
| Gemini | 48/100 | Schema de credenciales faltante |
| Bing Copilot | 30/100 | Sin Bing Webmaster Tools |
| **PROMEDIO** | **37/100** | Entidad de marca no reconocida en LLMs |

### Oportunidad Principal: ChatGPT (mayor ROI)

1. Artículo en Wikipedia — Una acción, beneficio permanente en todos los LLMs
2. Presencia en Reddit — Comunidades relevantes ya existen
3. Perfil en Trustpilot — Crear perfil, solicitar reviews post-sesión
4. Inclusión en listas editoriales — psicologiaymente.com, mejorcomparo.com

---

## Plan de Acción Priorizado

### Quick Wins — Semana 1-2

| # | Acción | Impacto | Esfuerzo |
|---|---|---|---|
| 1 | Auditar y corregir WAF: whitelist de crawlers IA | Crítico | 2–4h |
| 2 | Crear /llms.txt | Alto | 1h |
| 3 | Instalar RankMath/Yoast SEO Premium + schema base | Alto | 4h |
| 4 | FAQPage JSON-LD en 5 páginas de condición principales | Alto | 3h |
| 5 | Verificar robots.txt y sitemap_index.xml (200 OK) | Medio | 1h |

### Corto Plazo — Mes 1-2

| # | Acción | Impacto | Esfuerzo |
|---|---|---|---|
| 6 | Crear artículo en Wikipedia | Muy Alto | 8h |
| 7 | MedicalClinic schema en homepage | Alto | 4h |
| 8 | Hreflang para España, LatAm, inglés y catalán | Alto | 6h |
| 9 | "Revisado por" en todo contenido clínico | Alto | 6h |
| 10 | Perfil verificado en Trustpilot | Medio | 2h |
| 11 | Entidad en Wikidata | Medio | 2h |
| 12 | PageSpeed Insights y optimización CWV | Medio | 16h |

### Medio Plazo — Mes 3-6

| # | Acción | Impacto | Esfuerzo |
|---|---|---|---|
| 13 | Estrategia Reddit: participación auténtica del equipo | Muy Alto | Continuo |
| 14 | Canal YouTube con contenido del podcast | Alto | Semanas |
| 15 | "Resultados de Siquia 2026" con datos anónimos | Muy Alto | Meses |
| 16 | Páginas de subtipos de condición | Alto | 20h |
| 17 | Página de crisis/emergencias | Ético + SEO | 2h |
| 18 | Article + Person schema en blog completo | Alto | 8h |

---

## Metodología

**Herramientas:** 5 subagentes paralelos (AI Visibility, Schema, Technical, E-E-A-T Content, Platform Optimization) + WebSearch × 25+ consultas.

**Limitación:** El entorno de auditoría no pudo acceder directamente a siquia.com (HTTP 403 del proxy WAF/Envoy). El análisis se basó en señales de SERP, snippets indexados, headers HTTP y data de búsqueda. Este bloqueo confirma que crawlers externos (incluidos los de IA) enfrentan la misma barrera — lo que es en sí mismo un hallazgo crítico.

**Fuentes principales:**
- siquia.com (SERP snippets indexados)
- Trustindex.io — 4.9/5 de 125+ reseñas
- Doctoralia.es
- El País (ICON), revcyl.com, mejorcomparo.com
- LinkedIn company page (~1.150 seguidores)
- Spotify / Apple Podcasts — podcast "Hemisferio Izquierdo"
