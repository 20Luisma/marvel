# 🪞 Estrategia de Mirroring y Entorno de Staging

Este documento detalla la arquitectura de "espejo" (Mirroring) implementada en el proyecto para garantizar la paridad entre los entornos de desarrollo local, pruebas (Staging) y producción.

## 🚀 Concepto de "Espejo 100% Real"

El objetivo de esta estrategia es que el código sea **agnóstico al entorno**. Ningún archivo de código fuente (`.php`, `.js`, etc.) debe contener URLs o rutas absolutas de un servidor específico. 

La aplicación debe ser "ciega" y adaptarse dinámicamente según la configuración del archivo `.env`.

### Las 3 Caras del Espejo

| Entorno | URL Base App | Microservicio OpenAI | Microservicio RAG |
| :--- | :--- | :--- | :--- |
| **Local** | `localhost:8080` | `localhost:8081` | `localhost:8082` |
| **Staging** | `staging.contenido...` | `openai-staging...` | `rag-staging...` |
| **Producción**| `iamasterbigschool...` | `openai-service...` | `rag-service...` |

---

## 🛠️ Implementación Técnica

### 1. Resolución Automática de URLs
Se utiliza la clase `App\Config\ServiceUrlProvider` para centralizar la resolución de endpoints. El flujo de decisión es:
1. **Prioridad Máxima:** Si el `.env` define una URL (ej: `RAG_SERVICE_URL`), se usa esa.
2. **Detección por Dominio:** Si el `.env` está vacío, el sistema mira el `HTTP_HOST`:
   - Si contiene `staging`, activa el modo Staging.
   - Si contiene `localhost`, activa el modo Local.
   - Por defecto, activa el modo Hosting (Producción).

### 2. Microservicios Desacoplados
Cada microservicio (`openai-service`, `rag-service`) tiene su propio subdominio y su propio archivo `.env`. Esto evita "fugas" de datos. Si estás en Staging, el Agente IA hablará exclusivamente con el RAG de Staging.

### 3. Registro de Métricas Unificado
Para que el Dashboard de IA funcione en Staging igual que en Producción, se han configurado rutas de log cruzadas en el `.env`:
- El `rag-service` de Staging escribe sus tokens en la carpeta `storage/ai/` de la aplicación principal de Staging mediante la variable `AI_TOKENS_LOG_PATH` y `TOKENS_LOG_PATH`.

---

## 🔄 Ciclo de Vida del Desarrollo (SDLC Profesional)

Para trabajar como un ingeniero senior en las mejores empresas tecnológicas (FAANG/MAANG), seguimos este ciclo sistematizado:

| Fase | Entorno | Propósito | URL |
| :--- | :--- | :--- | :--- |
| **1. Desarrollo** | **Local** | Programación rápida y tests unitarios. | `localhost:8080` |
| **2. Validación** | **Staging** | Prueba de integración real en la nube ("Espejo"). | `staging.creawebes...` |
| **3. Lanzamiento** | **Producción** | Entrega oficial y estable al usuario. | `iamasterbigschool...` |

### Paso a paso para un nuevo cambio (Ej: Agente de Series)

1.  **Exploración Local:** Programas el nuevo módulo de IA. El código auto-resuelve los servicios a `localhost`.
2.  **Validación en la Nube:** Haces push a una rama `feature/` (ej: `feature/series-agent`). GitHub Actions lo despliega automáticamente en Staging.
3.  **Auditoría de Espejo:** Verificas que el agente responde correctamente bajo el servidor real (Hostinger) y que los logs de IA se registran en el dashboard de Staging.
4.  **Merge a Main (Producción):** Creas un Pull Request. Una vez aprobado, haces el merge a `main`. El mismo código se despliega en la web oficial y ahora, al detectar el host de producción, utiliza automáticamente los recursos VIP.

### 🛡️ Beneficios de este flujo
- **Cero Riesgos:** La rama `main` siempre es estable.
- **Trazabilidad:** Cada cambio en producción tiene un PR y una validación previa.
- **Eficiencia:** No hay configuraciones manuales tras el despliegue. El código es inteligente.

---

## ⚠️ Reglas de Oro para el Agente IA

Si eres una IA trabajando en este proyecto, **NUNCA**:
1. Escribas una URL que contenga `.creawebes.com` dentro de un archivo `.php`.
2. Uses rutas absolutas como `/home/u968396048/...` en el código fuente.
3. Modifiques el archivo `marvel-agent.php` para apuntar a un servidor fijo.

**SIEMPRE**:
1. Usa `$_ENV` o `getenv()` para obtener rutas.
2. Usa rutas relativas (`../../storage/...`) cuando sea posible.
3. Mantén la lógica de `ServiceUrlProvider` para que el "espejo" no se rompa.

---

## 📊 Evidencias del Espejo
Puedes verificar el funcionamiento del espejo accediendo al panel de **Métricas de IA** en cada entorno. Si el Agente IA (`marvel_agent`) aparece registrado con latencia real, la conexión es correcta y el espejo está operativo.
