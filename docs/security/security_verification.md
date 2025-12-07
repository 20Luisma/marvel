# 🔒 Guía de Verificación de Seguridad CSP

## 🎯 Objetivo

Verificar que la **Content Security Policy con nonces** está funcionando correctamente y bloqueando ataques XSS.

---

## ✅ Verificación 1: Headers CSP

### Comando
```bash
curl -I http://localhost:8080/ | grep -i content-security-policy
```

### Resultado Esperado
```
Content-Security-Policy: default-src 'self'; 
  img-src 'self' data: blob: https:; 
  media-src 'self' data: blob: https:; 
  style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; 
  font-src 'self' https://fonts.gstatic.com https://r2cdn.perplexity.ai data:; 
  script-src 'self' 'nonce-XXXXX...' https://cdn.tailwindcss.com https://cdn.jsdelivr.net; 
  connect-src 'self' https: http://localhost:8080 http://localhost:8081 http://localhost:8082; 
  frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com; 
  frame-ancestors 'self'
```

### ✅ Verificar
- ✅ `script-src` tiene `'nonce-XXXXX'` (nonce único)
- ✅ `script-src` NO tiene `'unsafe-inline'`
- ✅ `style-src` tiene `'unsafe-inline'` (Tailwind CDN)
- ✅ Nonce cambia en cada request (ejecuta curl varias veces)

---

## ✅ Verificación 2: Nonce Único por Request

### Comandos
```bash
# Request 1
curl -I http://localhost:8080/ 2>&1 | grep "script-src" | grep -o "nonce-[^']*"

# Request 2
curl -I http://localhost:8080/ 2>&1 | grep "script-src" | grep -o "nonce-[^']*"

# Request 3
curl -I http://localhost:8080/ 2>&1 | grep "script-src" | grep -o "nonce-[^']*"
```

### Resultado Esperado
```
nonce-Xy9kL2mN4pQrS8tU3vW5xY==
nonce-A1b2C3d4E5f6G7h8I9j0K1==
nonce-Z9y8X7w6V5u4T3s2R1q0P9==
```

### ✅ Verificar
- ✅ Cada request genera un nonce DIFERENTE
- ✅ Nonces son base64 válidos (caracteres A-Z, a-z, 0-9, +, /, =)
- ✅ Longitud aproximada de 24 caracteres (128 bits)

---

## ✅ Verificación 3: Bloqueo de Scripts Inline

### Prueba en Navegador

1. **Abre DevTools** (F12)
2. **Ve a la pestaña Console**
3. **Ejecuta este código malicioso**:

```javascript
// Intento de XSS - DEBE SER BLOQUEADO
var script = document.createElement('script');
script.innerHTML = "alert('XSS Attack!')";
document.body.appendChild(script);
```

### Resultado Esperado
```
❌ Refused to execute inline script because it violates the following 
   Content Security Policy directive: "script-src 'self' 'nonce-XXX...'". 
   Either the 'unsafe-inline' keyword, a hash ('sha256-...'), 
   or a nonce ('nonce-...') is required to enable inline execution.
```

### ✅ Verificar
- ✅ El script NO se ejecuta
- ✅ Aparece error CSP en consola
- ✅ NO aparece alert('XSS Attack!')

---

## ✅ Verificación 4: Scripts con Nonce Válido

### Prueba en Navegador

1. **Inspecciona el código fuente** (Ctrl+U)
2. **Busca** `<script` tags
3. **Verifica** que tienen el atributo `nonce`

### Resultado Esperado
```html
<!-- ✅ PERMITIDO - tiene nonce válido -->
<script src="https://cdn.tailwindcss.com" nonce="Xy9kL2mN4pQ..."></script>
<script src="./assets/js/intro.js" defer nonce="Xy9kL2mN4pQ..."></script>
```

### ✅ Verificar
- ✅ Scripts externos tienen atributo `nonce="..."`
- ✅ El nonce coincide con el del header CSP
- ✅ Scripts se ejecutan correctamente (página funciona)

---

## ✅ Verificación 5: Inyección XSS en Formularios

### Prueba Manual

Si tu aplicación tiene formularios (login, crear álbum, etc.):

1. **Intenta inyectar XSS** en un campo de texto:
   ```html
   <script>alert('XSS')</script>
   <img src=x onerror="alert('XSS')">
   <svg onload="alert('XSS')">
   ```

2. **Envía el formulario**

### Resultado Esperado
- ✅ Input es sanitizado (tags HTML removidos)
- ✅ Si algún script pasa sanitización, CSP lo bloquea
- ✅ NO aparece ningún alert

### Verificar con curl
```bash
# Intenta crear un álbum con XSS
curl -X POST http://localhost:8080/api/albums \
  -H "Content-Type: application/json" \
  -d '{"name":"<script>alert(\"XSS\")</script>Test Album"}'
```

---

## ✅ Verificación 6: CSP Evaluator (Google)

### Pasos

1. **Copia el header CSP**:
   ```bash
   curl -I http://localhost:8080/ 2>&1 | grep "Content-Security-Policy:" | cut -d' ' -f2-
   ```

2. **Ve a**: https://csp-evaluator.withgoogle.com/

3. **Pega el header** y haz clic en "Check CSP"

### Resultado Esperado
```
✅ No high severity issues found
⚠️ 'unsafe-inline' in style-src (expected - Tailwind CDN)
✅ script-src uses nonces (strict)
✅ No 'unsafe-eval'
✅ default-src is restrictive
```

### ✅ Verificar
- ✅ Score alto (8-10/10)
- ✅ Solo warnings en `style-src` (aceptable)
- ✅ Sin errores críticos en `script-src`

---

## ✅ Verificación 7: Tests Automatizados

