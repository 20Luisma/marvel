# Clean Marvel Album — Documentación

Documentación del proyecto **Clean Marvel Album**.

Este directorio describe el diseño, la seguridad, la API, la operación y los flujos de trabajo del repositorio.  
Si hay discrepancias, **prevalece el código** y los workflows de CI.

---

## Índice de documentación

### [Architecture](./architecture/)
Documentación de arquitectura y decisiones de diseño:
- [ARCHITECTURE.md](./architecture/ARCHITECTURE.md) - Visión general de Clean Architecture
- [REQUIREMENTS.md](./architecture/REQUIREMENTS.md) - Requisitos funcionales y no funcionales
- [USE_CASES.md](./architecture/USE_CASES.md) - Casos de uso del sistema
- [ADRs](./architecture/) - Architecture Decision Records

### [Security](./security/)
Documentación de seguridad:
- [security.md](./security/security.md) - Medidas de seguridad completas
- [security_verification.md](./security/security_verification.md) - Guía de verificación (10 pruebas)

### [Deployment](./deployment/)
Guías de despliegue:
- [deploy.md](./deployment/deploy.md) - Instrucciones de deployment

### [API](./api/)
Documentación de API:
- [API_REFERENCE.md](./api/API_REFERENCE.md) - Referencia de endpoints
- [openapi.yaml](./api/openapi.yaml) - Especificación OpenAPI

### [Development](./development/)
Documentación para desarrolladores:
- [agent.md](./development/agent.md) - Guía del agente AI
- [analisis_estructura.md](./development/analisis_estructura.md) - Análisis detallado del proyecto

### [Project Management](./project-management/)
Gestión del proyecto:
- [CHANGELOG.md](./project-management/CHANGELOG.md) - Historial de cambios (v1.2.0)
- [ROADMAP.md](./project-management/ROADMAP.md) - Hoja de ruta
- [CONTRIBUTING.md](./project-management/CONTRIBUTING.md) - Guía de contribución
- [TASKS_AUTOMATION.md](./project-management/TASKS_AUTOMATION.md) - Automatización

### [Guides](./guides/)
Guías prácticas:
- [getting-started.md](./guides/getting-started.md) - Primeros pasos
- [authentication.md](./guides/authentication.md) - Autenticación
- [testing.md](./guides/testing.md) - Testing

### [Components](./components/)
Documentación de componentes UI

### [UML](./uml/)
Diagramas UML del sistema

---

## Quick Start (verificable)

### Instalación
```bash
git clone <repo>
cd clean-marvel
composer install
cp .env.example .env
php -S localhost:8080 -t public
```

### Testing
```bash
# Suite de tests (ver salida de PHPUnit)
vendor/bin/phpunit --colors=always

# Solo tests de seguridad
vendor/bin/phpunit tests/Security/ --testdox

# Verificar CSP
curl -I http://localhost:8080/ | grep -i content-security-policy
```

---

## Contenido (sin autoevaluación)

### Seguridad (alcance académico)
- CSP con nonce para `script-src` (concesiones en `style-src` por Tailwind CDN)
- CSRF en rutas POST críticas
- Rate limit en rutas sensibles
- Sesiones con TTL/lifetime e integridad IP/UA
- Firma interna por HMAC entre servicios cuando se configura `INTERNAL_API_KEY`

### Arquitectura (Clean Architecture)
- Separación de capas (Domain, Application, Infrastructure)
- Inversión de dependencias
- Event-Driven con EventBus
- Repository Pattern
- Use Cases bien definidos

### Testing
- Tests unitarios/integración con PHPUnit
- Cobertura de seguridad, dominio, infraestructura
- Tests E2E con Playwright

### Calidad de código
- PHPStan (nivel configurado en `phpstan.neon`)
- SonarCloud (config en `sonar-project.properties`)
- Documentación y ADRs

---

## Métricas (cómo verificar)

En este repo se evita fijar números “mágicos” en documentación porque cambian con el tiempo.  
Para obtener métricas actuales:

- Tests: ejecuta `vendor/bin/phpunit` (PHPUnit imprime el resumen).
- Cobertura: ejecuta `composer test:coverage` (genera `coverage.xml`) y CI aplica umbral con `scripts/coverage-gate.php`.
- E2E: ejecuta `npm run test:e2e` (Playwright).

---

## Enlaces rápidos

- [Changelog v1.2.0](./project-management/CHANGELOG.md) - Últimos cambios
- [Verificación de Seguridad](./security/security_verification.md) - Guía de pruebas
- [Arquitectura](./architecture/ARCHITECTURE.md) - Visión general
- [API Reference](./api/API_REFERENCE.md) - Endpoints disponibles

---

## Versión actual

**v1.2.0** (2025-11-29)
- CSP Hardening con nonces dinámicos
- Documentación reorganizada

---

## Autor

**Martín Pallante**  
Proyecto Final del Máster en Desarrollo de IA - Big School 2025

---

## Licencia

Este proyecto es parte de un trabajo académico del Máster en Desarrollo de IA.
