# 🔍 ZONAR - Informe de Auditoría de Arquitectura y Seguridad

**Proyecto**: Clean Marvel Album  
**Fecha**: 2025-11-30  
**Auditor**: Zonar (Sistema de Auditoría Senior)  
**Versión**: 1.0  

---

## 1️⃣  Resumen del Problema

### Estado en LOCAL (✅ Todo funciona)
- **Crear Cómic**: ✅ Funciona perfectamente
  - Llama a OpenAI Service (puerto 8081)
  - Logs de tokens registrados en `storage/ai/tokens.log`
  - Feature: `comic_generator`

- **Comparar Héroes (RAG)**: ✅ Funciona perfectamente
  - Llama a RAG Service (puerto 8082) → OpenAI Service (puerto 8081)
  - Logs de tokens registrados en `rag-service/storage/ai/tokens.log`
  - Feature: `compare_heroes`

- **Marvel Agent / AgentIA**: ✅ Funciona perfectamente
  - Llama a RAG Service (puerto 8082) → OpenAI Service (puerto 8081)
  - Logs de tokens registrados en `rag-service/storage/ai/tokens.log`
  - Feature: `marvel_agent`

### Estado en HOSTING (⚠️ Fallos parciales)
- **Crear Cómic**: ✅ Funciona
  - Respuesta correcta
  - Logs de tokens **SÍ** se registran

- **Marvel Agent / AgentIA**: ⚠️ Funciona pero sin métricas
  - Responde correctamente
  - Logs de tokens **NO** llegan (no se registran)

- **Comparar Héroes (RAG)**: ❌ Error 500
  - Error en UI: `Error interno en el proxy RAG: El cuerpo de la petición está vacío`
  - En consola: `POST /api/rag/heroes` → **Status 500**
  - El cuerpo de la petición se pierde antes de llegar al controlador

### Síntomas Técnicos Detectados

**Del código revisado, he identificado:**

1. **Middleware de seguridad configurado en bootstrap.php** (líneas 156-183):
   - SecurityHeaders con CSP y nonces
   - Anti-Replay tokens
   - ApiFirewall con validación de payload
   - RateLimit

2. **Flujo de lectura del body**:
   - `ApiFirewall::readRawInput()` → llama a `RequestBodyReader::getRawBody()`
   - `RequestBodyReader::getRawBody()` → lee `php://input` **UNA SOLA VEZ** y lo cachea
   - RagProxyController intenta leer desde `$_POST` primero, luego `php://input`

3. **Formato de envío desde frontend (comic.js)** líneas 1193-1217:
   - Actualmente envía como `application/x-www-form-urlencoded` (FormData)
   - El proxy PHP espera leerlo desde `$_POST` o `php://input`

4. **ApiFirewall tiene whitelist** para `/api/rag/heroes` (línea 24):
   - Está en la allowlist, teóricamente debe omitir validaciones
   - Pero el método `readRawInput()` **SÍ SE EJECUTA** y puede consumir el stream

---

## 2️⃣ Mapa de Flujo de Cada Funcionalidad

### A) Crear Cómic (✅ Local + ✅ Hosting)

```
Frontend (comic.js línea 1087-1146)
  │
  ├──> POST /comics/generate
  │     Content-Type: application/json
  │     Body: { heroIds: ["id1", "id2", ...] }
  │
  └──> Router.php (handlePost línea 211-214)
       │
       ├──> ComicController.php::generate()
       │    │
       │    └──> OpenAIComicGenerator.php::generateComic()
       │         │   - Llama directamente a http://localhost:8081/v1/chat (local)
       │         │   - o https://openai-service.contenido.creawebes.com/v1/chat (hosting)
       │         │
       │         └──> requestChat() (línea 174-300)
       │              │
       │              ├──> Usa cURL directo con reintentos
       │              │
       │              └──> logUsageIfAvailable() (línea 324-355)
       │                   │
       │                   └──> TokenLogger::log() → storage/ai/tokens.log
       │                        Feature: 'comic_generator'
       │
       └──> ✅ Funciona en local y hosting
            ✅ Tokens se registran correctamente
```

**Por qué funciona:**
- Envío JSON directo
- No pasa por el proxy RAG
- El cuerpo NO se consume antes en middleware (ruta no está en whitelist de ApiFirewall pero no es problemática)
- OpenAIComicGenerator controla 100% el flujo: hace la llamada, obtiene usage, y logea tokens

---

### B) Comparar Héroes RAG (✅ Local | ❌ Hosting)

