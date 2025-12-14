# Requisitos y operación (Clean Marvel Album)
## 1. Objetivo del documento
Este documento describe **los requisitos técnicos, de entorno y de ejecución** del proyecto **Clean Marvel Album** junto con los microservicios **`openai-service`** y **`rag-service`**.  
Está pensado para desarrolladores que clonen el repositorio y quieran **levantarlo en local** siguiendo las convenciones del repositorio.

---

## 2. Entorno soportado

- **SO recomendado:** macOS / Linux (en Windows funciona, pero cambia el arranque de tasks).
- **PHP:** **8.2 o superior** (se recomienda un entorno con soporte completo para PHP 8.2 y herramientas de depuración)
  - extensiones necesarias:
    - `curl` (para llamar a OpenAI desde el microservicio)
    - `json`
    - `mbstring`
    - `pdo` *(opcional – para futuras persistencias)*
- **Composer:** 2.x
- **Navegador:** cualquiera moderno (Chrome, Edge, Safari)
- **Editor recomendado:** VS Code con las siguientes extensiones:
  - PHP Intelephense
  - PHP Debug (requiere Xdebug activo si querés depurar paso a paso)
  - GitLens
  - Markdown All in One
- **Configuración lista para usar:** el repo incluye `.vscode/` con tasks y launchers preconfigurados para instalar dependencias y levantar ambos servidores localmente sin pasos extra.
- **Servicios opcionales:** WAVE API (`WAVE_API_KEY`), PageSpeed Insights, ElevenLabs (`ELEVENLABS_API_KEY`), heatmap (`HEATMAP_API_BASE_URL`, `HEATMAP_API_TOKEN`), GitHub (`GITHUB_API_KEY`), Sentry y SonarCloud.

**Seguridad (resumen):** cabeceras de hardening activas, CSRF en POST críticos, rate limit en rutas sensibles, sesiones con TTL/lifetime + sellado IP/UA + anti-replay pasivo, firewall de payloads y sanitización, guard admin y logging con trace_id. Detalle y hardening futuro en `docs/security/security.md`.

---

## 3. Estructura del repositorio (técnica)

```text
clean-marvel/                # raíz de la app principal
├── public/                  # punto de entrada HTTP → :8080
│   └── index.php
├── src/
│   ├── bootstrap.php        # registro de dependencias (DIC casero)
│   ├── Shared/              # Router, EventBus, helpers
│   ├── Albums/              # caso de uso + dominio de álbumes
│   ├── Heroes/              # caso de uso + dominio de héroes
│   └── Notifications/       # eventos, listeners
│
├── openai-service/          # ⬅️ microservicio PHP independiente → :8081
│   ├── public/              # index.php + carga manual de .env
│   ├── src/                 # controller + servicio OpenAI
│   ├── composer.json        # autoload PSR-4: Creawebes\OpenAI\ → src/
│   └── .env                 # API key (no se sube)
│
├── rag-service/             # ⬅️ microservicio PHP independiente → :8082
│   ├── public/              # index.php + routing simple
│   ├── src/                 # controladores + servicios RAG y Agent (hero + marvel_agent)
│   ├── storage/             # knowledge heroes.json, embeddings y KB del agent
│   ├── bin/                 # scripts para KB/embeddings del agente (generate_agent_embeddings.php, build_marvel_agent_kb.php)
│   └── composer.json        # autoload PSR-4: Creawebes\Rag\ → src/
│
├── docs/microservicioheatmap/   # guía del microservicio Heatmap (Python/Flask + Docker)
├── docker-compose.yml           # stack auxiliar (n8n, etc.) para entornos locales
│
├── docs/                    # este archivo + diagramas
├── tests/                   # pruebas PHPUnit
├── .vscode/                 # tasks.json (servidores, QA, git)
├── composer.json
├── composer.lock
└── phpunit.xml.dist
```

**Nota:** la app principal y el microservicio tienen **composer.json separados**. Hay que hacer `composer install` en ambos si se quieren usar por separado.

---

## 4. Instalación paso a paso

### 4.1. Clonar el repo
```bash
git clone https://github.com/tu-usuario/clean-marvel.git
cd clean-marvel
```

### 4.2. Instalar dependencias del proyecto principal
```bash
composer install
```

### 4.3. Instalar dependencias del microservicio
```bash
cd openai-service
composer install
cd ..
```

### 4.4. Instalar dependencias del microservicio RAG
```bash
cd rag-service
composer install
cd ..
```

### 4.5. Crear archivos `.env` (app + microservicios)

En la raíz, parte de `/.env.example` (cópialo a `.env`):

```bash
cp .env.example .env
```