### Ejecutar Suite de Tests
```bash
cd /Users/admin/Desktop/Proyecto\ Marvel\ local\ y\ Hosting/clean-marvel
XDEBUG_MODE=coverage vendor/bin/phpunit --colors=always --testdox --coverage-clover coverage.xml
```

### Resultado Esperado
```
Tests: 191, Assertions: 593 - ALL PASSING ✅

Csp Strict (Tests\Security\CspStrict)
 ✔ Csp with nonce does not contain unsafe inline
 ✔ Csp without nonce falls back to unsafe inline
 ✔ Nonce generator produces valid base64
 ✔ Nonce generator produces unique values
 ✔ Csp nonce appears in both script and style directives
 ✔ Csp maintains allowed cdn sources
```

### ✅ Verificar
- ✅ 191/191 tests pasan
- ✅ 6 tests específicos de CSP pasan
- ✅ Sin errores ni warnings

---

## ✅ Verificación 8: Nonce en HTML Renderizado

### Comando
```bash
curl http://localhost:8080/ 2>&1 | grep -o 'nonce="[^"]*"' | head -3
```

### Resultado Esperado
```
nonce="Xy9kL2mN4pQrS8tU3vW5xY=="
nonce="Xy9kL2mN4pQrS8tU3vW5xY=="
nonce="Xy9kL2mN4pQrS8tU3vW5xY=="
```

### ✅ Verificar
- ✅ Todos los nonces en el HTML son IGUALES (mismo request)
- ✅ Nonce del HTML coincide con nonce del header CSP
- ✅ Nonce está correctamente escapado (sin caracteres raros)

---

## ✅ Verificación 9: Protección contra Event Handlers

### Prueba en Navegador

Intenta inyectar event handlers inline:

```javascript
// En consola, intenta crear elemento con onclick
var div = document.createElement('div');
div.innerHTML = '<button onclick="alert(\'XSS\')">Click me</button>';
document.body.appendChild(div);

// Ahora haz clic en el botón
```

### Resultado Esperado
```
❌ Refused to execute inline event handler because it violates the following 
   Content Security Policy directive: "script-src 'self' 'nonce-XXX...'".
```

### ✅ Verificar
- ✅ El botón aparece pero NO ejecuta el onclick
- ✅ Error CSP en consola
- ✅ NO aparece alert

---

## ✅ Verificación 10: Backward Compatibility

### Prueba sin Nonce

Temporalmente, comenta la generación de nonce en `public/index.php`:

```php
// TEMPORAL - solo para testing
// $cspNonce = \App\Security\Http\CspNonceGenerator::generate();
// $_SERVER['CSP_NONCE'] = $cspNonce;
// SecurityHeaders::apply($cspNonce);
SecurityHeaders::apply(null); // Sin nonce
```

### Verificar Header
```bash
curl -I http://localhost:8080/ | grep "script-src"
```

### Resultado Esperado
```
script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com ...
```

### ✅ Verificar
- ✅ Fallback a `'unsafe-inline'` funciona
- ✅ Página sigue funcionando
- ✅ **IMPORTANTE**: Restaura el código después de la prueba

---

## 📊 Checklist Final de Verificación

| Verificación | Estado | Notas |
|--------------|--------|-------|
| Header CSP presente | ☐ | curl -I |
| Nonce único por request | ☐ | Ejecutar curl 3 veces |
| Scripts inline bloqueados | ☐ | Consola navegador |
| Scripts con nonce permitidos | ☐ | Inspeccionar código |
| XSS en formularios bloqueado | ☐ | Prueba manual |
| CSP Evaluator score alto | ☐ | Google CSP Evaluator |
| 191 tests pasando | ☐ | PHPUnit |
| Nonce en HTML correcto | ☐ | curl + grep |
| Event handlers bloqueados | ☐ | Consola navegador |
| Backward compatibility OK | ☐ | Test sin nonce |

---

## 🎓 Para tu Máster

### Evidencias a Incluir

1. **Screenshot de CSP Evaluator** mostrando score 8-10/10
2. **Screenshot de consola** mostrando script bloqueado
3. **Output de tests** mostrando 191/191 passing
4. **Curl output** mostrando headers CSP con nonces
5. **Código fuente** de `SecurityHeaders.php` con comentarios

### Argumentos de Defensa

1. **"¿Por qué unsafe-inline en style-src?"**
   - Tailwind CDN inyecta estilos dinámicamente
   - Estilos NO son vector de XSS (solo scripts)
   - Protección crítica está en `script-src` con nonces

2. **"¿Cómo garantizas que funciona?"**
   - 6 tests automatizados específicos de CSP
   - Verificación con Google CSP Evaluator
   - Pruebas manuales de inyección XSS

3. **"¿Qué pasa si falla el nonce?"**
   - Backward compatible: fallback a `unsafe-inline`
   - Tests verifican ambos escenarios
   - Logs de seguridad registran intentos

---

## 🚀 Comandos Rápidos

```bash
# Verificación completa en un comando
echo "=== CSP Header ===" && \
curl -I http://localhost:8080/ 2>&1 | grep "Content-Security-Policy:" && \
echo -e "\n=== Nonce Único ===" && \
curl -I http://localhost:8080/ 2>&1 | grep -o "nonce-[^']*" && \
curl -I http://localhost:8080/ 2>&1 | grep -o "nonce-[^']*" && \
echo -e "\n=== Tests ===" && \
vendor/bin/phpunit --filter CspStrict --testdox
```

---

## ✅ Conclusión

Si **TODAS** las verificaciones pasan:

🏆 **Tu sistema de seguridad CSP funciona al 100%**

- ✅ Protección XSS completa
- ✅ Nonces criptográficamente seguros
- ✅ Tests automatizados
- ✅ Backward compatible
- ✅ Listo para producción
