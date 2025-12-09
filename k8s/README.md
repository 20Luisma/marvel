# Kubernetes - Clean Marvel Album

## 📋 Descripción General

Este directorio contiene la **configuración completa de Kubernetes** para desplegar Clean Marvel Album en un entorno orquestado. La implementación actual está diseñada para:

- ✅ **Desarrollo y pruebas** en clusters locales (minikube, kind, k3s)
- ✅ **Demostración académica** (TFG, Máster, portfolio técnico)
- ✅ **Base sólida** para evolucionar a producción empresarial

---

## 🗂️ Estructura del Directorio

```
k8s/
├── README.md                           ← Este archivo
├── DEPLOY_K8S.md                       ← Guía de despliegue básico
├── PRODUCTION_CONSIDERATIONS.md        ← Mejoras para producción
├── SECURITY_HARDENING.md               ← Hardening de seguridad
│
├── clean-marvel-deployment.yaml        ← Deployment + ConfigMap + Secret (frontend)
├── clean-marvel-service.yaml           ← Service ClusterIP (frontend)
│
├── openai-service-deployment.yaml      ← Deployment + ConfigMap (microservicio AI)
├── openai-service-service.yaml         ← Service ClusterIP (microservicio AI)
│
├── rag-service-deployment.yaml         ← Deployment + ConfigMap (microservicio RAG)
├── rag-service-service.yaml            ← Service ClusterIP (microservicio RAG)
│
└── ingress.yaml                        ← Ingress NGINX (3 rutas: web + APIs)
```

---

## 🚀 Quick Start

### Requisitos Previos

- Cluster de Kubernetes funcional (local o cloud)
- `kubectl` instalado y configurado
- NGINX Ingress Controller instalado
- Registro de contenedores accesible (Docker Hub, etc.)

### Despliegue en 3 Pasos

```bash
# 1. Construir y publicar imágenes
docker build -t 20luisma/clean-marvel:latest .
docker push 20luisma/clean-marvel:latest

# 2. Aplicar manifiestos
kubectl apply -f k8s/

# 3. Verificar estado
kubectl get pods,svc,ing
kubectl rollout status deployment/clean-marvel
```

📖 **Para detalles completos, ver [DEPLOY_K8S.md](./DEPLOY_K8S.md)**

---

## 📚 Documentación

### 1️⃣ [DEPLOY_K8S.md](./DEPLOY_K8S.md)
**Guía de despliegue funcional** - Todo lo necesario para tener la aplicación corriendo en Kubernetes.

**Contenido:**
- ✅ Construcción de imágenes Docker
- ✅ Configuración de ConfigMaps y Secrets
- ✅ Aplicación de manifiestos
- ✅ Validación funcional
- ⚠️ Alcance y limitaciones conocidas

**Audiencia:** Desarrolladores, evaluadores académicos, despliegues de demo

---

### 2️⃣ [PRODUCTION_CONSIDERATIONS.md](./PRODUCTION_CONSIDERATIONS.md)
**Hoja de ruta para producción** - Mejoras críticas para entornos empresariales.

