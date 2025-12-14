# Guía de verificación de seguridad (CSP)

## Objetivo

Verificar que la **Content Security Policy con nonces** está funcionando correctamente y bloqueando ataques XSS.

---

## Verificación 1: Headers CSP

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

### Comprobaciones
- `script-src` incluye un valor `'nonce-...'` (nonce por respuesta).
- `script-src` no incluye `'unsafe-inline'`.
- `style-src` incluye `'unsafe-inline'` (limitación conocida por uso de Tailwind CDN).
- El nonce cambia entre respuestas (repite `curl` varias veces).

---

## Verificación 2: Nonce único por respuesta

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

### Comprobaciones
- Cada respuesta genera un nonce distinto.
- El nonce es base64 válido (A-Z, a-z, 0-9, `+`, `/`, `=`).
- La longitud depende de la implementación; el objetivo es que el valor sea suficientemente aleatorio.

---

## Verificación 3: Bloqueo de scripts inline

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

### Resultado esperado
```
Refused to execute inline script because it violates the following
   Content Security Policy directive: "script-src 'self' 'nonce-XXX...'". 
   Either the 'unsafe-inline' keyword, a hash ('sha256-...'), 
   or a nonce ('nonce-...') is required to enable inline execution.
```

### Comprobaciones
- El script no se ejecuta.
- Aparece un error CSP en consola.
- No aparece `alert('XSS Attack!')`.

---

## Verificación 4: Scripts con nonce válido

### Prueba en Navegador

1. **Inspecciona el código fuente** (Ctrl+U)
2. **Busca** `<script` tags
3. **Verifica** que tienen el atributo `nonce`

### Resultado esperado
```html
<!-- Permitido: tiene nonce válido -->
<script src="https://cdn.tailwindcss.com" nonce="Xy9kL2mN4pQ..."></script>
<script src="./assets/js/intro.js" defer nonce="Xy9kL2mN4pQ..."></script>
```

### Comprobaciones
- Los scripts externos tienen atributo `nonce="..."`.
- El nonce coincide con el del header CSP.
- Los scripts se ejecutan (la página carga con normalidad).

---

## Verificación 5: Inyección XSS en formularios

### Prueba Manual

Si tu aplicación tiene formularios (login, crear álbum, etc.):

1. **Intenta inyectar XSS** en un campo de texto:
   ```html
   <script>alert('XSS')</script>
   <img src=x onerror="alert('XSS')">
   <svg onload="alert('XSS')">
   ```

2. **Envía el formulario**

### Resultado esperado (observación)
- La entrada se sanitiza (p. ej., se eliminan tags HTML).
- Si algún script llegase a renderizarse, CSP lo bloquearía.
- No aparece ningún `alert`.

### Verificar con curl
```bash
# Intenta crear un álbum con XSS
curl -X POST http://localhost:8080/api/albums \
  -H "Content-Type: application/json" \
  -d '{"name":"<script>alert(\"XSS\")</script>Test Album"}'
```

---

## Verificación 6: CSP Evaluator (Google) (opcional)

### Pasos

1. **Copia el header CSP**:
   ```bash
   curl -I http://localhost:8080/ 2>&1 | grep "Content-Security-Policy:" | cut -d' ' -f2-
   ```

2. **Ve a**: https://csp-evaluator.withgoogle.com/

3. **Pega el header** y haz clic en "Check CSP"

### Resultado esperado
```
No high severity issues found
'unsafe-inline' in style-src (Tailwind CDN)
script-src uses nonces (strict)
No 'unsafe-eval'
default-src is restrictive
```

### Comprobaciones
- No aparecen avisos de alta severidad.
- Si hay avisos en `style-src` por `'unsafe-inline'`, queda documentado el motivo (Tailwind CDN).
- `script-src` usa nonces y no depende de `'unsafe-inline'`.

---

## Verificación 7: Tests automatizados

