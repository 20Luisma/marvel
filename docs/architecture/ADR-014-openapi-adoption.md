# ADR-014: Adopción de OpenAPI 3.0 para Interoperabilidad

* **Estado**: Aceptado
* **Fecha**: 2026-01-31
* **Contexto**: 
  La observabilidad del sistema se basa en múltiples endpoints de API. Sin una especificación formal, los evaluadores deben deducir el esquema de datos manualmente, lo cual resta profesionalidad al proyecto.

* **Decisión**: 
  Adoptar el estándar **OpenAPI 3.0.3** para documentar formalmente la API de observabilidad.
  * Se implementa un contrato `openapi.yaml`.
  * Se despliega un visor interactivo **Swagger UI** en `public/api/docs.html`.

* **Consecuencias**:
  * **Positivas**: Interoperabilidad demostrada. El tribunal puede interactuar con la API mediante una interfaz estandarizada. Sigue las mejores prácticas de la industria (API-First).
  * **Negativas**: Esfuerzo de mantenimiento adicional al modificar los endpoints.
