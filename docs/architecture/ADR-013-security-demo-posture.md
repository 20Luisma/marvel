# ADR-013: Postura de Seguridad en Modo Demo

* **Estado**: Aceptado
* **Fecha**: 2026-01-31
* **Contexto**: 
  El proyecto es un TFM con fines didácticos y de demostración técnica. Los evaluadores necesitan poder resetear el entorno y observar métricas internas para validar el funcionamiento del sistema sin configurar credenciales complejas de administrador.

* **Decisión**: 
  Se ha decidido mantener públicamente accesibles ciertos endpoints bajo `public/api/*` (especialmente `reset-demo.php` y métricas de Sonar/Sentry).
  Esta decisión es **deliberada** y se acompaña de:
  1. Documentación explícita de seguridad (`SECURITY.md`).
  2. Advertencias en el código (Docblocks).
  3. Mención en la presentación técnica para diferenciar entre un entorno académico y uno productivo real.

* **Consecuencias**:
  * **Positivas**: Máxima transparencia para el tribunal. Facilidad de uso para el evaluador.
  * **Negativas**: Riesgo aceptado de DoS lógico (reseteos frecuentes). Este diseño nunca debe ser exportado a un sistema con datos de usuario reales.