### Ejecutar suite de tests
```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --colors=always --testdox --coverage-clover coverage.xml
```

### Resultado esperado
```
PHPUnit finaliza sin fallos (puede haber tests marcados como `skipped` según condiciones).

Csp Strict (Tests\Security\CspStrict)
 - Csp with nonce does not contain unsafe inline
 - Csp without nonce falls back to unsafe inline
 - Nonce generator produces valid base64
 - Nonce generator produces unique values
 - Csp maintains allowed cdn sources
```

### Comprobaciones
- Finaliza sin `failures` ni `errors` (puede haber `skipped`).
- Los tests de CSP (`tests/Security/CspStrictTest.php`) pasan.

---

## Verificación 8: Nonce en HTML renderizado

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

### Comprobaciones
- En una misma respuesta HTML, los nonces coinciden entre scripts.
- El nonce del HTML coincide con el nonce del header CSP.
- El nonce está correctamente escapado.

---

## Verificación 9: Protección contra event handlers

### Prueba en Navegador

Intenta inyectar event handlers inline:

```javascript
// En consola, intenta crear elemento con onclick
var div = document.createElement('div');
div.innerHTML = '<button onclick="alert(\'XSS\')">Click me</button>';
document.body.appendChild(div);

// Ahora haz clic en el botón
```

### Resultado esperado
```
Refused to execute inline event handler because it violates the following 
   Content Security Policy directive: "script-src 'self' 'nonce-XXX...'".
```

### Comprobaciones
- El botón aparece pero no ejecuta el `onclick`.
- Aparece un error CSP en consola.
- No aparece `alert`.

---

## Verificación 10: Compatibilidad sin nonce (vía tests)

La compatibilidad de la política cuando no se genera nonce se verifica con tests automatizados (casos "con nonce" y "sin nonce") en `tests/Security/CspStrictTest.php`.

---

## Checklist final de verificación

| Verificación | Estado | Notas |
|--------------|--------|-------|
| Header CSP presente | ☐ | curl -I |
| Nonce único por request | ☐ | Ejecutar curl 3 veces |
| Scripts inline bloqueados | ☐ | Consola navegador |
| Scripts con nonce permitidos | ☐ | Inspeccionar código |
| XSS en formularios bloqueado | ☐ | Prueba manual |
| CSP Evaluator sin avisos de alta severidad | ☐ | Google CSP Evaluator |
| Suite de tests sin fallos | ☐ | `vendor/bin/phpunit` |
| Nonce en HTML correcto | ☐ | curl + grep |
| Event handlers bloqueados | ☐ | Consola navegador |
| Compatibilidad sin nonce (tests) | ☐ | `tests/Security/CspStrictTest.php` |

---

## Evidencias sugeridas (memoria)

### Evidencias a incluir

1. **Captura de CSP Evaluator** (sin avisos de alta severidad)
2. **Captura de consola** mostrando script bloqueado
3. **Output de tests** mostrando la suite sin fallos
4. **Curl output** mostrando headers CSP con nonces
5. **Código fuente** de `SecurityHeaders.php` con comentarios

### Puntos de discusión

1. **Por qué `unsafe-inline` en `style-src`**
   - Tailwind CDN inyecta estilos dinámicamente
   - El control principal contra XSS está en `script-src` con nonces

2. **Evidencia de funcionamiento**
   - Tests automatizados específicos de CSP
   - Verificación con Google CSP Evaluator
   - Pruebas manuales de inyección XSS

3. **Qué ocurre si no hay nonce**
   - El comportamiento "sin nonce" queda cubierto por tests (ver `tests/Security/CspStrictTest.php`)

---

## Comandos rápidos

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

## Cierre

Si las verificaciones anteriores pasan, queda evidencia práctica de que:

- `Content-Security-Policy` se emite con nonces en `script-src`
- scripts inline sin nonce se bloquean por CSP
- los scripts previstos se ejecutan cuando se sirve el nonce correcto
