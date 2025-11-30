# ✅ ZONAR - Fixes Nivel 1 Aplicados

**Fecha**: 2025-11-30  
**Estado**: COMPLETADO ✅

---

## 🎯 Cambios Aplicados

### ✅ Fix 1.1: ApiFirewall - Evitar consumir php://input innecesariamente

**Archivo:** `src/Security/Http/ApiFirewall.php`  
**Líneas:** 31-37

**Cambio:**
- Movido el check `shouldSkip()` **ANTES** de `readRawInput()`
- Ahora las rutas en allowlist (`/api/rag/heroes`) NO consumen el body
- Permite que RagProxyController pueda leer el body posteriormente

**Impacto esperado:**
- ✅ Resuelve error 500 "El cuerpo de la petición está vacío" en `/api/rag/heroes`

---

### ✅ Fix 1.2: RagProxyController - Usar RequestBodyReader (cache)

**Archivo:** `src/Controllers/RagProxyController.php`  
**Líneas:** 32-56

**Cambio:**
- Reemplazado `file_get_contents('php://input')` por `\Src\Http\RequestBodyReader::getRawBody()`
- Ahora reutiliza el body cacheado por RequestBodyReader
- Doble fallback: primero `$_POST` (FormData), luego RequestBodyReader

**Impacto esperado:**
- ✅ Evita error de "cuerpo vacío" incluso si ApiFirewall leyó el stream primero
- ✅ Compatible con FormData y JSON puro

---

### ✅ Fix 1.3 + 1.4: OpenAiHttpClient - Logging mejorado con diagnóstico

**Archivo:** `rag-service/src/Application/Clients/OpenAiHttpClient.php`  
**Líneas:** 168-228

**Cambios:**
1. Añadido `error_log()` cuando NO hay `usage` en la respuesta
2. Verificación explícita de permisos de escritura en `$logDir`
3. Error logging si falla `mkdir()` o `file_put_contents()`
4. Success logging cuando se escriben tokens correctamente

**Impacto esperado:**
- ✅ Permite diagnosticar por qué Marvel Agent no logea tokens en hosting
- ✅ Información clara en error_log del servidor
- ✅ Detecta problemas de permisos automáticamente

---

## 📋 Script de Permisos para Hosting

**Archivo:** `zonar_fix_permisos.sh`

**Uso:**
1. Subir el script al servidor de hosting
2. Editar la ruta `/path/to/clean-marvel` con la ruta real
3. Ejecutar:
   ```bash
   chmod +x zonar_fix_permisos.sh
   ./zonar_fix_permisos.sh
   ```

**Alternativamente (manual):**
```bash
# En el proyecto principal
cd /path/to/clean-marvel
mkdir -p storage/ai
chmod 755 storage/ai
touch storage/ai/tokens.log
chmod 666 storage/ai/tokens.log

# En rag-service
cd rag-service
mkdir -p storage/ai
chmod 755 storage/ai
touch storage/ai/tokens.log
chmod 666 storage/ai/tokens.log

# Verificar ownership
chown -R tuUsuario:www-data storage/ai/
```

---

## 🧪 Plan de Pruebas

### Paso 1: Subir archivos modificados a hosting

**Archivos a actualizar:**
1. `src/Security/Http/ApiFirewall.php` (Fix 1.1)
2. `src/Controllers/RagProxyController.php` (Fix 1.2)
3. `rag-service/src/Application/Clients/OpenAiHttpClient.php` (Fix 1.3 + 1.4)

**Comando sugerido (desde local):**
```bash
# Ejemplo con rsync (ajustar según tu setup)
rsync -avz src/Security/Http/ApiFirewall.php user@hosting:/path/to/clean-marvel/src/Security/Http/
rsync -avz src/Controllers/RagProxyController.php user@hosting:/path/to/clean-marvel/src/Controllers/
rsync -avz rag-service/src/Application/Clients/OpenAiHttpClient.php user@hosting:/path/to/clean-marvel/rag-service/src/Application/Clients/
```

**O via Git:**
```bash
git add src/Security/Http/ApiFirewall.php
git add src/Controllers/RagProxyController.php
git add rag-service/src/Application/Clients/OpenAiHttpClient.php
git commit -m "ZONAR Fix Nivel 1: ApiFirewall + RagProxy + TokenLogger"
git push origin main

# En hosting
git pull origin main
```

---

### Paso 2: Ejecutar script de permisos

```bash
ssh user@hosting
cd /path/to/clean-marvel
./zonar_fix_permisos.sh
```