```
Frontend (comic.js línea 1148-1284)
  │
  ├──> POST /api/rag/heroes
  │     Content-Type: application/x-www-form-urlencoded (FormData desde línea 1204)
  │     Body: question=...&heroIds=["id1","id2"]&csrf_token=...
  │
  └──> public/index.php
       │
       └──> Router.php::handle() (línea 72-90)
            │
            ├─1─> ApiFirewall::handle() (línea 31-77 en ApiFirewall.php)
            │     │
            │     ├──> shouldSkip('/api/rag/heroes') → TRUE (línea 79-91)
            │     │     ⚠️ PERO readRawInput() SE EJECUTA ANTES (línea 37)
            │     │
            │     ├──> readRawInput() llama RequestBodyReader::getRawBody()
            │     │     └──> Lee php://input UNA VEZ y lo cachea (línea 15-22 RequestBodyReader.php)
            │     │         ⚠️ PROBLEMA: php://input se puede leer SOLO UNA VEZ en HTTP POST
            │     │         ⚠️ FormData NO se lee desde php://input, está en $_POST
            │     │
            │     └──> logDebugInfo() registra en debug_rag_proxy.log
            │
            ├─2─> RateLimitMiddleware::handle() → pasa (configurado 20 req/60s)
            │
            └─3─> Router::handlePost() → '/api/rag/heroes' (línea 211-214)
                  │
                  └──> public/api/rag/heroes/index.php
                       │
                       ├──> RagProxyController::forwardHeroesComparison() (línea 28-112)
                       │    │
                       │    ├──> Línea 33-56: Lee desde $_POST primero
                       │    │    │
                       │    │    ├──> if (!empty($_POST)) → Intenta leer heroIds (JSON string)
                       │    │    │    └── FormData envía: heroIds=["id1","id2"]
                       │    │    │
                       │    │    └──> ELSE: Intenta php://input (línea 50-55)
                       │    │         ⚠️ AQUÍ FALLA EN HOSTING
                       │    │         ⚠️ php://input YA FUE CONSUMIDO por ApiFirewall
                       │    │         ⚠️ Retorna cadena vacía
                       │    │
                       │    ├──> Línea 58-61: if (empty($payload))
                       │    │    └──> Lanza excepción: "El cuerpo de la petición está vacío"
                       │    │
                       │    └──> Línea 102-111: catch → Error 500 JSON
                       │
                       └──> ❌ FALLO EN HOSTING
                            ⚠️ El body se pierde
```

**Por qué falla en hosting:**
- **ApiFirewall** ejecuta `readRawInput()` incluso si la ruta está en whitelist
- `RequestBodyReader::getRawBody()` consume `php://input` una vez
- En **local**: no hay problema (¿middleware desactivado o configuración diferente?)
- En **hosting**: con los 10 sistemas de seguridad activados, ApiFirewall SE EJECUTA
- **FormData** está en `$_POST`, pero el código intenta leer `php://input` como fallback
- Como `php://input` ya fue leído, retorna vacío → Error 500

---

### C) Marvel Agent / AgentIA (✅ Local con tokens | ⚠️ Hosting sin tokens)

```
Frontend (agentia.js línea 32-60)
  │
  ├──> POST /api/marvel-agent.php
  │     Content-Type: application/x-www-form-urlencoded
  │     Body: question=...
  │
  └──> public/api/marvel-agent.php (línea 1-106)
       │
       ├──> Línea 15-23: Lee $_POST['question']
       │    └──> ✅ Sin problemas, usa $_POST directamente
       │
       ├──> Línea 25-32: Resuelve RAG_SERVICE_URL
       │    └──> http://localhost:8082/rag/agent (local)
       │         https://rag-service.contenido.creawebes.com/rag/agent (hosting)
       │
       ├──> Línea 56-69: Usa CurlHttpClient::postJson()
       │    │
       │    └──> Llama al RAG Service → /rag/agent
       │
       └──> RAG Service → OpenAiHttpClient::ask()
            │
            ├──> Llama a OpenAI Service
            │
            ├──> En local:
            │    └──> logUsage() se ejecuta (línea 171-211 OpenAiHttpClient.php)
            │         └──> Escribe en rag-service/storage/ai/tokens.log
            │              Feature: 'marvel_agent'
            │
            └──> En hosting:
                 ⚠️ HIPÓTESIS: logUsage() NO se ejecuta o falla silenciosamente
                 ⚠️ Posibles causas:
                    - Permisos de escritura en rag-service/storage/ai/
                    - Path relativo __DIR__.'/../../../storage/ai/tokens.log' resuelve mal
                    - OpenAI Service no devuelve 'usage' en respuesta (capas extra de proxy)
```

