# 🕓 Changelog — Clean Marvel Album

## v1.2.0 – 2025-11-29
### 🔒 Security Enhancements
- **CSP Hardening**: Implementación de Content Security Policy estricta con nonces dinámicos
- Eliminado `'unsafe-inline'` de `script-src` (protección XSS completa)
- Generador de nonces criptográficos (128 bits de entropía)
- 6 nuevos tests de seguridad CSP
- Documentación completa de seguridad reorganizada en `docs/security/`

### ✅ Testing
- 191 tests automatizados pasando (100%)
- Cobertura de seguridad mejorada
- Tests de CSP con verificación de nonces

### 📚 Documentation
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
