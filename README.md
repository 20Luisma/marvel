# Clean Marvel Album

## Descripción general
**Clean Marvel Album** es un proyecto académico en **PHP 8.2** para gestionar álbumes y héroes del universo Marvel. Aplica **Arquitectura Limpia** para separar presentación, casos de uso, dominio e infraestructura, y se apoya en microservicios propios para integrar IA (OpenAI/RAG).  
Es un sistema real desplegado y en evolución, con foco en mantenibilidad y escalabilidad.

Repositorio (GitHub): https://github.com/20Luisma/marvel

---

## 📊 Presentación del TFM

La presentación del Trabajo Fin de Máster está disponible en formato web interactivo en la siguiente dirección:

🔗 https://contenido.creawebes.com/iamasterbigschool/presentation/tfm-presentation.html

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
- **Despliegue:** entorno propio funcional (app + microservicios)

---

## Arquitectura (Clean Architecture)
La aplicación se organiza en cuatro capas con responsabilidades claras:

- **Presentación:** controladores HTTP y vistas
- **Aplicación:** casos de uso y orquestación de servicios
- **Dominio:** entidades y contratos
- **Infraestructura:** repositorios, adaptadores externos y persistencia

La capa de dominio es independiente de frameworks y de HTTP, lo que mejora la testabilidad y reduce el acoplamiento.  
La resolución de endpoints por entorno se realiza desde `App\Config\ServiceUrlProvider` (`local` vs `hosting`).

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
- **Knowledge Base:** archivos JSON en `storage/knowledge/` con información estructurada
- **Embeddings:** vectores precalculados (OpenAI) en `storage/embeddings/` para búsqueda semántica
- **Retriever léxico:** bolsa de palabras + similitud coseno (modo por defecto)
- **Retriever vectorial:** embeddings + similitud coseno densa (activable con `RAG_USE_EMBEDDINGS=1`)
- **Fallback automático:** si falla el modo vectorial, cae al léxico sin interrumpir el flujo
- **Cliente LLM desacoplado:** comunica con `openai-service`, no directamente con OpenAI

**Endpoints:**
- `POST /rag/heroes` — Comparación de héroes Marvel usando KB de héroes
- `POST /rag/agent` — Marvel Agent: responde preguntas técnicas sobre el proyecto usando su propia KB

**Características de calidad:**
- Telemetría de latencia y modo de retrieval
- Tests unitarios completos
- Generación offline de embeddings para no gastar tokens en producción

### Heatmap Service (Python/Flask)
Registra eventos de clic para análisis de interacción. Dockerizado en VM externa (Google Cloud).

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

La aplicación está desplegada y accesible públicamente en:
https://iamasterbigschool.contenido.creawebes.com/

---

## Proceso de desarrollo y autoría
Proyecto realizado íntegramente por el autor como Trabajo Fin de Máster.  
El desarrollo fue incremental: primero el dominio y la arquitectura, luego la integración con IA y finalmente el despliegue real.  
Se ha utilizado documentación oficial y asistencia puntual de IA como apoyo, sin modificar el enfoque técnico del proyecto.

---


## Documentación adicional
- `docs/architecture/` — decisiones de arquitectura
- `docs/api/` — referencia de endpoints
- `docs/guides/` — guías técnicas
📚 La documentación técnica ampliada del proyecto se encuentra en `docs/README_TECHNICAL.md`.

---

## Documentación técnica y evidencias
- `docs/README_TECHNICAL.md` — índice técnico y guías operativas.
- `docs/evidence/README.md` — checklist de evidencias verificables y ubicacion de capturas.
- `docs/guides/demo-script.md` — guion de demo reproducible (10-15 min).
- `docs/TRACEABILITY.md` — trazabilidad requisito -> caso de uso -> implementacion -> tests.

Estas piezas elevan la credibilidad del TFM porque convierten la narrativa en verificacion objetiva.
Permiten demostrar que el sistema es ejecutable y revisable, no solo descriptivo.
El guion de demo estandariza una prueba reproducible en tiempo limitado.
La trazabilidad conecta requisitos con codigo y pruebas, lo que reduce ambiguedad.
El paquete de evidencias centraliza capturas, salidas de comandos y enlaces.
En conjunto, esto facilita que el tribunal valide el trabajo con criterios tecnicos claros.