**Por qué no logea tokens en hosting:**
- Funciona correctamente (responde)
- Pero `OpenAiHttpClient::logUsage()` no escribe o no detecta usage
- **Posibles causas:**
  1. Permisos de directorio en hosting
  2. Path relativo mal resuelto
  3. Respuesta de OpenAI Service no incluye `usage` o `raw.usage`

---

## 3️⃣ Hipótesis Técnicas de Fallo (HOSTING)

### H1: ApiFirewall consume php://input antes del RagProxyController **[ALTA PROBABILIDAD]**

**Evidencia:**
- `ApiFirewall::handle()` se ejecuta en Router (línea 72-75)
- `ApiFirewall::readRawInput()` llama `RequestBodyReader::getRawBody()` (línea 93-101 ApiFirewall.php)
- `RequestBodyReader::getRawBody()` lee `php://input` y lo cachea (línea 15-22)
- En PHP, `php://input` se puede leer **UNA SOLA VEZ** en peticiones POST con `Content-Type` diferente a `multipart/form-data`
- FormData con `application/x-www-form-urlencoded` envía datos en `$_POST`, **NO en php://input**
- El RagProxyController intenta leer desde `$_POST` primero (bien), pero si está vacío, intenta `php://input` (línea 50-55)
- Si `php://input` ya fue leído, retorna cadena vacía → Error 500

**Diferencia local vs hosting:**
- En **local**: probablemente ApiFirewall no se ejecuta (modo debug?) o SecurityHeaders está menos restrictivo
- En **hosting**: con "10 sistemas de seguridad", ApiFirewall se ejecuta SIEMPRE

**Solución propuesta:**
- Eliminar `shouldSkip()` check DESPUÉS de `readRawInput()` en ApiFirewall
- O mejor: **NO** llamar `readRawInput()` para rutas en whitelist
- O usar `$GLOBALS['__raw_input__']` que ApiFirewall graba (línea 45)

---

### H2: Diferencia de Content-Type entre local y hosting **[MEDIA PROBABILIDAD]**

**Evidencia:**
- Frontend envía `Content-Type: application/x-www-form-urlencoded` (línea 1213 comic.js)
- En FormData, el body debería estar en `$_POST`, no en `php://input`
- Pero el RagProxyController intenta leer `php://input` como fallback (línea 50-55)
- Si en hosting hay un proxy intermedio (CDN, WAF) que transforma la petición, podría cambiar el Content-Type

**Pruebas:**
- Verificar en logs `debug_rag_proxy.log` (si existe) qué Content-Type llega
- ApiFirewall logea esto en `logDebugInfo()` (línea 268-288)

---

### H3: Ruta de logs de tokens para `marvel_agent` se resuelve mal en hosting **[ALTA PROBABILIDAD]**

**Evidencia:**
- `OpenAiHttpClient::logUsage()` (línea 171-211 en rag-service)
- Escribe en `__DIR__ . '/../../../storage/ai/tokens.log'` (línea 197)
- Path relativo desde `rag-service/src/Application/Clients/OpenAiHttpClient.php`
- Resuelve a: `rag-service/storage/ai/tokens.log`

**En hosting:**
- Si el directorio `rag-service/storage/ai/` no existe o no tiene permisos de escritura → falla silenciosamente
- El código hace `@mkdir()` (línea 200-204) pero puede fallar
- Si falla, el `return` silencioso (línea 203) evita que se escriba el log

**Solución propuesta:**
- Verificar permisos de `rag-service/storage/ai/` en hosting
- Cambiar a path absoluto o usar variable de entorno
- Agregar logging de errores en lugar de `return` silencioso

---

### H4: Middleware de seguridad bloquea selectivamente rutas en hosting **[BAJA PROBABILIDAD]**

**Evidencia:**
- RateLimitMiddleware tiene whitelist para `/api/rag/heroes` (línea 66-68 RateLimitMiddleware.php)
- Pero solo omite rate limit si es POST
- ApiFirewall tiene `/api/rag/heroes` en allowlist (línea 24 ApiFirewall.php)

**Descartado:**
- Si el middleware bloqueara, retornaría 429 o 400, no 500
- El error 500 con "cuerpo vacío" indica que el código del controller se ejecuta
- El middleware **permite** la petición, pero **consume** el body

---

### H5: OpenAI Service en hosting no devuelve `usage` en la respuesta **[MEDIA PROBABILIDAD]**

**Evidencia (para Marvel Agent):**
- `OpenAiHttpClient::logUsage()` solo se ejecuta si `$decoded['ok'] === true` (línea 125-158)
- Luego extrae `$usage = $decoded['usage'] ?? $decoded['raw']['usage'] ?? null` (línea 173)
- Si `$usage` no es array, **return silencioso** (línea 174-176)

