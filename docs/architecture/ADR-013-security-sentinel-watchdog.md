# ADR-013 – Security Sentinel Watchdog y Hardening Automático

## Estado
Accepted

## Contexto
La superficie de ataque de una aplicación web evoluciona constantemente. Las vulnerabilidades en librerías de terceros (cadena de suministro) y los errores de configuración en el servidor de producción ( hardening) son riesgos críticos que requieren auditoría periódica.

## Decisión
Desplegar un **"Security Sentinel"** que realiza dos tipos de auditorías automatizadas:
1. **Dependency Audit**: Escaneo semanal de vulnerabilidades en el árbol de dependencias (Composer y NPM).
2. **Production Hardening Test**: Suite de tests E2E que intentan acceder a recursos críticos expuestos (.env, .git) y validan cabeceras de seguridad HTTP.

## Consecuencias
**Pros:** Garantía de seguridad frente a vulnerabilidades conocidas, cumplimiento de estándares básicos de OWASP, prevención de fugas de secretos de configuración.  
**Contras:** Requiere mantenimiento de rutas de escaneo si la infraestructura cambia drásticamente.

## Opciones descartadas
- Auditoría manual: Ineficiente y propensa al error humano.
- Dependabot (solo): Útil pero no verifica el hardening real del servidor de producción (archivos expuestos en Hostinger).

## Evidencia en código
- `.github/workflows/security-watchman.yml`
- `tests/Security/hardening-check.spec.js`