Variables principales (ver el archivo para la lista completa):
- APP/DB: `APP_ENV`, `APP_DEBUG`, `APP_URL`, `DB_HOST/DB_NAME/DB_USER/DB_PASSWORD`
- Seguridad: `ADMIN_EMAIL`, `ADMIN_PASSWORD_HASH`, `INTERNAL_API_KEY`
- IA y APIs: `OPENAI_API_KEY`, `OPENAI_MODEL`, `ELEVENLABS_API_KEY`, `WAVE_API_KEY`, `GITHUB_API_KEY`, `TMDB_API_KEY`
- Heatmap: `HEATMAP_API_BASE_URL`, `HEATMAP_API_TOKEN`
- Observabilidad: `SENTRY_DSN`, `SONAR_TOKEN` (si aplica), PSI claves, etc.

Microservicios:
- `openai-service/.env.example` → copiar a `.env` y rellenar `OPENAI_API_KEY`, `OPENAI_MODEL` (fallback JSON si falta la key).
- `rag-service/.env.example` → copiar a `.env`; opcional `OPENAI_SERVICE_URL` si no es `http://localhost:8081/v1/chat`.
- `openai-service` carga su `.env` con `putenv()` en `openai-service/public/index.php`.
- `rag-service` carga su `.env` con `putenv()` en `rag-service/src/bootstrap.php` (invocado por `rag-service/public/index.php`).

---

## 5. Levantar los servidores

### 5.1. Servidor de la app principal (8080)

Desde la raíz del proyecto (`clean-marvel/`):

```bash
php -S localhost:8080 -t public
```

Esto sirve el front + backend (router) que usa la app para manejar álbumes y héroes.

Se accede en: **http://localhost:8080**

---

### 5.2. Servidor del microservicio (8081)

En una segunda terminal:

```bash
cd openai-service
php -S localhost:8081 -t public
```

Se accede en: **http://localhost:8081**  
El endpoint que usa la app principal es: **`POST http://localhost:8081/v1/chat`**

**IMPORTANTE:** si el microservicio no está levantado, la app principal devolverá `502 Bad Gateway` o el frontend mostrará: **“La IA devolvió una estructura inesperada”.**

---

### 5.3. Servidor del microservicio RAG (8082)

En una tercera terminal:

```bash
cd rag-service
php -S localhost:8082 -t public
```

Se accede en: **http://localhost:8082/rag/heroes**  
Este servicio **consume** al microservicio OpenAI (8081), así que asegúrate de que esté activo antes de hacer peticiones.  
Si desplegás ambos servicios en hosts distintos, configura `OPENAI_SERVICE_URL` en `rag-service/.env`.

---

### 5.4. Levantar con VS Code

El repo ya trae `.vscode/tasks.json` con estas tareas:

- Iniciar servidor PHP (8080) → app principal
- Run OpenAI Service (8081) → microservicio
- Run Both (8080 + 8081) → opción para levantar ambos a la vez

Para ejecutarlo:
1. Cmd+Shift+P → "Run Task" → seleccionar la tarea de "Run Both (8080 + 8081)"
2. VS Code abrirá 2 terminales internas, una para cada servidor.

> **Nota:** por ahora el microservicio RAG (8082) se arranca manualmente con el comando anterior o creando una task adicional en VS Code.

---

## 6. Checks de calidad y seguridad mínimos

- Tests con cobertura (Clover): `composer test:coverage` (genera `coverage.xml`).
- Security check local: `bash bin/security-check.sh` (ejecuta `composer audit` y lint de sintaxis con `php -l` sobre `src/` y `tests/`).
- Análisis estático general: `vendor/bin/phpstan analyse --memory-limit=512M`.
- Auditoría de dependencias: `composer audit --no-interaction`.

El workflow `security-check.yml` corre estos pasos en cada PR/push a `main`; si falla, no se debe desplegar.

---

## 6. Microservicio `openai-service` (detalle técnico)

### 6.1. Namespace y autoload
En `openai-service/composer.json`:

```json
"autoload": {
  "psr-4": {
    "Creawebes\\OpenAI\\": "src/"
  }
}
```

Cada vez que se cree o mueva una clase en `src/` hay que ejecutar:

```bash
cd openai-service
composer dump-autoload
```

### 6.2. Punto de entrada
`openai-service/public/index.php`

- Incluye el `vendor/autoload.php`
- Carga manualmente el `.env` usando `putenv()`
- Llama al router del microservicio
- Devuelve SIEMPRE JSON

### 6.3. Controlador principal
Ubicado en `openai-service/src/Controller/OpenAIController.php`.  
Responsabilidades:
- leer el body JSON de la petición
- validar que vengan `messages`
- delegar en el caso de uso `Application/UseCase/GenerateContent`
- devolver JSON `{ "ok": true|false, ... }`