**En hosting:**
- Si OpenAI Service está detrás de un proxy adicional o usa un formato de respuesta diferente
- Puede que `usage` no llegue en el mismo path del JSON

**Solución propuesta:**
- Agregar logging temporal para capturar la respuesta completa de OpenAI Service
- Verificar si `usage` viene en otro nodo del JSON

---

### H6: Diferencia en configuración de PHP entre local y hosting (php://input) **[BAJA PROBABILIDAD]**

**Evidencia:**
- Algunas configuraciones de PHP o proxies (nginx, Apache) pueden afectar `php://input`
- Si hay un reverse proxy mal configurado que consume el body antes de PHP → `php://input` llega vacío

**Descartado parcialmente:**
- FormData se envía en `$_POST`, no en `php://input`
- Pero el código del RagProxyController intenta leer ambos

---

## 4️⃣ Pruebas Propuestas para Validar Cada Hipótesis

### P1: Validar consumo de php://input por ApiFirewall **[H1]**

**Acción:**
1. Agregar logging en `RagProxyController::forwardHeroesComparison()` **antes** de leer el body:
   ```php
   // Línea 30 (antes del try)
   file_put_contents($logFile, date('c') . " [RAG] INICIO - Checking GLOBALS\n", FILE_APPEND);
   file_put_contents($logFile, date('c') . " [RAG] GLOBALS __raw_input__ exists: " . (isset($GLOBALS['__raw_input__']) ? 'YES' : 'NO') . "\n", FILE_APPEND);
   if (isset($GLOBALS['__raw_input__'])) {
       file_put_contents($logFile, date('c') . " [RAG] GLOBALS __raw_input__: " . substr($GLOBALS['__raw_input__'], 0, 200) . "\n", FILE_APPEND);
   }
   file_put_contents($logFile, date('c') . " [RAG] $_POST count: " . count($_POST) . "\n", FILE_APPEND);
   file_put_contents($logFile, date('c') . " [RAG] php://input can read: " . (($test = file_get_contents('php://input')) !== false ? strlen($test) . ' bytes' : 'EMPTY/FALSE') . "\n", FILE_APPEND);
   ```

2. Desplegar a hosting y probar `/api/rag/heroes`

3. Revisar `storage/logs/debug_rag_proxy.log`

**Resultado esperado:**
- Si `GLOBALS __raw_input__` existe y tiene contenido → ApiFirewall SÍ lo leyó
- Si `php://input can read: 0 bytes` → Confirma consumo previo
- Si `$_POST count: 2` → FormData llegó correctamente

**Si se confirma H1:**
- El problema es que RagProxyController no usa `$GLOBALS['__raw_input__']`
- Solución: leer desde `$GLOBALS['__raw_input__']` antes de `php://input`

---

### P2: Verificar Content-Type real en hosting **[H2]**

**Acción:**
- Revisar logs de `debug_rag_proxy.log` que ApiFirewall ya genera (línea 268-288)
- Buscar líneas como: `[FIREWALL_DEBUG] POST /api/rag/heroes | Content-Type: ...`

**Si Content-Type es diferente:**
- Ajustar lógica de lectura en RagProxyController

---

### P3: Validar permisos de escritura en rag-service/storage/ai/ **[H3]**

**Acción:**
1. SSH a hosting
2. Ejecutar:
   ```bash
   ls -la /path/to/rag-service/storage/
   ls -la /path/to/rag-service/storage/ai/
   cat /path/to/rag-service/storage/ai/tokens.log
   ```

3. Verificar permisos (deben ser 755 para directorios, 644 para archivos)

4. Verificar propietario (debe ser usuario de PHP, típicamente `www-data` o `nobody`)

**Si no existe o no tiene permisos:**
- Crear directorio manualmente
- Asignar permisos: `chmod 755 storage/ai && chmod 664 storage/ai/tokens.log`

---

### P4: Capturar respuesta completa de OpenAI Service **[H5]**

**Acción:**
1. En `OpenAiHttpClient::ask()`, después de línea 105 (json_decode), agregar:
   ```php
   $debugLog = __DIR__ . '/../../../storage/debug_openai_response.json';
   file_put_contents($debugLog, json_encode([
       'timestamp' => date('c'),
       'feature' => $this->feature,
       'response_snippet' => substr($response, 0, 1000),
       'decoded_keys' => array_keys($decoded),
       'has_usage' => isset($decoded['usage']),
       'has_raw_usage' => isset($decoded['raw']['usage']),
       'full_decoded' => $decoded // TEMPORAL, borrar después
   ], JSON_PRETTY_PRINT), FILE_APPEND);
   ```

