# ADR-012: Estrategia de Versionado y Release Management

* **Estado**: Aceptado
* **Fecha**: 2026-01-31
* **Contexto**: 
  Inicialmente, el sistema generaba una versión oficial (tag v1.x.x) en cada commit fusionado en `main`. Esto resultó en un volumen excesivo de versiones ("noise") que dificultaba la identificación de hitos reales del proyecto y restaba profesionalidad al historial de entregas.

* **Decisión**: 
  Se ha modificado el pipeline de CI/CD (`auto-release.yml`) para restringir la generación automática de versiones.
  1. Solo se disparan versiones automáticas cuando el merge en `main` proviene de una rama con el prefijo `release/` (e.g., `release/v2.1.0`).
  2. Los cambios diarios (features, fixes) se fusionan en `main` y se despliegan, pero no generan etiquetas de versión.
  3. Se mantiene el "workflow_dispatch" para lanzamientos manuales de emergencia.

* **Consecuencias**:
  * **Positivas**: Historial de versiones limpio y orientado a hitos ("Milestones"). Facilidad para auditoría académica de entregas.
  * **Negativas**: Requiere una disciplina mayor por parte del desarrollador al nombrar las ramas de entrega.
