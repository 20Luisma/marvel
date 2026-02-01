# ADR-012 – Monitoreo Continuo Proactivo en Producción

## Estado
Accepted

## Contexto
Tras el despliegue exitoso, la estabilidad del sistema puede verse comprometida por factores externos no detectables en el momento del deploy (caída de APIs de IA, agotamiento de créditos, saturación del hosting compartido). Se requiere una vigilancia recurrente sin intervención manual.

## Decisión
Implementar un sistema de **Monitoreo Sintético Continuo** mediante GitHub Actions (`cron`) y Playwright. El sistema ejecuta el "Filtro Quirúrgico" directamente sobre la URL de producción con una frecuencia configurable (actualmente cada 3 días en Modo Demo).

## Consecuencias
**Pros:** Detección de errores post-despliegue, tranquilidad operativa 24/7, histórico de salud del sistema, alertas inmediatas por email ante fallos en producción.  
**Contras:** Consumo marginal de tokens de IA para las pruebas semánticas, dependencia de la disponibilidad de GitHub Actions.

## Opciones descartadas
- Monitoreo de logs pasivo (Sentry/CloudWatch): detecta errores cuando el usuario ya los ha sufrido. El monitoreo sintético es proactivo.
- Herramientas externas (Pingdom/UptimeRobot): se limitan a chequear el puerto 80/443, no validan la lógica de negocio ni la semántica de la IA.

## Evidencia en código
- `.github/workflows/monitoring.yml`
- `playwright.monitoring.config.cjs`
- `tests/e2e/surgical-production-check.spec.js`