2. Probar en hosting: Marvel Agent

3. Revisar `rag-service/storage/debug_openai_response.json`

**Si `has_usage: false` y `has_raw_usage: false`:**
- OpenAI Service no está devolviendo usage
- Investigar en openai-service

---

### P5: Desactivar temporalmente ApiFirewall para /api/rag/heroes **[H1 validación]**

**Acción:**
1. En `ApiFirewall::handle()`, línea 31-34, cambiar:
   ```php
   public function handle(string $method, string $path): bool
   {
       if ($this->shouldSkip($path)) {
           return true; // ← Mover ANTES de readRawInput()
       }

       $rawInput = $this->readRawInput(); // ← Ahora NO se ejecuta para whitelist
       // ...
   ```

2. Desplegar y probar

**Si funciona:**
- Confirma H1: el problema era consumir php://input innecesariamente

---

## 5️⃣ Plan de Acción por Niveles (SIN APLICAR AÚN)

### 🟢 NIVEL 1 – Cambios Rápidos y Poco Invasivos

#### Fix 1.1: Mover lógica de skip ANTES de readRawInput en ApiFirewall **[CRÍTICO]**

**Archivo:** `src/Security/Http/ApiFirewall.php`

**Cambio:** Líneas 31-46

**Antes:**
```php
public function handle(string $method, string $path): bool
{
    if ($this->shouldSkip($path)) {
        return true;
    }

    $rawInput = $this->readRawInput();
    // ...
```

**Después:**
```php
public function handle(string $method, string $path): bool
{
    // BEGIN FIX ZONAR 1.1 - Evitar consumir php://input para rutas en whitelist
    if ($this->shouldSkip($path)) {
        return true; // Salir ANTES de leer el body
    }
    // END FIX ZONAR 1.1

    $rawInput = $this->readRawInput();
    // ...
```

**Impacto:**
- ✅ Evita consumir `php://input` innecesariamente para `/api/rag/heroes`
- ✅ Permite que RagProxyController lea el body sin problemas
- ✅ Sin riesgo: mantiene la whitelist tal cual
- Complejidad: **2/10** (cambio de orden de líneas)

---

#### Fix 1.2: Usar `$GLOBALS['__raw_input__']` en RagProxyController si está disponible **[RECOMENDADO]**

**Archivo:** `src/Controllers/RagProxyController.php`

**Cambio:** Líneas 32-56

**Antes:**
```php
try {
    // LEER DIRECTAMENTE DESDE $_POST (solución definitiva)
    $payload = [];
    
    if (!empty($_POST)) {
        // Viene como FormData
        $heroIds = isset($_POST['heroIds']) ? json_decode($_POST['heroIds'], true) : [];
       // ...
    } else {
        // Intentar desde php://input como fallback
        $rawBody = file_get_contents('php://input');
        if ($rawBody !== false && $rawBody !== '') {
            $payload = json_decode($rawBody, true);
            // ...
        }
    }
```

**Después:**
```php
try {
    // BEGIN FIX ZONAR 1.2 - Leer desde GLOBALS primero (set por ApiFirewall)
    $payload = [];
    $rawBody = null;

    // Opción 1: Leer desde GLOBALS si ApiFirewall ya lo cargó
    if (isset($GLOBALS['__raw_input__']) && is_string($GLOBALS['__raw_input__']) && $GLOBALS['__raw_input__'] !== '') {
        $rawBody = $GLOBALS['__raw_input__'];
        file_put_contents($logFile, date('c') . " [RAG] Leído desde GLOBALS\n", FILE_APPEND);
    }
    
    // Opción 2: Leer desde $_POST (FormData)
    if ($rawBody === null && !empty($_POST)) {
        $heroIds = isset($_POST['heroIds']) ? json_decode($_POST['heroIds'], true) : [];
        if (!is_array($heroIds)) {
            $heroIds = [];
        }
        
        $payload = [
            'question' => $_POST['question'] ?? '',
            'heroIds' => $heroIds
        ];
        
        file_put_contents($logFile, date('c') . " [RAG] Leído desde POST\n", FILE_APPEND);
    }

    // Opción 3: Intentar php://input como último recurso
    if ($rawBody === null && $payload === []) {
        $rawBody = file_get_contents('php://input');
        if ($rawBody !== false && $rawBody !== '') {
            file_put_contents($logFile, date('c') . " [RAG] Leído desde php://input\n", FILE_APPEND);
        }
    }

    //Decodificar rawBody si existe
    if ($rawBody !== null && is_string($rawBody) && $rawBody !== '') {
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $payload = [];
        }
    }
    // END FIX ZONAR 1.2
```

