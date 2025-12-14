# Fix: Token logging en hosting para `rag-service`

## Problema identificado

En hosting se observó que el contador de tokens no funcionaba correctamente para:
- Comparar héroes (RAG): no comparaba y no contabilizaba tokens.
- Marvel Agent: respondía pero no contabilizaba tokens.
- Crear cómic: se contabilizaba en el log de la aplicación principal.

## Causa Raíz

El `OpenAiHttpClient.php` del rag-service tenía un **path incorrecto** para el archivo de logs:

```php
// Antes (incorrecto)
$logFile = __DIR__ . '/../../../../storage/ai/tokens.log';
```

Este path apuntaba al directorio `storage/` del proyecto principal, pero:
- **En local**: Funcionaba porque todo está en el mismo servidor
- **En hosting**: Fallaba porque el rag-service está en un servidor/contenedor separado

## Solución Implementada

### 1. Corregir el path del log
```php
// Ahora (correcto)
$logFile = __DIR__ . '/../../../storage/ai/tokens.log';
```

Ahora cada servicio escribe en su propio directorio:
- `storage/ai/tokens.log` → Para comics (proyecto principal)
- `rag-service/storage/ai/tokens.log` → Para comparación de héroes y Marvel Agent

### 2. Soporte para features específicos

Modificado `OpenAiHttpClient` para aceptar un parámetro `$feature`:

```php
public function __construct(?string $openAiEndpoint = null, string $feature = 'rag_service')
{
    // ...
    $this->feature = $feature;
}
```

### 3. Instancias separadas por feature

En `rag-service/src/bootstrap.php`:

```php
$llmClientForCompare = new OpenAiHttpClient($openAiEndpoint, 'compare_heroes');
$llmClientForAgent = new OpenAiHttpClient($openAiEndpoint, 'marvel_agent');

$ragService = new HeroRagService($knowledgeBase, $retriever, $llmClientForCompare);
$agentUseCase = new AskMarvelAgentUseCase($agentRetriever, $llmClientForAgent);
```

### 4. TokenMetricsService (agregación)

Ahora lee de **ambos archivos de log** para agregar todas las métricas:

```php
// Lee del proyecto principal
if (file_exists(self::LOG_FILE)) {
    // storage/ai/tokens.log
}

// Lee del rag-service
$ragLogFile = __DIR__ . '/../../rag-service/storage/ai/tokens.log';
if (file_exists($ragLogFile)) {
    // rag-service/storage/ai/tokens.log
}
```

### 5. Estructura de directorios

```
rag-service/storage/ai/
├── .gitignore    # Ignora *.log pero no .gitkeep
├── .gitkeep      # Preserva el directorio en git
└── tokens.log    # Se crea automáticamente
```

## Archivos Modificados

1. ✏️ `rag-service/src/Application/Clients/OpenAiHttpClient.php`
   - Corregido path del log
   - Agregado parámetro `$feature`
   - Mejorado manejo de errores

2. ✏️ `rag-service/src/bootstrap.php`
   - Creadas instancias separadas de LlmClient
   - Una para `compare_heroes`
   - Otra para `marvel_agent`

3. ✏️ `src/Monitoring/TokenMetricsService.php`
   - Lee de ambos archivos de log
   - Agrega todas las métricas correctamente

4. ➕ `rag-service/storage/ai/.gitignore`
   - Nuevo archivo para ignorar logs

5. ➕ `rag-service/storage/ai/.gitkeep`
   - Preserva el directorio en git

## Verificación en Local

1. **Crear un cómic**:
   ```bash
   # Debe registrar en storage/ai/tokens.log
   # Feature: comic_generator
   ```

2. **Comparar héroes**:
   ```bash
   # Debe registrar en rag-service/storage/ai/tokens.log
   # Feature: compare_heroes
   ```

3. **Preguntar al Marvel Agent**:
   ```bash
   # Debe registrar en rag-service/storage/ai/tokens.log
   # Feature: marvel_agent
   ```

4. **Ver métricas**:
   ```bash
   # /secret-ai-metrics debe mostrar todos los tokens
   # Agrupados por feature
   ```

## Deployment en Hosting

Al hacer deploy:

1. El directorio `rag-service/storage/ai/` debe existir y ser escribible por el proceso PHP.
2. Los permisos de escritura deben ser consistentes con el entorno (p. ej., 755/775 según usuario/grupo del servidor).
3. Cada servicio escribe en su propio log.
4. El dashboard agrega ambos logs (si están disponibles y se pueden leer).
5. En algunos despliegues, `rag-service/` dentro del proyecto principal puede ser un symlink a una ruta externa; la resolución de rutas debe contemplar ese caso.

## Resultado esperado

Después de estos cambios, en hosting:
- Crear cómic: mantiene el registro de tokens en el log de la app principal.
- Comparar héroes: registra tokens en el log del `rag-service`.
- Marvel Agent: registra tokens en el log del `rag-service`.
- Dashboard de métricas: agrega tokens desde ambos logs.

## Notas

- Los logs se mantienen separados por arquitectura (microservicios).
- El dashboard agrega métricas a partir de los logs disponibles.
- Cada feature se etiqueta en el log.
- El objetivo es mantener compatibilidad sin modificar el contrato público del dashboard.

---

**Fecha**: 2025-11-30  
**Autor**: —  
**Issue**: Token logging not working in hosting for RAG service
