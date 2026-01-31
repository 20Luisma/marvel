# Security Policy

## Gestión de Secretos

> **Los secretos viven exclusivamente en variables de entorno (`.env` local / configuración del hosting).**  
> Nunca se commitean al repositorio.

### Protección implementada

| Mecanismo | Descripción |
|-----------|-------------|
| `.gitignore` | El archivo `.env` está excluido del control de versiones |
| `.env.example` | Plantilla pública sin valores reales — solo placeholders |
| Hosting | Las variables sensibles se configuran en el panel del proveedor |
| Kubernetes | Secrets gestionados con Sealed Secrets (ver [`k8s/SECURITY_HARDENING.md`](./k8s/SECURITY_HARDENING.md)) |

### Variables sensibles

Las siguientes variables **nunca** deben exponerse públicamente:

```env
# Credenciales de base de datos
DB_HOST, DB_NAME, DB_USER, DB_PASS

# APIs externas
OPENAI_API_KEY
ELEVENLABS_API_KEY
GITHUB_TOKEN
SENTRY_DSN

# Autenticación
ADMIN_PASSWORD_HASH
SESSION_SECRET
```

### Qué hacer si se filtra un secreto

1. **Revocar inmediatamente** el secreto en el servicio correspondiente (OpenAI, GitHub, etc.)
2. Generar un nuevo secreto y actualizar `.env` / configuración del hosting
3. Si se commiteó accidentalmente, usar `git filter-branch` o BFG Repo-Cleaner para eliminar el historial
4. Notificar al equipo

---

## Seguridad de la Aplicación

Para detalles sobre las medidas de seguridad implementadas en la aplicación (CSRF, Rate Limiting, Security Headers, etc.), consulta:

- [`docs/guides/authentication.md`](./docs/guides/authentication.md) — Autenticación y sesiones
- [`k8s/SECURITY_HARDENING.md`](./k8s/SECURITY_HARDENING.md) — Hardening para Kubernetes

---

## 🚀 Decisiones de Diseño: Modo Demo y Observabilidad

Este proyecto opera en **Modo Demo/Guía**, lo que implica una postura de seguridad específica orientada a la transparencia y facilidad de uso académico.

### 1. Endpoint de Reset (`public/api/reset-demo.php`)
- **Estado:** Público de forma intencional.
- **Racional:** Permite que cada usuario pueda restaurar el entorno a un estado inicial conocido antes de su exploración.
- **Riesgo:** DoS lógico (denegación de servicio por reseteos frecuentes).
- **Decisión:** Riesgo aceptado. En un entorno real, este endpoint requeriría privilegios de `SUPER_ADMIN` o acceso mediante túnel VPN.

### 2. APIs de Métricas y Estado (`public/api/*`)
- **Estado:** Abiertas para lectura.
- **Racional:** Facilitar la observabilidad y demostrar la integración de herramientas como SonarCloud, Sentry y GitHub Metrics sin fricciones.
- **Postura en Producción:** Estos datos deberían centralizarse en un sistema de monitorización interno (como Prometheus/Grafana) con acceso restringido.

---

### 📜 Documentación de la API
Para una referencia completa de los endpoints de observabilidad y su esquema de datos, consulta nuestra [Documentación OpenAPI/Swagger](https://iamasterbigschool.contenido.creawebes.com/api/docs.html).

---

## Reportar Vulnerabilidades

*Última actualización: Diciembre 2024*
