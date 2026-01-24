# Evidencias verificables - Clean Marvel Album

Este directorio reune evidencias verificables para el TFM. Todo lo listado aqui esta pensado para comprobarse con capturas, logs o salidas de comandos reales.

## Fecha de verificacion
Fecha de verificacion: YYYY-MM-DD (completar cuando se generen las evidencias).

## Enlaces de referencia (rellenar si aplica)
- App (UI): <URL_APP>
- Presentacion TFM: <URL_SLIDES>
- Ultimo run de CI: <URL_CI_RUN>
- SonarCloud: <URL_SONAR>
- Repositorio: <URL_GITHUB_REPO>

## Evidencias a aportar
Guarda las capturas en `docs/evidence/screenshots/` con nombres claros y fecha.

### Capturas sugeridas (UI)
- Home (vista principal).
- Albums: listado y alta de album.
- Heroes: listado y alta de heroe.
- Comic IA: salida generada (si hay servicio IA configurado).
- Comparacion RAG: respuesta de `rag-service` (si esta configurado).
- Paneles: accesibilidad, performance, Sonar/Calidad, heatmap.

### Evidencias tecnicas (logs/salidas)
- PHPUnit: salida corta del resumen.
- Cobertura: `coverage.xml` o salida de `composer test:coverage`.
- CI en verde (captura del workflow en GitHub Actions).
- Pa11y / Lighthouse (si se ejecutan en CI o local).

## Ubicacion de capturas
- Carpeta: `docs/evidence/screenshots/`
- Convencion: `YYYY-MM-DD_nombre-captura.png`

## Notas
- Si no hay acceso a servicios externos (OpenAI, WAVE, PSI), registrar la evidencia como "no disponible" e indicar el motivo (falta de clave o entorno sin red).
- Evitar datos sensibles en capturas (tokens, claves, emails).