### 6.4. Servicio de OpenAI
`openai-service/src/Application/UseCase/GenerateContent.php` + `openai-service/src/Infrastructure/Client/OpenAiClient.php`

Responsabilidades:

1. `GenerateContent` limpia/valida contenido, extrae el `content` de la respuesta del LLM y genera fallback seguro si falta la clave o la respuesta.
2. `OpenAiClient` lee `OPENAI_API_KEY`, `OPENAI_API_BASE` y `OPENAI_MODEL` desde `.env`, construye la petición `POST /v1/chat/completions` (Guzzle) y lanza excepciones descriptivas en caso de error.

3. Construir la llamada real a OpenAI:

   ```php
   $ch = curl_init('https://api.openai.com/v1/chat/completions');
   // headers, body, etc.
   ```

4. Devolver **solo** el `choices[0].message.content`

5. En caso de error de cURL o HTTP → devolver mensaje controlado

### 6.5. Respuesta estándar del microservicio

- **Éxito:**
  ```json
  {
    "ok": true,
    "content": "historia generada por OpenAI..."
  }
  ```

- **Error controlado (sin matar la app):**
  ```json
  {
    "ok": false,
    "error": "No se ha configurado OPENAI_API_KEY en el entorno."
  }
  ```

Esto es importante porque el frontend maneja el caso `ok=false` mostrando un mensaje de error.

### 6.6. Microservicio `rag-service` (detalle técnico)

- `public/index.php`: router súper liviano con CORS habilitado y dispatch hacia `/rag/heroes`.
- `src/bootstrap.php`: carga variables de entorno (`.env` opcional, incl. `OPENAI_SERVICE_URL`) y registra la base de conocimiento (`storage/knowledge/heroes.json`), el `HeroRetriever` y el `HeroRagService`.
- `Infrastructure/HeroJsonKnowledgeBase.php`: lee el JSON y normaliza `{ heroId, nombre, contenido }`. Si el archivo cambia en despliegues, basta con reemplazarlo.
- `Application/HeroRetriever.php`: vectoriza texto con bolsa de palabras + coseno para ordenar los héroes según la pregunta y obtiene los mejores contextos.
- `Infrastructure/VectorHeroRetriever.php`: retriever opcional basado en embeddings precalculados (`storage/embeddings/heroes.json`), con fallback automático al retriever léxico.
- `Application/HeroRagService.php`: arma un prompt narrativo (sin tablas), llama al microservicio OpenAI usando `OPENAI_SERVICE_URL` (por defecto `http://localhost:8081/v1/chat`) y devuelve la respuesta con contextos y `heroIds`.
- `Controllers/RagController.php`: expone `POST /rag/heroes` y responde `{ answer, contexts, heroIds }`, propagando errores controlados.

> Este microservicio **no** genera historias propias: solo hace RAG sobre el JSON y delega la generación al servicio de OpenAI (8081). Puedes escalarlo en otra máquina apuntando el `.env` al endpoint OpenAI correspondiente.

### 6.7. Heatmap service (Python/Flask + Docker)
- Código y guía: `docs/microservicioheatmap/` (incluye Dockerfile). En producción corre en una VM de Google Cloud (contenedor Docker).
- Variables clave: `HEATMAP_API_BASE_URL`, `HEATMAP_API_TOKEN` (la app principal proxy en `/api/heatmap/*` envía el token sin exponerlo).
- Ejemplo de build/run (ajusta ruta si cambias la ubicación):
  ```bash
  cd docs/microservicioheatmap
  docker build -t heatmap-service .
  docker run -e HEATMAP_API_TOKEN=dev-heatmap-token -p 8080:8080 heatmap-service
  ```

---

## 7. Flujo completo app → microservicio → OpenAI

1. Usuario hace clic en **“Generar cómic”** en la UI.
2. Frontend hace `POST /comics/generate` a la app principal (8080).
3. El backend de la app principal hace una petición HTTP al microservicio:
   ```text
   POST http://localhost:8081/v1/chat
   ```
4. El microservicio llama a OpenAI (usando la API key del `.env`).
5. OpenAI responde con una historia en texto.
6. El microservicio devuelve `{ ok: true, content: "..." }` a la app principal.
7. La app principal devuelve esa historia al frontend.
8. El frontend la pinta como “Historia generada”.

Si en cualquier punto hay un error (8081 apagado, API key faltante, OpenAI caído), la app muestra un mensaje de error controlado en vez de una respuesta HTML inesperada.

**Flujo “Comparar héroes (RAG)”**

