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

## Reportar Vulnerabilidades

Si descubres una vulnerabilidad de seguridad, por favor repórtala de forma responsable:

1. **No** abras un issue público
2. Contacta directamente al mantenedor del proyecto
3. Proporciona detalles suficientes para reproducir el problema

---

*Última actualización: Diciembre 2024*
