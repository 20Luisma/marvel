# Documentación de seguridad

Esta carpeta contiene toda la documentación relacionada con las medidas de seguridad implementadas en **Clean Marvel Album**.

---

## Documentos disponibles

### 1. `docs/security/security.md`
Documentación completa de todas las medidas de seguridad implementadas en el proyecto:
- CSRF Protection
- Rate Limiting
- Session Security
- Input Sanitization
- Security Headers
- CSP con nonces
- HMAC para microservicios
- Otros controles documentados en el archivo.

### 2. `docs/security/security_verification.md`
Guía práctica de verificación de seguridad con 10 pruebas para validar que el sistema CSP funciona correctamente:
- Verificación de headers
- Nonces únicos
- Bloqueo de XSS
- Tests automatizados
- CSP Evaluator
- Pruebas en navegador

---

## Uso rápido

### Verificar Seguridad CSP
```bash
# Ver headers CSP
curl -I http://localhost:8080/ | grep -i content-security-policy

# Verificar nonces únicos
curl -I http://localhost:8080/ 2>&1 | grep -o "nonce-[^']*"

# Ejecutar tests de seguridad
vendor/bin/phpunit tests/Security/ --testdox
```

---

## Alcance y límites

Este repositorio incluye controles de seguridad **a nivel académico/didáctico** (hardening, CSRF, rate limit, sesión, sanitización y CSP).  
No se presenta como seguridad para entornos regulados: hay límites explícitos en `docs/security/security.md` y recomendaciones de hardening futuro.

---

## Para más información

- Ver `docs/security/security.md` para detalles técnicos
- Ver `docs/security/security_verification.md` para pruebas prácticas
- Ver `docs/development/analisis_estructura.md` para arquitectura general