---

### Paso 3: Limpiar caches de PHP (si aplica)

```bash
# OPcache
sudo systemctl reload php8.1-fpm  # ajustar versión PHP

# O reiniciar servidor web
sudo systemctl restart apache2
# O
sudo systemctl restart nginx
```

---

### Paso 4: Probar en producción

#### A) Crear Cómic (debe seguir funcionando)
1. Ir a https://tudominio.com/
2. Seleccionar 1-2 héroes
3. Clic en "Generar cómic"
4. Verificar:
   - ✅ Respuesta exitosa
   - ✅ Logs en `/secret-ai-metrics` muestran tokens
   - ✅ Feature: `comic_generator`

#### B) Comparar Héroes (RAG) - DEBE FUNCIONAR AHORA
1. Ir a https://tudominio.com/
2. Seleccionar **exactamente 2 héroes**
3. Clic en "Comparar héroes (RAG)"
4. Verificar:
   - ✅ **NO** error 500
   - ✅ Respuesta con comparación de héroes
   - ✅ Logs en `storage/logs/debug_rag_proxy.log` muestran:
     - `[RAG] Leído desde POST` o `[RAG] Leído desde RequestBodyReader (cache)`
     - NO debe mostrar `[RAG] ERROR: Payload vacío`
   - ✅ Logs en `/secret-ai-metrics` muestran tokens
   - ✅ Feature: `compare_heroes`

#### C) Marvel Agent / AgentIA - DEBE LOGEAR TOKENS AHORA
1. Ir a https://tudominio.com/agentia
2. Escribir una pregunta (ej: "¿Quién es Spider-Man?")
3. Enviar
4. Verificar:
   - ✅ Respuesta exitosa
   - ✅ Logs en `rag-service/storage/ai/tokens.log` tienen nueva línea
   - ✅ Feature: `marvel_agent`
   - ✅ En error_log del servidor aparece:
     - `[TOKENS] Successfully logged X tokens for feature=marvel_agent`

---

### Paso 5: Revisar logs de diagnóstico

**A) Error log del servidor**

```bash
# En hosting
tail -f /var/log/php8.1-fpm/error.log
# O
tail -f /var/log/apache2/error.log
# O
tail -f /var/log/nginx/error.log
```

**Buscar líneas ZONAR:**
- `[TOKENS] No usage found for feature=...` → OpenAI Service no devolvió `usage`
- `[TOKENS] Failed to create directory: ...` → Problema de permisos
- `[TOKENS] Directory not writable: ...` → Problema de permisos
- `[TOKENS] Failed to write to log file: ...` → Problema de permisos archivo
- `[TOKENS] Successfully logged X tokens for feature=...` → ✅ Funcionando

**B) Debug RAG Proxy log**

```bash
cat storage/logs/debug_rag_proxy.log
```

Buscar líneas recientes:
- `[RAG] Leído desde POST` → FormData procesado OK
- `[RAG] Leído desde RequestBodyReader (cache)` → Cache usado OK
- `[RAG] ERROR: Payload vacío` → ❌ AÚN HAY PROBLEMA

**C) Tokens log**

```bash
# Proyecto principal
tail -20 storage/ai/tokens.log

# RAG Service
tail -20 rag-service/storage/ai/tokens.log
```

Verificar que aparecen líneas con:
- `"feature":"comic_generator"` (proyecto principal)
- `"feature":"compare_heroes"` (rag-service)
- `"feature":"marvel_agent"` (rag-service)

---

## 🎯 Resultados Esperados

### ✅ Escenario Ideal

| Funcionalidad | Respuesta | Logs de tokens | Status |
|---------------|-----------|----------------|--------|
| Crear Cómic | ✅ Funciona | ✅ Se registran | ✅ OK |
| Comparar Héroes (RAG) | ✅ Funciona | ✅ Se registran | ✅ **ARREGLADO** |
| Marvel Agent | ✅ Funciona | ✅ Se registran | ✅ **ARREGLADO** |

### ⚠️ Si algo falla

**Escenario 1: RAG sigue dando error 500**

*Posibles causas:*
- Archivos no se subieron correctamente
- Cache de PHP no se limpió
- Hay otro middleware consumiendo el body

*Diagnóstico:*
```bash
# Verificar que los archivos tienen los cambios
grep -n "ZONAR FIX 1.1" src/Security/Http/ApiFirewall.php
grep -n "ZONAR FIX 1.2" src/Controllers/RagProxyController.php

# Revisar debug_rag_proxy.log
tail -50 storage/logs/debug_rag_proxy.log
```

