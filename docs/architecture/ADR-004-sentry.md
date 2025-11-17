# ADR-004 – Integración con Sentry como sistema de observabilidad

## Estado
Accepted

## Contexto
El proyecto necesita capturar errores en tiempo real y presentar eventos recientes sin depender únicamente de logs planos.

## Decisión
Inicializar Sentry en `src/bootstrap.php` usando `SENTRY_DSN` y el entorno activo.  
Crear el endpoint `public/api/sentry-metrics.php` que llama a `https://sentry.io/api/0/projects/{org}/{project}/events/` con `SENTRY_API_TOKEN`, cachea los resultados en `storage/sentry-metrics.json` y normaliza para la vista `views/pages/sentry.php`.  
Agregar `public/api/sentry-test.php` para lanzar errores controlados y validar rutas en la UI.

## Justificación
- Permite que la versión de demo muestre errores reales sin exponer el panel de Sentry.  
- La vista en la app puede lanzar errores de prueba y ver eventos recientes con un botón.  
- El endpoint centraliza la lógica de llamadas, retries y fallback en cache.

## Consecuencias
### Positivas
- Observabilidad continua desde la misma app.  
- Usuarios pueden validar Sentry mediante `sentry-test.php`.
### Negativas
- Requiere mantener `SENTRY_API_TOKEN` y `SENTRY_DSN` actualizados.  
- La UI depende de la disponibilidad de la API de Sentry (aunque hay cache).

## Opciones descartadas
- Usar solo `error_log` sin una consola central (pierde visibilidad).  
- No integrar Sentry y depender únicamente de SonarCloud (diferentes propósitos).

## Supersede
Actualizar si migramos a otro proveedor de eventos o si movemos esta lógica a un microservicio separado.
