# Clean Marvel Album

## Descripción general
**Clean Marvel Album** es un proyecto académico en **PHP 8.2** para gestionar álbumes y héroes del universo Marvel. Aplica **Arquitectura Limpia** para separar presentación, casos de uso, dominio e infraestructura, y se apoya en microservicios propios para integrar IA (OpenAI/RAG).  
Es un sistema real desplegado y en evolución, con foco en mantenibilidad y escalabilidad.

Repositorio (GitHub): https://github.com/20Luisma/marvel

---

## 📊 Presentación del TFM

La presentación del Trabajo Fin de Máster está disponible en formato web interactivo en la siguiente dirección:

🔗 https://iamasterbigschool.contenido.creawebes.com/presentation/tfm-presentation.html

Esta presentación resume los objetivos, arquitectura, stack tecnológico, microservicios, despliegue y aprendizajes del proyecto.

---

## 📖 README Extendido

La versión extendida y visual de este README está disponible en:

🔗 https://iamasterbigschool.contenido.creawebes.com/readme

Incluye secciones adicionales sobre observabilidad, CI/CD, refactors estructurales, seguridad y más detalles técnicos del proyecto.

---

## Stack tecnológico
- **Backend:** PHP 8.2
- **Arquitectura:** Clean Architecture
- **Persistencia:** JSON en local y MySQL en hosting
- **Microservicios:** OpenAI Service, RAG Service (PHP) y Heatmap Service (Python/Flask)
- **Servicios externos:** OpenAI API
- **Control de versiones:** Git / GitHub
- **Auditoría de Código IA:** CodeRabbit (AI Code Reviewer)
- **Despliegue:** entorno propio funcional (app + microservicios)

---

## Arquitectura (Clean Architecture)
La aplicación se organiza en cuatro capas con responsabilidades claras:

- **Presentación:** controladores HTTP y vistas
- **Aplicación:** casos de uso y orquestación de servicios
- **Dominio:** entidades y contratos
- **Infraestructura:** repositorios, adaptadores externos y persistencia

La capa de dominio es independiente de frameworks y de HTTP, lo que mejora la testabilidad y reduce el acoplamiento.

### 🧩 Arquitectura Evolutiva (Últimas Mejoras Senior)
Recientemente el proyecto ha evolucionado para alcanzar un estándar de ingeniería **Senior**:
- **Skinny Controllers**: Los controladores se han vaciado de lógica de negocio, delegando toda la orquestación a la **Capa de Aplicación** (`UseCases`).
- **Abstracción de Filesystem**: Se ha implementado una `FilesystemInterface` para desacoplar el almacenamiento de imágenes del disco duro, permitiendo una migración inmediata a **AWS S3** o **Google Cloud Storage** sin tocar el código de negocio.
- **Dependency Inversion (DIP)**: Se han desacoplado los clientes de IA y almacenamiento usando interfaces, garantizando que el sistema sea agnóstico a proveedores externos.

---

---

## Microservicios

### OpenAI Service (PHP)
Gateway controlado hacia OpenAI API. Expone `POST /v1/chat` y centraliza la gestión de claves, CORS y validación de payloads.

### RAG Service (PHP) — Retrieval-Augmented Generation
Microservicio que implementa un **RAG real** (Retrieval-Augmented Generation) con arquitectura desacoplada:

**¿Qué es RAG?**  
Patrón que combina recuperación de información (Retrieval) con generación de texto (Generation). En lugar de enviar solo la pregunta al LLM, primero se buscan fragmentos relevantes en una base de conocimiento y se inyectan como contexto en el prompt.

**Flujo técnico:**
```
Pregunta → Retriever (KB) → Top-N contextos → Prompt con contexto → LLM → Respuesta
```

**Componentes implementados:**
- **Knowledge Base Master:** Información estructurada en archivos JSON que sirven de fuente de verdad.
- **RAG Local (Modo Ligero):** Búsqueda vectorial local usando embeddings JSON precalculados. Ideal para entornos aislados o de bajo consumo.
- **RAG Enterprise (Modo Cloud):** Integración con **Pinecone (Vector Database)**. Los embeddings se almacenan en la nube para máxima escalabilidad y rendimiento semántico profesional.
- **Embeddings:** Vectores de 1536 dimensiones generados con OpenAI (`text-embedding-3-small`).
- **Retriever Híbrido:** Conmutación automática entre Pinecone (Cloud) y el motor local (JSON) en caso de fallo, garantizando alta disponibilidad.
- **Cliente LLM desacoplado:** Comunicación segura con `openai-service` mediante firma HMAC.