**Impacto:**
- ✅ Prioriza lectura desde `$GLOBALS['__raw_input__']` (cache de ApiFirewall)
- ✅ Si no está, usa `$_POST` (FormData)
- ✅ Solo como último recurso intenta `php://input`
- ✅ Triple fallback, máxima compatibilidad
- Complejidad: **4/10** (lógica condicional adicional)

---

#### Fix 1.3: Verificar y crear directorio `rag-service/storage/ai/` con permisos adecuados **[CRÍTICO para Marvel Agent]**

**Acción manual en hosting (SSH):**
```bash
cd /path/to/rag-service/
mkdir -p storage/ai
chmod 755 storage/ai
touch storage/ai/tokens.log
chmod 666 storage/ai/tokens.log
chown www-data:www-data storage/ai storage/ai/tokens.log  # ajustar usuario según hosting
```

**Impacto:**
- ✅ Permite que `OpenAiHttpClient::logUsage()` escriba logs
- ✅ Sin cambios de código
- Complejidad: **1/10** (comando manual)

---

#### Fix 1.4: Mejorar logging en `OpenAiHttpClient::logUsage()` para detectar fallos **[DIAGNÓSTICO]**

**Archivo:** `rag-service/src/Application/Clients/OpenAiHttpClient.php`

**Cambio:** Líneas 171-211

**Antes:**
```php
private function logUsage(array $decoded): void
{
    $usage = $decoded['usage'] ?? $decoded['raw']['usage'] ?? null;
    if (!is_array($usage)) {
        return; // ← Falla silenciosamente
    }
    // ...
```

**Después:**
```php
private function logUsage(array $decoded): void
{
    // BEGIN FIX ZONAR 1.4 - Logging de diagnóstico
    $debugLog = __DIR__ . '/../../../storage/ai/debug_tokens.log';
    $usage = $decoded['usage'] ?? $decoded['raw']['usage'] ?? null;
    
    if (!is_array($usage)) {
        @file_put_contents($debugLog, date('c') . " [WARN] No usage found for feature={$this->feature}\n", FILE_APPEND);
        return;
    }
    // END FIX ZONAR 1.4

    $model = $decoded['model'] ?? $decoded['raw']['model'] ?? self::DEFAULT_MODEL;
    // ...
```

**Impacto:**
- ✅ Permite detectar si `usage` no llega
- ✅ Temporal: borrar después de diagnosticar
- Complejidad: **2/10** (una línea de log)

---

### 🟡 NIVEL 2 – Cambios Intermedios

#### Fix 2.1: Unificar formato de envío del frontend (JSON puro, no FormData) **[OPCIONAL]**

**Archivo:** `public/assets/js/comic.js`

**Cambio:** Líneas 1193-1218

**Antes:**
```js
const payload = {
  question: 'Compara sus atributos y resume el resultado',
  heroIds: JSON.stringify(finalHeroIds) // ← STRING, no array
};

const formData = new URLSearchParams();
formData.append('question', payload.question);
formData.append('heroIds', payload.heroIds);
if (csrfToken) formData.append('csrf_token', csrfToken);

const response = await fetch(targetEndpoint, {
  method: 'POST',
  credentials: 'same-origin',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded', // ← FormData
    'Accept': 'application/json',
    //...
  },
  body: formData.toString()
});
```

**Después:**
```js
// BEGIN FIX ZONAR 2.1 - Enviar JSON puro
const payload = {
  question: 'Compara sus atributos y resume el resultado',
  heroIds: finalHeroIds // ← ARRAY directo, no string
};

console.log('[RAG] Payload objeto:', payload);
console.log('[RAG] Enviando como JSON');

const response = await fetch(targetEndpoint, {
  method: 'POST',
  credentials: 'same-origin',
  headers: {
    'Content-Type': 'application/json', // ← JSON
    'Accept': 'application/json',
    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
  },
  body: JSON.stringify(payload) // ← JSON.stringify
});
// END FIX ZONAR 2.1
```

**Y ajustar RagProxyController para JSON:**

**Archivo:** `src/Controllers/RagProxyController.php`

**Cambio:** Líneas 32-56

```php
// BEGIN FIX ZONAR 2.1 companion - Leer JSON directamente
$rawBody = isset($GLOBALS['__raw_input__']) ? $GLOBALS['__raw_input__'] : file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    file_put_contents($logFile, date('c') . " [RAG] ERROR: Body vacío\n", FILE_APPEND);
    throw new \RuntimeException('El cuerpo de la petición está vacío');
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    file_put_contents($logFile, date('c') . " [RAG] ERROR: JSON inválido\n", FILE_APPEND);
    throw new \RuntimeException('JSON inválido');
}

file_put_contents($logFile, date('c') . " [RAG] Payload JSON recibido\n", FILE_APPEND);
// END FIX ZONAR 2.1 companion
```