1. Usuario hace clic en **“Comparar héroes (RAG)”** en la UI.
2. El frontend valida que haya ≥2 héroes seleccionados y hace `POST http://localhost:8082/rag/heroes`.
3. El microservicio RAG recupera los héroes desde su JSON, arma el prompt y llama a `http://localhost:8081/v1/chat`.
4. El microservicio OpenAI genera la respuesta narrativa y responde al RAG.
5. RAG devuelve `{ answer, contexts, heroIds }` al frontend.
6. El frontend muestra la respuesta y los contextos dentro del panel lateral.

Si 8082 no está disponible (o 8081 está caído), el panel muestra un mensaje de error controlado.

---

## 8. Comandos útiles

### 8.1. Probar el microservicio directamente (sin la app)
```bash
curl -X POST http://localhost:8081/v1/chat   -H "Content-Type: application/json"   -d '{
    "messages": [
      { "role": "system", "content": "Eres un narrador de cómics de Marvel en español." },
      { "role": "user", "content": "Crea una escena épica entre Iron Man y Capitán América." }
    ]
  }'
```

Si todo está bien, deberías ver algo tipo:

```json
{"ok":true,"content":"**Título: La Última Frontera** ... "}
```

### 8.2. Probar el microservicio RAG
```bash
curl -X POST http://localhost:8082/rag/heroes \
  -H "Content-Type: application/json" \
  -d '{
    "question": "Compara sus atributos y resume el resultado",
    "heroIds": [
      "a1a1a1a1-0001-4f00-9000-000000000001",
      "a1a1a1a1-0001-4f00-9000-000000000002"
    ]
  }'
```

Si 8081 está encendido y la knowledge base tiene esos héroes, deberías recibir algo como:

```json
{
  "answer": "Atributo | Valoración\nAtaque | ...\n\n...",
  "contexts": [...],
  "heroIds": [...]
}
```

### 8.3. Ejecutar tests
```bash
vendor/bin/phpunit --colors=always --testdox
```

### 8.4. Análisis estático
```bash
vendor/bin/phpstan analyse --memory-limit=512M
```

### 8.5. Validar composer
```bash
composer validate
```

---

## 9. Errores comunes y cómo resolverlos

### 9.1. “La IA devolvió una estructura inesperada”
- El microservicio no está levantado en 8081
- El microservicio devolvió HTML (por un warning) y no JSON
- Solución: mirar el terminal donde corrés `php -S localhost:8081 -t public` y corregir el error

### 9.2. “No se ha configurado OPENAI_API_KEY en el entorno.”
- Existe el `.env` pero no se está cargando
- Revisar que el código de `public/index.php` del microservicio tenga el bloque de `putenv()`
- Revisar que el `.env` esté en la ruta correcta: `openai-service/.env`

### 9.3. 502 Bad Gateway en el navegador
- La app principal intentó hablar con `http://localhost:8081/v1/chat` y no había nada escuchando
- Solución: levantar el microservicio

### 9.4. “Error al consultar el RAG.”
- El microservicio RAG (8082) no está levantado o devolvió error 5xx.
- El servicio de OpenAI (8081) no respondió y el RAG propagó el fallo.
- Solución: encender 8082 (y 8081), revisar el log de la terminal del RAG.

### 9.5. “Class ... not found”
- Se movió el controlador del microservicio de `src/Http/Controller` a `src/Controller` y no se ejecutó:
  ```bash
  composer dump-autoload
  ```

---

## 10. QA y Git (automatizado)

El proyecto incluye una tarea de VS Code:

```json
{
  "label": "⬆️ Git: add + commit + push (actualiza ambos README)",
  "type": "shell",
  "command": "bash",
  "args": [
    "-c",
    "cp -f clean-marvel/README.md README.md; git add -A; git commit -m \"update clean-marvel + sync README root\" || true; git push"
  ],
  "options": {
    "cwd": "${workspaceFolder}/.."
  }
}
```

Esto hace lo siguiente:
1. Copia el README de la carpeta del proyecto al README raíz
2. Hace `git add -A`
3. Hace commit con mensaje estándar
4. Hace push

Sirve para mantener el README **del proyecto** y el README **del repo raíz** sincronizados.

---

## 11. Seguridad

- No subir **`.env`**
- No subir **keys** en `tasks.json`
- No dejar `var_dump()` o `echo` en los controladores del microservicio porque rompen el JSON
- Mantener `composer.lock` para que todos tengan las mismas versiones

---

## 12. Próximos pasos (roadmap técnico)

- Reemplazar el almacenamiento JSON por **SQLite** o **MySQL** mediante repositorios
- Extraer el microservicio OpenAI a su propio repo
- Añadir autenticación básica a las rutas de administración
- Añadir tests específicos para el microservicio (mock de cURL / OpenAI)
- Dockerizar los dos servicios (8080 y 8081)

-----

**Documento generado para el proyecto Creawebes — Clean Marvel Album (actualizado, microservicio funcional).**