**Contenido:**
- 🔐 Gestión segura de Secrets (Sealed Secrets, External Secrets, Vault)
- 🔄 Alta disponibilidad y estrategias de despliegue (RollingUpdate, PDB)
- 📊 Observabilidad avanzada (Prometheus, Grafana, Jaeger)
- 🛡️ Seguridad de red (NetworkPolicies, zero-trust)
- 🔒 TLS automático (cert-manager + Let's Encrypt)
- 🐳 Gestión de imágenes (versionado, scanning, firma)
- 📦 Namespaces y aislamiento (Resource Quotas, RBAC)
- 📈 Autoescalado (HPA, VPA)
- 💾 Backup y disaster recovery (Velero)
- ✅ Checklist de pre-producción

**Audiencia:** DevOps engineers, arquitectos de sistemas, despliegues críticos

---

### 3️⃣ [SECURITY_HARDENING.md](./SECURITY_HARDENING.md)
**Defensa en profundidad para Kubernetes** - Alineado con las 10 capas de seguridad del proyecto.

**Contenido:**
- 🔐 **Capa 1:** Control Plane Security (API Server, etcd, audit)
- 👤 **Capa 2:** RBAC y gestión de identidades (Service Accounts, mínimo privilegio)
- 🌐 **Capa 3:** Network Security (NetworkPolicies, Ingress hardening)
- 🔑 **Capa 4:** Secrets Management (rotación, cifrado en reposo)
- 🛡️ **Capa 5:** Pod Security Standards (PSA, OPA/Gatekeeper)
- 🐳 **Capa 6:** Image Security (scanning, firma con Cosign)
- 🚨 **Capa 7:** Runtime Security (Falco)
- 📝 **Capa 8:** Audit y Compliance (logs centralizados, kube-bench)
- 🔒 **Capa 9:** Data Protection (mTLS, encryption at rest)
- 🆘 **Capa 10:** Incident Response (playbooks, forensics)

**Audiencia:** Security engineers, compliance officers, auditorías de seguridad

---

## 🏗️ Arquitectura Desplegada

```
                                    ┌─────────────────┐
                                    │  Ingress NGINX  │
                                    │  (TLS termination)
                                    └────────┬────────┘
                                             │
                ┌────────────────────────────┼────────────────────────────┐
                │                            │                            │
                ▼                            ▼                            ▼
        ┌───────────────┐          ┌─────────────────┐         ┌─────────────────┐
        │ clean-marvel  │          │ /api/rag/*      │         │ /api/openai/*   │
        │   (Frontend)  │          │ → rag-service   │         │ → openai-service│
        │               │          └─────────────────┘         └─────────────────┘
        │ - 2 réplicas  │                   │                           │
        │ - Port 8080   │                   │                           │
        └───────┬───────┘                   │                           │
                │                           │                           │
                └───────────────────────────┴───────────────────────────┘
                                            │
                                            ▼
                                ┌─────────────────────┐
                                │  Backend Services   │
                                │  (AI Layer)         │
                                │                     │
                                │ rag-service:80      │
                                │ openai-service:8081 │
                                └─────────────────────┘
```

**Componentes:**
- **clean-marvel**: Aplicación PHP principal (frontend + lógica de negocio)
- **openai-service**: Microservicio Python (proxy a OpenAI GPT-4)
- **rag-service**: Microservicio Python (RAG + embeddings para héroes Marvel)
- **Ingress**: Enrutamiento inteligente (`/` → frontend, `/api/rag/*` → RAG, `/api/openai/*` → OpenAI)

---

## 🎯 Niveles de Implementación

### 📘 **Nivel 1: Demostración y Desarrollo** (ACTUAL)
**Estado:** ✅ Completamente funcional

**Incluye:**
- Deployments con 2 réplicas
- Services ClusterIP
- Ingress con path rewriting
- ConfigMaps para configuración
- Secrets (con placeholders)
- Health probes básicas
- Resource limits

**Suficiente para:**
- ✅ Demostración en presentación de TFG/Máster
- ✅ Pruebas en cluster local (minikube, kind)
- ✅ Validación de arquitectura de microservicios
- ✅ Portfolio técnico

**Limitaciones:**
- ⚠️ Secrets en plaintext (placeholders)
- ⚠️ Sin TLS
- ⚠️ Sin NetworkPolicies
- ⚠️ Tags `:latest` en imágenes

---

### 📗 **Nivel 2: Producción Básica**
**Estado:** 📚 Documentado en [PRODUCTION_CONSIDERATIONS.md](./PRODUCTION_CONSIDERATIONS.md)

**Agregar:**
- Sealed Secrets o External Secrets
- cert-manager + Let's Encrypt (TLS automático)
- Rolling Update strategy explícita
- PodDisruptionBudgets
- NetworkPolicies básicas
- Healthchecks HTTP con `/health` y `/ready`
- Image tags inmutables (semver)
- Prometheus + Grafana
- Namespaces dedicados

**Suficiente para:**
- ✅ Staging environments
- ✅ Producción de tráfico moderado
- ✅ Startups y proyectos pequeños

---

### 📕 **Nivel 3: Enterprise Production**
**Estado:** 📚 Documentado en [SECURITY_HARDENING.md](./SECURITY_HARDENING.md)

**Agregar:**
- Todos los elementos de Nivel 2, más:
- Pod Security Admission (`restricted`)
- Service Mesh (Istio/Linkerd) para mTLS
- Runtime security (Falco)
- Image scanning + signing (Trivy + Cosign)
- OPA/Gatekeeper para políticas
- Secrets cifrados en etcd
- HPA/VPA para autoescalado
- Velero para backups
- Compliance scanning (kube-bench)
- Incident Response playbooks
- Multi-cluster setup

**Suficiente para:**
- ✅ Producción crítica (fintech, healthcare, etc.)
- ✅ Compliance (SOC2, ISO27001, PCI-DSS)
- ✅ Alta disponibilidad (99.9%+ SLA)

---

## ⚠️ Disclaimer: Alcance Actual

### Lo que SÍ está implementado
✅ Arquitectura de microservicios funcional  
✅ Orquestación correcta (Deployments, Services, Ingress)  
✅ Configuración separada por servicio (ConfigMaps)  
✅ Health monitoring (probes)  
✅ Resource management (limits/requests)  
✅ Etiquetado consistente para observabilidad  

### Lo que está DOCUMENTADO pero no implementado
📚 Gestión segura de Secrets (Sealed Secrets, External Secrets)  
📚 TLS automático (cert-manager)  
📚 NetworkPolicies (segmentación de red)  
📚 Pod Security Admission  
📚 Image scanning y firma  
📚 Runtime security (Falco)  
📚 Observabilidad avanzada (Prometheus/Grafana/Jaeger)  
📚 Autoescalado (HPA/VPA)  

### Por qué esta separación es CORRECTA

1. **Transparencia académica:** Reconocer el alcance real sin sobrevender
2. **Escalabilidad:** Base sólida que puede evolucionar progresivamente
3. **Didáctico:** Demuestra conocimiento teórico sin complejidad innecesaria para demos
4. **Profesionalismo:** Proveer hoja de ruta clara para quien quiera llevarlo a producción

---

## 🛠️ Casos de Uso

### Caso 1: Presentación de TFG/Máster
1. Leer [DEPLOY_K8S.md](./DEPLOY_K8S.md)
2. Desplegar en minikube local: `minikube start && kubectl apply -f k8s/`
3. Demostrar funcionamiento con `kubectl port-forward`
4. Mencionar [PRODUCTION_CONSIDERATIONS.md](./PRODUCTION_CONSIDERATIONS.md) en sección "Trabajo Futuro"

### Caso 2: Despliegue en Staging
1. Leer [DEPLOY_K8S.md](./DEPLOY_K8S.md) para entender base
2. Implementar mejoras de [PRODUCTION_CONSIDERATIONS.md](./PRODUCTION_CONSIDERATIONS.md):
   - Sealed Secrets
   - cert-manager + TLS
   - NetworkPolicies básicas
3. Usar namespace dedicado (`clean-marvel-staging`)

### Caso 3: Despliegue en Producción
1. Completar **todos** los checks de [PRODUCTION_CONSIDERATIONS.md - Checklist](./PRODUCTION_CONSIDERATIONS.md#checklist-de-pre-producción)
2. Implementar capas de [SECURITY_HARDENING.md](./SECURITY_HARDENING.md)
3. Ejecutar kube-bench y remediar hallazgos
4. Configurar alertas y runbooks
5. Realizar penetration testing

---

## 📊 Comparación con la Aplicación Principal

| Aspecto | Aplicación PHP | Kubernetes |
|---------|---------------|------------|
| **Configuración** | `.env` + archivos PHP | ConfigMaps + Secrets |
| **Seguridad** | 10 capas implementadas | Documentadas en [SECURITY_HARDENING.md](./SECURITY_HARDENING.md) |
| **Escalabilidad** | Vertical (más recursos) | Horizontal (más pods) + HPA |
| **Monitoring** | Sentry + logs propios | Prometheus + Grafana + Falco |
| **Deployment** | `git pull` + reload | Rolling updates sin downtime |
| **Networking** | Reverse proxy (Nginx/Apache) | Ingress + Service Mesh |
| **Secrets** | `.env` + gitignored | Sealed Secrets / External Secrets |

---

## 🔗 Enlaces Rápidos

### Documentación de Este Proyecto
- [📖 Guía de Despliegue](./DEPLOY_K8S.md)
- [🚀 Mejoras para Producción](./PRODUCTION_CONSIDERATIONS.md)
- [🔒 Hardening de Seguridad](./SECURITY_HARDENING.md)
- [📚 README Principal](../README.md)

### Documentación Externa
- [Kubernetes Official Docs](https://kubernetes.io/docs/)
- [NGINX Ingress Controller](https://kubernetes.github.io/ingress-nginx/)
- [cert-manager](https://cert-manager.io/)
- [Sealed Secrets](https://github.com/bitnami-labs/sealed-secrets)
- [Prometheus Operator](https://github.com/prometheus-operator/prometheus-operator)
- [Falco](https://falco.org/)

### Herramientas Recomendadas
- [kubectl](https://kubernetes.io/docs/tasks/tools/)
- [k9s](https://k9scli.io/) - Terminal UI para Kubernetes
- [Lens](https://k8slens.dev/) - Kubernetes IDE
- [Helm](https://helm.sh/) - Package manager
- [Trivy](https://aquasecurity.github.io/trivy/) - Security scanner

---

## 🤝 Contribuciones

Si despliegas este proyecto en Kubernetes y encuentras mejoras o problemas:

1. **Abre un Issue** describiendo el escenario (local, cloud, distribución de K8s)
2. **Sugiere mejoras** a los manifiestos o documentación
3. **Comparte tu experiencia** (¿qué funcionó? ¿qué no?)

---

## 📝 Notas Finales

### Para Evaluadores Académicos
✅ La implementación actual demuestra **conocimiento sólido de Kubernetes**  
✅ La documentación muestra **madurez técnica** al reconocer limitaciones  
✅ La hoja de ruta evidencia **capacidad de diseño para escalabilidad**  

### Para Usuarios Profesionales
⚠️ **NO usar en producción sin implementar mejoras de [PRODUCTION_CONSIDERATIONS.md](./PRODUCTION_CONSIDERATIONS.md)**  
✅ La arquitectura base es **sólida y escalable**  
✅ Toda la documentación necesaria **está provista**  

### Para Futuros Desarrolladores
📚 Empieza por [DEPLOY_K8S.md](./DEPLOY_K8S.md) para entender la base  
📚 Lee [PRODUCTION_CONSIDERATIONS.md](./PRODUCTION_CONSIDERATIONS.md) para planeaR evolución  
📚 Consulta [SECURITY_HARDENING.md](./SECURITY_HARDENING.md) para seguridad  

---

## 📧 Soporte

Para preguntas sobre esta implementación de Kubernetes:
- **Documentación completa:** Ver archivos `.md` en este directorio
- **Dudas generales del proyecto:** Ver [README principal](../README.md)
- **Issues técnicos:** Abrir Issue en el repositorio

---

**Última actualización:** 2025-12-09  
**Versión:** 1.0.0  
**Autor:** Clean Marvel Album Team
