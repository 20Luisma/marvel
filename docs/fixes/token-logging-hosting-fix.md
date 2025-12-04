# 🔧 Fix: Token Logging en Hosting para RAG Service

## Problema Identificado

En hosting, el contador de tokens NO funcionaba correctamente para:
- ❌ **Comparar Héroes (RAG)**: No comparaba y no contaba tokens
- ⚠️ **Marvel Agent**: Respondía pero no contaba tokens
- ✅ **Crear Cómic**: Funcionaba perfectamente

## Causa Raíz

El `OpenAiHttpClient.php` del rag-service tenía un **path incorrecto** para el archivo de logs:

```php
// ❌ ANTES (incorrecto)
$logFile = __DIR__ . '/../../../../storage/ai/tokens.log';
```

Este path apuntaba al directorio `storage/` del proyecto principal, pero:
- **En local**: Funcionaba porque todo está en el mismo servidor
- **En hosting**: Fallaba porque el rag-service está en un servidor/contenedor separado

## Solución Implementada

### 1. **Corregido el path del log**
```php
// ✅ AHORA (correcto)
$logFile = __DIR__ . '/../../../storage/ai/tokens.log';
```

Ahora cada servicio escribe en su propio directorio:
- `storage/ai/tokens.log` → Para comics (proyecto principal)
- `rag-service/storage/ai/tokens.log` → Para comparación de héroes y Marvel Agent

### 2. **Agregado soporte para features específicos**

Modificado `OpenAiHttpClient` para aceptar un parámetro `$feature`:

```php
public function __construct(?string $openAiEndpoint = null, string $feature = 'rag_service')
{
    // ...
    $this->feature = $feature;
}
```

### 3. **Creadas instancias separadas por feature**

En `rag-service/src/bootstrap.php`:

```php
$llmClientForCompare = new OpenAiHttpClient($openAiEndpoint, 'compare_heroes');
$llmClientForAgent = new OpenAiHttpClient($openAiEndpoint, 'marvel_agent');

$ragService = new HeroRagService($knowledgeBase, $retriever, $llmClientForCompare);
$agentUseCase = new AskMarvelAgentUseCase($agentRetriever, $llmClientForAgent);
```

### 4. **Actualizado TokenMetricsService**

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

### 5. **Estructura de directorios creada**

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

1. ✅ El directorio `rag-service/storage/ai/` se creará automáticamente
2. ✅ Los permisos de escritura deben estar correctos (755)
3. ✅ Cada servicio escribirá en su propio log
4. ✅ El dashboard agregará todos los logs correctamente
5. ✅ En hosting, `rag-service/` en el proyecto principal es un symlink a `/home/u968396048/rag-service`, para que `TokenMetricsService` lea `rag-service/storage/ai/tokens.log` igual que en local.

## Resultado Esperado

Después de estos cambios, en hosting:

- ✅ **Crear Cómic**: Sigue funcionando (sin cambios)
- ✅ **Comparar Héroes**: Ahora compara Y cuenta tokens
- ✅ **Marvel Agent**: Ahora responde Y cuenta tokens
- ✅ **Dashboard de Métricas**: Muestra TODOS los tokens correctamente

## Notas Importantes

- 📝 Los logs se mantienen separados por arquitectura (microservicios)
- 📝 El dashboard los agrega automáticamente
- 📝 Cada feature tiene su propio tracking
- 📝 No se rompe nada existente (backwards compatible)

---

**Fecha**: 2025-11-30  
**Autor**: Antigravity AI Assistant  
**Issue**: Token logging not working in hosting for RAG service