**Endpoints:**
- `POST /rag/heroes` — Comparación de héroes Marvel usando KB de héroes
- `POST /rag/agent` — Marvel Agent: responde preguntas técnicas sobre el proyecto usando su propia KB

**Características de calidad:**
- Telemetría de latencia y modo de retrieval
- Tests unitarios completos
- Generación offline de embeddings para no gastar tokens en producción

### Heatmap Service (Python/Flask)
Registra eventos de clic para análisis de interacción. Dockerizado en VM externa (Google Cloud).

## CI/CD & Quality Gate (Filtro Quirúrgico) 🛡️

El proyecto implementa un flujo de **DevSecOps** avanzado mediante GitHub Actions, diseñado para garantizar que ninguna versión inestable llegue a producción:

- **Quality Gate (Puerta de Calidad):** Un paso obligatorio antes del despliegue que ejecuta un **Surgical E2E Test Suite**.
- **Surgical Smoke Testing:** Suite de tests críticos que validan en tiempo real:
    - Estado de las APIs vitales.
    - Conectividad y razonamiento del **Agente IA (RAG)**.
    - Integridad del Ciclo CRUD de álbumes.
    - Persistencia y sincronización de microservicios.
- **Despliegue por Promoción:** El código solo se "promociona" a Hostinger si el robot de calidad da luz verde, bloqueando automáticamente cualquier subida errónea.

### 🚀 Sentinel Deploy (Plan B) — Despliegue Local Independiente
Como alternativa ultra-rápida y resiliente al flujo de GitHub Actions, el proyecto cuenta con el sistema **Sentinel Deploy**:
- **Velocidad Extrema**: Despliegues en segundos mediante Sincronización Quirúrgica (`rsync` delta-upload).
- **Independencia**: Permite desplegar directamente desde el entorno local sin depender de las colas de GitHub Actions.
- **Control Operativo**: Scripts CLI (`bin/deploy-hostinger.sh`, `bin/rollback.sh`) para despliegue y rollback trazable.
- **Seguridad**: Validación automática de Quality Gate local y restricción de rama `main` garantizada.

---

## Funcionalidades principales
- Gestión de álbumes y héroes (dominio Marvel)
- Separación estricta de capas (Domain / Application / Infrastructure)
- Integración con microservicios de IA (OpenAI / RAG)
- Evolución de persistencia: JSON en local → MySQL en hosting
- Arquitectura preparada para crecer sin romper el dominio

---

## Estructura del proyecto
```
clean-marvel/
├── public/              # Front controller y endpoints
├── src/                 # Código principal (capas Clean Architecture)
├── views/               # Vistas de presentación
├── storage/             # Persistencia JSON en local
├── openai-service/      # Microservicio OpenAI (PHP)
├── rag-service/         # Microservicio RAG (PHP)
├── docs/                # Documentación técnica ampliada
└── tests/               # Tests
```

---

## Instalación y ejecución (local)

1) **Instalar dependencias**
```bash
composer install
```

2) **Configurar entorno**
Copiar `.env.example` a `.env` y ajustar:
- `APP_ENV=local`
- URLs de microservicios (`OPENAI_SERVICE_URL`, `RAG_SERVICE_URL`)
- Claves necesarias si se usan servicios externos

3) **Ejecutar aplicación principal**
```bash
php -S localhost:8080 -t public
```

4) **Ejecutar microservicios**
```bash
# OpenAI Service
cd openai-service
php -S localhost:8081 -t public

# RAG Service
cd rag-service
php -S localhost:8082 -t public
```



---

## Despliegue
La aplicación principal y los microservicios están desplegados en un entorno propio.  
Se mantiene la separación de servicios y la misma arquitectura que en local.  
El objetivo académico es demostrar un sistema real funcionando, no un prototipo aislado.