**Impacto:**
- ✅ Simplifica lógica: un solo formato (JSON)
- ✅ Más estándar para APIs REST
- ⚠️ Requiere cambios en frontend Y backend
- Complejidad: **6/10** (cambios en dos capas)

---

#### Fix 2.2: Crear endpoint de salud para validar logging de tokens **[DIAGNÓSTICO]**

**Archivo:** (nuevo) `public/api/test-token-logging.php`

```php
<?php
declare(strict_types=1);

// BEGIN FIX ZONAR 2.2 - Endpoint de prueba de logging
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Monitoring\TokenLogger;

header('Content-Type: application/json');

try {
    TokenLogger::log([
        'feature' => 'test_endpoint',
        'model' => 'test-model',
        'endpoint' => 'test',
        'prompt_tokens' => 10,
        'completion_tokens' => 20,
        'total_tokens' => 30,
        'latency_ms' => 100,
        'tools_used' => 0,
        'success' => true,
        'error' => null,
        'user_id' => 'test',
        'context_size' => 0,
    ]);

    echo json_encode([
        'ok' => true,
        'message' => 'Token log test escrito',
        'log_file' => realpath(__DIR__ . '/../../storage/ai/tokens.log') ?: 'NOT FOUND',
        'log_exists' => file_exists(__DIR__ . '/../../storage/ai/tokens.log'),
        'log_writable' => is_writable(__DIR__ . '/../../storage/ai/'),
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
// END FIX ZONAR 2.2
```

**Uso:**
```bash
curl https://tudominio.com/api/test-token-logging.php
```

**Impacto:**
- ✅ Valida si TokenLogger funciona en hosting
- ✅ Detecta permisos, paths, etc.
- Complejidad: **3/10** (endpoint de prueba)

---

### 🔴 NIVEL 3 – Cambios Radicales (ÚLTIMO RECURSO)

#### Fix 3.1: Reestructurar ApiFirewall para NO leer body de rutas whitelisted **[REFACTORIZACIÓN]**

**Archivo:** `src/Security/Http/ApiFirewall.php`

**Cambio completo de lógica:**

- Separar `handle()` en dos métodos: `shouldProcess()` y `processPayload()`
- Solo llamar `readRawInput()` en `processPayload()`, NO en rutas whitelisted
- Requiere modificar Router.php para llamar `shouldProcess()` primero

**Impacto:**
- ✅ Solución definitiva y limpia
- ⚠️ Cambio arquitectónico significativo
- ⚠️ Requiere pruebas exhaustivas de seguridad
- Complejidad: **9/10** (reestructuración completa)

---

#### Fix 3.2: Migrar logs de tokens a base de datos centralizada **[ARQUITECTURA]**

**Cambio:**
- Crear tabla `ai_tokens_log` en BD
- Modificar `TokenLogger` para escribir en BD en lugar de archivos
- Centralizar métricas desde todos los microservicios

**Impacto:**
- ✅ Solución escalable
- ✅ No depende de permisos de archivos
- ⚠️ Requiere migración de esquema
- ⚠️ Cambio mayor en infraestructura
- Complejidad: **10/10** (cambio completo de persistencia)

---

## 6️⃣ Archivos que Propones Tocar en Cada Nivel

### Nivel 1 (Cambios Rápidos)

| Archivo | Tipo de Cambio | Líneas Afectadas | Complejidad |
|---------|---------------|------------------|-------------|
| `src/Security/Http/ApiFirewall.php` | Mover `shouldSkip()` antes de `readRawInput()` | 31-46 | 2/10 |
| `src/Controllers/RagProxyController.php` | Priorizar lectura desde `$GLOBALS['__raw_input__']` | 32-56 | 4/10 |
| `rag-service/src/Application/Clients/OpenAiHttpClient.php` | Agregar logging de diagnóstico | 171-176 | 2/10 |
| (Hosting SSH) | Crear directorio y permisos | N/A | 1/10 |

**Total archivos:** 3 PHP + 1 comando manual

---

### Nivel 2 (Cambios Intermedios)

| Archivo | Tipo de Cambio | Líneas Afectadas | Complejidad |
|---------|---------------|------------------|-------------|
| `public/assets/js/comic.js` | Cambiar FormData a JSON puro | 1193-1218 | 5/10 |
| `src/Controllers/RagProxyController.php` | Adaptar lectura para JSON | 32-80 | 6/10 |
| `public/api/test-token-logging.php` (nuevo) | Endpoint de diagnóstico | N/A | 3/10 |