---

**Escenario 2: Marvel Agent no logea tokens**

*Posibles causas:*
- Permisos de `rag-service/storage/ai/`
- OpenAI Service no devuelve `usage` en la respuesta

*Diagnóstico:*
```bash
# Verificar permisos
ls -la rag-service/storage/ai/

# Revisar error_log
tail -100 /var/log/php8.1-fpm/error.log | grep TOKENS

# Si dice "No usage found":
# → OpenAI Service no está devolviendo usage
# → Ir a openai-service y verificar su respuesta
```

---

**Escenario 3: Comparar Héroes funciona pero no logea tokens**

*Causa probable:*
- Mismo problema que Marvel Agent (permisos o `usage` no llega)

*Solución:*
- Aplicar mismo diagnóstico que Escenario 2

---

## 📊 Comparativa: Antes vs Después

### Antes de ZONAR Nivel 1

```
┌─────────────────┬──────────────┬─────────────┐
│  Funcionalidad  │  Local       │  Hosting    │
├─────────────────┼──────────────┼─────────────┤
│ Crear Cómic     │  ✅ + tokens │  ✅ + tokens│
│ Comparar (RAG)  │  ✅ + tokens │  ❌ Error500│
│ Marvel Agent    │  ✅ + tokens │  ⚠️ sin tok │
└─────────────────┴──────────────┴─────────────┘
```

### Después de ZONAR Nivel 1

```
┌─────────────────┬──────────────┬─────────────┐
│  Funcionalidad  │  Local       │  Hosting    │
├─────────────────┼──────────────┼─────────────┤
│ Crear Cómic     │  ✅ + tokens │  ✅ + tokens│
│ Comparar (RAG)  │  ✅ + tokens │  ✅ + tokens│ ← FIX
│ Marvel Agent    │  ✅ + tokens │  ✅ + tokens│ ← FIX
└─────────────────┴──────────────┴─────────────┘
```

---

## 🔧 Si Necesitas Nivel 2

Si después de aplicar Nivel 1 y verificar permisos AÚN hay problemas:

**Opción A:** Unificar frontend a JSON puro (Fix 2.1 del informe)
- Elimina dependencia de FormData
- Más estándar para APIs REST

**Opción B:** Crear endpoint de diagnóstico (Fix 2.2 del informe)
- Valida que TokenLogger funciona aisladamente
- Útil para aislar el problema

**Avísame y genero los diffs para Nivel 2** 🚀

---

## 📝 Notas Importantes

1. **Backup antes de modificar en hosting:**
   ```bash
   cp src/Security/Http/ApiFirewall.php src/Security/Http/ApiFirewall.php.backup
   cp src/Controllers/RagProxyController.php src/Controllers/RagProxyController.php.backup
   ```

2. **Los cambios son compatibles con local:**
   - RequestBodyReader ya existe y cachea
   - `shouldSkip()` ya existía, solo cambió orden
   - Los error_log() NO afectan rendimiento

3. **Rollback fácil:**
   - Todos los cambios tienen marcadores `BEGIN ZONAR FIX` / `END ZONAR FIX`
   - Puedes buscar por "ZONAR" para encontrarlos
   - O restaurar desde backup/git

4. **Monitoreo continuo:**
   - Revisar error_log periódicamente primeros días
   - Los `[TOKENS] Successfully logged` indican que todo va bien
   - Si ves muchos `[TOKENS] No usage found` → problema en OpenAI Service

---

## ✅ Checklist de Deployment

- [ ] Archivos modificados subidos a hosting
- [ ] Script de permisos ejecutado
- [ ] Cache de PHP limpiado
- [ ] Servidor web reiniciado (opcional pero recomendado)
- [ ] Prueba: Crear Cómic → ✅
- [ ] Prueba: Comparar Héroes → ✅
- [ ] Prueba: Marvel Agent → ✅
- [ ] Revisado error_log → sin errores ZONAR
- [ ] Revisado debug_rag_proxy.log → "Leído desde..."
- [ ] Revisado tokens.log → líneas nuevas con features correctos
- [ ] Dashboard `/secret-ai-metrics` → muestra tokens de todas las features

---

**Fin del documento - ZONAR Nivel 1 Aplicado** ✅

*Si todo va bien, en 10 minutos tendrás RAG funcionando y tokens logeando en hosting* 🎉