### Entornos Disponibles
- **Producción:** https://iamasterbigschool.contenido.creawebes.com/
- **Staging:** https://staging.contenido.creawebes.com/
- **API Docs (Swagger):** https://iamasterbigschool.contenido.creawebes.com/api/docs.html

### 🛠️ Flujo de Ingeniería Profesional (CI/CD)

Este proyecto sigue el estándar de las mejores empresas tecnológicas (FAANG/MAANG), implementando un ciclo de vida de desarrollo de software (SDLC) robusto:

1.  **Local (Laboratorio):** Desarrollo en `localhost`. El código es agnóstico y auto-detecta el entorno.
2.  **Staging (Espejo 100%):** El despliegue automático a Staging se activa en pushes a `staging`, `staging-final` y `feature/staging-final` (y también en PRs hacia `main`, según workflow). Aquí se valida la integración real del sistema tripartito (App + OpenAI + RAG) en la nube.
3.  **Producción (VIP):** El deploy a la web oficial solo ocurre tras un **Merge/Pull Request** exitoso a la rama `main`. Esto garantiza que NUNCA se suba código no probado.

> **Regla de Oro:** La rama `main` es sagrada. Solo contiene código validado en Staging.

Para más detalles, consulta la [Guía de Estrategia de Mirroring](./docs/guides/entorno-staging-mirroring.md).

---

## Proceso de desarrollo y autoría
Proyecto realizado íntegramente por el autor como Trabajo Fin de Máster.  
El desarrollo fue incremental: primero el dominio y la arquitectura, luego la integración con IA y finalmente el despliegue real.  
Se ha utilizado documentación oficial y asistencia puntual de IA como apoyo (incluyendo **CodeRabbit** para revisiones de código automáticas en Pull Requests), sin modificar el enfoque técnico del proyecto.

---

## 🛡️ Seguridad y Modo Demo

Este proyecto está diseñado como una **guía técnica y demo interactiva**, no como un sistema de producción con datos persistentes de usuario. Por ello, se han tomado decisiones de diseño específicas:

- **Reset Público (`reset-demo.php`):** El endpoint de restauración de datos es público por diseño. Esto permite que cualquier usuario que explore la demo pueda limpiar el estado y comenzar una experiencia desde cero.
- **Riesgos Aceptados:** Se reconoce el riesgo de DoS lógico (reseteos constantes), pero se acepta en favor de la usabilidad de la demo académica.
- **APIs de Observabilidad:** Los endpoints bajo `public/api/*` permanecen abiertos para facilitar la monitorización y transparencia de la demo.

> **Nota para entornos productivos:** En un sistema real, estos endpoints estarían protegidos por capas de autenticación (JWT/OAuth), Rate Limiting estricto y listas blancas de IP.

---

## Documentación adicional
- `docs/architecture/` — decisiones de arquitectura
- `docs/api/` — referencia de endpoints
- `docs/guides/` — guías técnicas
- `docs/guides/entorno-staging-mirroring.md` — Paridad de entornos y CI/CD Staging
- 🚀 `docs/FUTURE_IMPROVEMENTS.md` — **Informe de consultoría: 10 mejoras priorizadas con estimaciones**
📚 La documentación técnica ampliada del proyecto se encuentra en `docs/README_TECHNICAL.md`.

---

## Documentación técnica y evidencias
- `docs/README_TECHNICAL.md` — índice técnico y guías operativas.
- `docs/evidence/README.md` — checklist de evidencias verificables y ubicacion de capturas.
- `docs/guides/demo-script.md` — guion de demo reproducible (10-15 min).
---

## ⚖️ Aviso Legal y Atribución
- **Datos y Contenido:** Todos los datos, nombres e imágenes de los héroes utilizados en este proyecto son propiedad de **© 2026 MARVEL**. Este es un proyecto fan-made con activos descargados.
- **Propósito:** Este es un proyecto con fines **exclusivamente académicos y educativos**, desarrollado como Trabajo Final de Máster. No tiene ánimo de lucro ni objetivos comerciales.
- **Atribución:** Assets property of © 2026 MARVEL

<!-- Deployment Verified: 2026-02-14 22:58 UTC -->