**Total archivos:** 2 modificados + 1 nuevo

---

### Nivel 3 (Cambios Radicales)

| Archivo | Tipo de Cambio | Líneas Afectadas | Complejidad |
|---------|---------------|------------------|-------------|
| `src/Security/Http/ApiFirewall.php` | Refactorización completa | Todo el archivo | 9/10 |
| `src/Shared/Http/Router.php` | Adaptar llamadas a ApiFirewall | 72-76 | 7/10 |
| `src/Monitoring/TokenLogger.php` | Migrar a BD | Todo el archivo | 10/10 |
| `database/migrations/` (nuevo) | Crear tabla `ai_tokens_log` | N/A | 8/10 |
| `rag-service/src/Application/Clients/OpenAiHttpClient.php` | Usar TokenLogger con BD | 171-211 | 7/10 |

**Total archivos:** 4 modificados + 1 migración BD

---

## 7️⃣ Recomendación Final de ZONAR

### Estrategia Sugerida: **NIVEL 1 + Validaciones**

1. **Aplicar Fix 1.1**: Mover `shouldSkip()` antes de `readRawInput()` en `ApiFirewall.php`
   - **Justificación**: Es el cambio más pequeño con mayor impacto
   - **Riesgo**: Mínimo (solo cambia orden de ejecución)
   - **Impacto esperado**: ✅ Resuelve error 500 en RAG

2. **Aplicar Fix 1.2**: Priorizar `$GLOBALS['__raw_input__']` en `RagProxyController.php`
   - **Justificación**: Aumenta compatibilidad si ApiFirewall sigue ejecutándose
   - **Riesgo**: Mínimo (triple fallback)
   - **Impacto esperado**: ✅ Máxima robustez

3. **Aplicar Fix 1.3**: Verificar permisos de `rag-service/storage/ai/` en hosting
   - **Justificación**: Sin esto, Marvel Agent nunca logeará tokens
   - **Riesgo**: Nulo (solo permisos)
   - **Impacto esperado**: ✅ Resuelve logging de tokens para Marvel Agent

4. **Aplicar Fix 1.4**: Agregar logging temporal de diagnóstico
   - **Justificación**: Detectar si `usage` llega desde OpenAI Service
   - **Riesgo**: Nulo (solo logs)
   - **Impacto esperado**: 📊 Visibilidad para diagnosticar

5. **Validar con P1, P2, P3, P4**: Ejecutar pruebas de diagnóstico ANTES de aplicar fixes
   - **Justificación**: Confirmar hipótesis con datos reales
   - **Riesgo**: Nulo (solo lectura de logs)
   - **Impacto esperado**: 📊 Datos para validar teoría

### Si Nivel 1 NO Resuelve Todo

- **Plan B**: Aplicar Fix 2.1 (unificar a JSON puro)
  - Más estándar, evita problemas FormData vs php://input
  
- **Plan C**: Ejecutar Fix 2.2 (endpoint de prueba) para aislar problema de logging

### NO Aplicar Nivel 3 A Menos Que

- Nivel 1 y 2 fallen completamente
- Se detecten problemas estructurales más profundos
- El equipo decida refactorizar seguridad completa

---

## 8️⃣ Conclusión

El problema principal es un **conflicto entre ApiFirewall y RagProxyController** en la lectura del cuerpo de la petición:

- **ApiFirewall** consume `php://input` para todas las rutas (incluso las whitelisted)
- **RagProxyController** intenta leer `php://input` como fallback
- En PHP, `php://input` solo se puede leer **UNA VEZ**
- FormData envía datos en `$_POST`, no en `php://input`
- El código actual tiene lógica para `$_POST` pero falla el fallback

**Solución más simple:** Mover el check de `shouldSkip()` **ANTES** de consumir el input.

**Problema secundario (Marvel Agent tokens):**
- Permisos de escritura en `rag-service/storage/ai/` (hosting)
- O `usage` no llega desde OpenAI Service

**Próximos Pasos Inmediatos:**
1. Ejecutar pruebas P1, P2, P3, P4 para confirmar hipótesis
2. Esperar tu confirmación para aplicar Fix 1.1, 1.2, 1.3, 1.4
3. Validar resultados en hosting
4. Iterar si es necesario con Nivel 2

---

**Fin del Informe ZONAR** 🔍✅

*Este informe debe ser revisado por el desarrollador antes de aplicar cualquier cambio. Todos los fixes propuestos incluyen marcadores `BEGIN FIX ZONAR` / `END FIX ZONAR` para fácil localización y rollback.*
