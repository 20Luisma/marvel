# ADR-003 – Integración con SonarCloud como sistema de calidad

## Estado
Accepted

## Contexto
Queremos que el proyecto refleje métricas de calidad (bugs, vulnerabilidades, duplicación, cobertura) sin exponer secretos ni forzar la ejecución de pipelines externos en cada cambio.

## Decisión
Agregar un endpoint PHP (`public/api/sonar-metrics.php`) que consulta la API oficial de SonarCloud usando `SONARCLOUD_TOKEN` y `SONARCLOUD_PROJECT_KEY`, normaliza las métricas y devuelve un JSON consumido por `views/pages/sonar.php`. El archivo usa `Dotenv` si está disponible y reintentos con backoff para mejorar resiliencia.

## Justificación
- Evita exponer tokens al frontend: el JS solo llama al endpoint interno.  
- Permite mostrar métricas en vivo sin necesidad de exponer la UI de SonarCloud ni compartir credenciales.  
- Facilita que SonarCloud consuma el reporte (`build/coverage.xml`) generado por `composer test:cov`.

## Consecuencias
### Positivas
- La vista `sonar.php` muestra code smells, bugs, vulnerabilidades, cobertura y gráficos con Chart.js.  
- El endpoint se puede cachear (`storage/sonar-metrics.json`) para evitar saturar la API pública.
### Negativas
- Requiere mantener el token actualizado en `.env`.  
- Hay que documentar la falta de datos cuando el token no está configurado.

## Opciones descartadas
- Dejar que el frontend consulte directamente SonarCloud (exposición de secrets).  
- Eliminar la visualización en la app (pierde valor didáctico).

## Supersede
Revisar si se adopta otro proveedor de QA o si se reemplaza por una integración push hacia SonarCloud.
