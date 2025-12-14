# Fix: Token Metrics en Hosting

## Problema Resuelto

El dashboard `/secret-ai-metrics` funcionaba en **local** pero en **hosting** solo mostraba datos de `comic_generator`, sin contabilizar `compare_heroes` ni `marvel_agent`.

## Causa Raíz

El servicio `TokenMetricsService` lee dos archivos de logs:

1. `storage/ai/tokens.log` → logs de `comic_generator` (app principal)
2. `rag-service/storage/ai/tokens.log` → logs de `compare_heroes` y `marvel_agent`

**En local**: `rag-service/` es un directorio real, por lo que una ruta relativa puede funcionar.

**En hosting**: se observó un despliegue donde `rag-service/` apunta a una ruta externa (p. ej., mediante symlink), lo que hacía fallar `file_exists()` sobre la ruta relativa.

## Solución Implementada

Se implementó un mecanismo de resolución automática de rutas con tres niveles de fallback:

### 1. Variable de entorno (máxima prioridad)
```bash
RAG_LOG_PATH=/ruta/personalizada/tokens.log
```
Permite configuración explícita en entornos especiales.

### 2. Ruta relativa (para local)
```php
__DIR__ . '/../../rag-service/storage/ai/tokens.log'
```
Funciona cuando `rag-service/` es un directorio real.

### 3. Ruta absoluta (para hosting)
```php
'/home/REDACTED_SSH_USER/rag-service/storage/ai/tokens.log'
```
Fallback hardcoded para el hosting conocido.

## Código Modificado

### `src/Monitoring/TokenMetricsService.php`

**Cambios principales:**
- Nuevo método `resolveRagLogPath()` para resolver rutas con fallback.
- Configuración opcional vía variable de entorno.

**Método clave:**
```php
private function resolveRagLogPath(): ?string
{
    // 1. Variable de entorno
    $envPath = getenv('RAG_LOG_PATH');
    if (is_string($envPath) && $envPath !== '' && file_exists($envPath)) {
        return $envPath;
    }

    // 2. Ruta relativa (local)
    $relativePath = __DIR__ . '/../../rag-service/storage/ai/tokens.log';
    if (file_exists($relativePath)) {
        return $relativePath;
    }

    // 3. Ruta absoluta (hosting)
    $hostingPath = '/home/REDACTED_SSH_USER/rag-service/storage/ai/tokens.log';
    if (file_exists($hostingPath)) {
        return $hostingPath;
    }

    return null; // No disponible
}
```

## Ventajas de Esta Solución

- No cambia contratos públicos: mantiene compatibilidad con el código existente  
- Funciona en local: usa ruta relativa automáticamente  
- Funciona en hosting: permite ruta absoluta cuando aplica  
- Configurable: permite override vía variable de entorno  
- Extensible: permite añadir más entornos/rutas si fuese necesario  

## Verificación en Local

```bash
$ php -r "require 'vendor/autoload.php'; \
  \$service = new App\Monitoring\TokenMetricsService(); \
  \$metrics = \$service->getMetrics(); \
  echo 'Features detectadas:' . PHP_EOL; \
  foreach (\$metrics['by_feature'] as \$f) { \
    echo '  - ' . \$f['feature'] . ': ' . \$f['calls'] . ' llamadas' . PHP_EOL; \
  }"

Ejemplo de salida (los valores dependen del log disponible en `rag-service/storage/ai/tokens.log`):
  - comic_generator: …
  - compare_heroes: …
  - marvel_agent: …
```

Resultado esperado: que el panel agregue métricas de todas las features registradas en los logs disponibles.

## Próximos Pasos

1. **Desplegar a hosting** el código actualizado
2. **Verificar** que el dashboard muestra las 3 features
3. **Opcional**: Si el hosting tiene configuración especial, añadir `RAG_LOG_PATH` al `.env` del hosting

## Archivos modificados

- `src/Monitoring/TokenMetricsService.php` - Resolución de ruta del log de RAG
- `.env.example` - Variable `RAG_LOG_PATH`
- `docs/fixes/TOKEN_METRICS_HOSTING_FIX.md` - Esta documentación

## Testing (escenarios)

### Escenario 1: Entorno local
- La ruta relativa puede funcionar si `rag-service/` es un directorio real.

### Escenario 2: Hosting
- La ruta relativa puede no funcionar si `rag-service/` apunta fuera del árbol del proyecto.
- El fallback a ruta absoluta depende del entorno y permisos de lectura.

### Escenario 3: Con variable de entorno
- `RAG_LOG_PATH` tiene prioridad si apunta a un archivo existente.
- Permite configurar el path sin modificar código.

---

**Fecha**: 2025-12-03  
**Estado**: implementado (según el código del repositorio)
