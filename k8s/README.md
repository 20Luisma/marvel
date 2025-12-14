# Kubernetes - Clean Marvel Album

## Descripción general

Este directorio contiene manifiestos de Kubernetes y guías asociadas. Está orientado a despliegues de demostración y a documentación técnica (alcance académico).

---

## Estructura del directorio

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

## Quick start

### Requisitos Previos

- Cluster de Kubernetes funcional (local o cloud)
- `kubectl` instalado y configurado
- NGINX Ingress Controller instalado
- Registro de contenedores accesible (Docker Hub, etc.)

### Despliegue (alto nivel)

```bash
# 1. Ajustar imágenes y secrets en los manifiestos (según tu registry y tu entorno)
# 2. Aplicar manifiestos
kubectl apply -f k8s/

# 3. Verificar estado
kubectl get pods,svc,ing
kubectl rollout status deployment/clean-marvel
```

Nota: el repositorio incluye Dockerfiles para `openai-service/` y `rag-service/`. Para la aplicación principal no se incluye un Dockerfile en la raíz; `k8s/DEPLOY_K8S.md` incluye un Dockerfile de referencia (no versionado) si se desea construir una imagen.

---

## Documentación

### 1. `k8s/DEPLOY_K8S.md`
Guía de despliegue y verificación.

Contenido:
- Construcción de imágenes (si se dispone de Dockerfiles)
- ConfigMaps y Secrets
- Aplicación de manifiestos
- Validación funcional
- Alcance y limitaciones

**Audiencia:** Desarrolladores, evaluadores académicos, despliegues de demo

---

### 2. `k8s/PRODUCTION_CONSIDERATIONS.md`
Consideraciones adicionales para despliegues operativos (documentado como trabajo futuro).

Contenido:
- Gestión de secrets (Sealed Secrets, External Secrets, Vault)
- Alta disponibilidad y estrategias de despliegue (RollingUpdate, PDB)
- Observabilidad (Prometheus, Grafana, Jaeger)
- Seguridad de red (NetworkPolicies)
- TLS (cert-manager)
- Gestión de imágenes (versionado, scanning, firma)
- Namespaces y aislamiento (Resource Quotas, RBAC)
- Autoescalado (HPA, VPA)
- Backup y disaster recovery (Velero)
- Checklist de pre-producción

**Audiencia:** DevOps engineers, arquitectos de sistemas, despliegues críticos

---

### 3. `k8s/SECURITY_HARDENING.md`
Hardening de seguridad para Kubernetes (documentado como trabajo futuro).

Contenido:
- Control plane security (API Server, etcd, audit)
- RBAC y gestión de identidades (service accounts, mínimo privilegio)
- Network security (NetworkPolicies, hardening de Ingress)
- Secrets management (rotación, cifrado en reposo)
- Pod security standards (PSA, OPA/Gatekeeper)
- Image security (scanning, firma con Cosign)
- Runtime security (Falco)
- Audit y compliance (logs centralizados, kube-bench)
- Data protection (mTLS, encryption at rest)
- Incident response (playbooks, forensics)

**Audiencia:** Security engineers, compliance officers, auditorías de seguridad

---

## Arquitectura desplegada

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
- **openai-service**: Microservicio PHP (proxy hacia OpenAI)
- **rag-service**: Microservicio PHP (RAG)
- **Ingress**: Enrutamiento inteligente (`/` → frontend, `/api/rag/*` → RAG, `/api/openai/*` → OpenAI)

---

## Niveles de implementación (orientativo)

### Nivel 1: demostración y desarrollo (manifiestos incluidos)

**Incluye:**
- Deployments con 2 réplicas
- Services ClusterIP
- Ingress con path rewriting
- ConfigMaps para configuración
- Secrets (con placeholders)
- Health probes básicas
- Resource limits

Uso típico:
- Demostración académica
- Pruebas en cluster local (minikube, kind)

Limitaciones:
- Secrets con placeholders en YAML (no recomendados para entornos operativos)
- Sin TLS por defecto
- Sin NetworkPolicies por defecto
- Uso de tags `:latest` en ejemplos

---

### Nivel 2: producción básica (documentado)
Referencia: `k8s/PRODUCTION_CONSIDERATIONS.md`.

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

Uso típico: entornos de staging y cargas moderadas (dependiente del contexto).

---

