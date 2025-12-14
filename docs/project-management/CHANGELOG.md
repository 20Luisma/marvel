# Changelog — Clean Marvel Album

## v1.2.0 – 2025-11-29
### Security enhancements
- CSP con nonces dinámicos
- Eliminación de `'unsafe-inline'` en `script-src` (manteniendo `'unsafe-inline'` en `style-src` por Tailwind CDN)
- Generación de nonces
- Tests de CSP y documentación reorganizada en `docs/security/`

### Testing
- Tests automatizados y CI (ver `.github/workflows/ci.yml`)
- Cobertura de seguridad mejorada
- Tests de CSP con verificación de nonces

### Documentation
- Reorganización completa de `docs/`
- Guía de verificación de seguridad
- Walkthrough de implementación CSP

## v1.1.0 – 2025-11-01
- Controladores extraídos del index.php
- Añadido QA unificado en VS Code
- Corrección de constantes runtime para PHPStan

## v1.0.0 – 2025-10-30
- Primera versión estable (MVP)
- Arquitectura Clean inicial con JSON persistence
- EventBus y módulos Album/Hero/Notifications