### Nivel 3: producción avanzada (documentado)
Referencia: `k8s/SECURITY_HARDENING.md`.

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

Uso típico: entornos con requisitos avanzados (seguridad, compliance, alta disponibilidad), fuera del alcance del Máster.

---

## Alcance actual

### Implementado en los manifiestos del repositorio
- Orquestación: Deployments, Services e Ingress.
- Configuración por servicio: ConfigMaps y Secrets (placeholders).
- Probes y resource limits (según manifiestos).

### Documentado como trabajo futuro
- Gestión segura de secrets (Sealed Secrets, External Secrets).
- TLS automático (cert-manager).
- NetworkPolicies.
- Pod Security Admission.
- Image scanning y firma.
- Runtime security (Falco).
- Observabilidad avanzada (Prometheus/Grafana/Jaeger).
- Autoescalado (HPA/VPA).

Esta separación permite distinguir lo que está incluido en el repositorio de lo que queda como extensión futura.

---

## Casos de uso (orientativos)

### Caso 1: presentación académica
1. Leer [DEPLOY_K8S.md](./DEPLOY_K8S.md)
2. Desplegar en minikube local: `minikube start && kubectl apply -f k8s/`
3. Demostrar funcionamiento con `kubectl port-forward`
4. Mencionar [PRODUCTION_CONSIDERATIONS.md](./PRODUCTION_CONSIDERATIONS.md) en sección "Trabajo Futuro"

### Caso 2: despliegue en staging
1. Leer [DEPLOY_K8S.md](./DEPLOY_K8S.md) para entender base
2. Implementar mejoras de [PRODUCTION_CONSIDERATIONS.md](./PRODUCTION_CONSIDERATIONS.md):
   - Sealed Secrets
   - cert-manager + TLS
   - NetworkPolicies básicas
3. Usar namespace dedicado (`clean-marvel-staging`)

### Caso 3: despliegue en producción (fuera de alcance)
1. Completar **todos** los checks de [PRODUCTION_CONSIDERATIONS.md - Checklist](./PRODUCTION_CONSIDERATIONS.md#checklist-de-pre-producción)
2. Implementar capas de [SECURITY_HARDENING.md](./SECURITY_HARDENING.md)
3. Ejecutar kube-bench y remediar hallazgos
4. Configurar alertas y runbooks
5. Realizar penetration testing

---

## Comparación con la aplicación principal

| Aspecto | Aplicación PHP | Kubernetes |
|---------|---------------|------------|
| **Configuración** | `.env` + archivos PHP | ConfigMaps + Secrets |
| **Seguridad** | Ver `docs/security/security.md` | Ver `k8s/SECURITY_HARDENING.md` |
| **Escalabilidad** | Vertical (más recursos) | Horizontal (más pods) + HPA |
| **Monitoring** | Sentry + logs propios | Prometheus + Grafana + Falco (documentado) |
| **Deployment** | `git pull` + reload | Rolling updates (según configuración) |
| **Networking** | Reverse proxy (Nginx/Apache) | Ingress + Service Mesh |
| **Secrets** | `.env` + gitignored | Sealed Secrets / External Secrets |

---

## Enlaces rápidos

### Documentación de este proyecto
- `k8s/DEPLOY_K8S.md`
- `k8s/PRODUCTION_CONSIDERATIONS.md`
- `k8s/SECURITY_HARDENING.md`
- `README.md` (raíz)

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

## Contribuciones

Si despliegas este proyecto en Kubernetes y encuentras mejoras o problemas:

1. **Abre un Issue** describiendo el escenario (local, cloud, distribución de K8s)
2. **Sugiere mejoras** a los manifiestos o documentación
3. **Comparte tu experiencia** (¿qué funcionó? ¿qué no?)

---

## Notas finales

- Este directorio incluye manifiestos y guías orientadas a despliegues de demostración.
- Las consideraciones de producción y hardening están documentadas como trabajo futuro.

---

## Soporte

Para preguntas sobre esta implementación de Kubernetes:
- **Documentación completa:** Ver archivos `.md` en este directorio
- **Dudas generales del proyecto:** Ver [README principal](../README.md)
- **Issues técnicos:** Abrir Issue en el repositorio

---

**Última actualización:** 2025-12-09  
**Versión:** 1.0.0  
**Autor:** Clean Marvel Album Team
