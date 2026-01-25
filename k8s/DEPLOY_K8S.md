# Despliegue en Kubernetes (Clean Marvel Album)

## Alcance y contexto

Esta guía describe el despliegue en Kubernetes de la aplicación principal y de los microservicios de IA. Los manifiestos proporcionados están orientados a despliegues de demostración y a documentación técnica.

### Importante: desarrollo vs producción

La configuración está orientada a desarrollo y pruebas. Para consideraciones adicionales de despliegue operativo, consulta:

- `k8s/PRODUCTION_CONSIDERATIONS.md` - Consideraciones adicionales
- `k8s/SECURITY_HARDENING.md` - Hardening de seguridad

---

## Arquitectura desplegada

Se asume un clúster con Ingress Controller NGINX y acceso a un registro de contenedores. Todos los manifiestos residen en `k8s/` y usan etiquetas `app` + `tier` para selección coherente de pods.

## 1) Construcción de imágenes Docker

Use las etiquetas de los manifiestos (`:latest` por defecto) o sustituya por tags inmutables (`:sha-<commit>`, `:vX.Y.Z`):

- **Aplicación principal** (Usando el `Dockerfile` de la raíz):

```bash
docker build -t 20luisma/clean-marvel:latest .
```

- **openai-service** (`openai-service/Dockerfile`):
```bash
docker build -t 20luisma/openai-service:latest openai-service
```

- **rag-service** (`rag-service/Dockerfile`):
```bash
docker build -t 20luisma/rag-service:latest rag-service
```

Publique las imágenes en su registro:
```bash
docker push 20luisma/clean-marvel:latest
docker push 20luisma/openai-service:latest
docker push 20luisma/rag-service:latest
```

## 2) Configuración de ConfigMaps y Secrets

- **ConfigMaps**: `clean-marvel-config`, `rag-service-config`, `openai-service-config` contienen URLs internas, flags y parámetros no sensibles.
- **Secrets**: `clean-marvel-secrets` agrupa claves (`INTERNAL_API_KEY`, `OPENAI_API_KEY`, tokens externos). Todos los valores vienen como placeholders `CHANGEME`; sustitúyalos antes del despliegue o genere el Secret con `kubectl create secret ...` y elimine la sección `Secret` del YAML.

## 3) Aplicación de manifiestos

Ejecute desde la raíz:
```bash
kubectl apply -f k8s/clean-marvel-deployment.yaml
kubectl apply -f k8s/clean-marvel-service.yaml
kubectl apply -f k8s/rag-service-deployment.yaml
kubectl apply -f k8s/rag-service-service.yaml
kubectl apply -f k8s/openai-service-deployment.yaml
kubectl apply -f k8s/openai-service-service.yaml
kubectl apply -f k8s/ingress.yaml
```

Comprobaciones recomendadas:
```bash
kubectl get pods -l app=clean-marvel
kubectl get pods -l app=rag-service
kubectl get pods -l app=openai-service
kubectl get svc,ing
kubectl rollout status deploy/clean-marvel
kubectl rollout status deploy/rag-service
kubectl rollout status deploy/openai-service
```

## 4) Validación funcional

1. Asigne el dominio en DNS o en `/etc/hosts` (`clean-marvel.local` apunta al Load Balancer del Ingress).  
2. Verifique la aplicación web:  
   ```bash
   curl -I http://clean-marvel.local/
   ```  
3. Verifique microservicios vía Ingress (las rutas se reescriben internamente):  
   ```bash
   curl -X POST http://clean-marvel.local/api/rag/heroes -H "X-Internal-Signature: ..." ...
   curl -X POST http://clean-marvel.local/api/openai/v1/chat -H "X-Internal-Signature: ..." ...
   ```  
4. Para depuración puntual:  
   ```bash
   kubectl port-forward svc/clean-marvel 8080:80
   kubectl port-forward svc/rag-service 8082:80
   kubectl port-forward svc/openai-service 8081:8081
   ```

## 5) Evaluación y Alcance del Despliegue

### Implementación actual

Esta configuración de Kubernetes demuestra:

1. **Arquitectura de Microservicios**: Separación clara entre frontend y servicios AI backend
2. **Orquestación Correcta**: Deployments escalables (2 réplicas), Services `ClusterIP`, Ingress NGINX con path rewriting
3. **Gestión de Configuración**: ConfigMaps para parámetros no sensibles, Secrets para credenciales
4. **Health Monitoring**: Liveness y Readiness probes configuradas en todos los contenedores
5. **Resource Management**: Requests y limits definidos para CPU/memoria
6. **Etiquetado Consistente**: Sistema de labels (`app`, `tier`) para políticas y observabilidad

### Validación académica

El despliegue resulta adecuado para:
- Demostración académica
- Pruebas de concepto y desarrollo en cluster local (minikube, kind, k3s)

### Limitaciones conocidas (entorno de desarrollo)

Para transparencia técnica, se identifican las siguientes áreas que requerirían mejora en producción:

| Aspecto | Estado Actual | Mejora para Producción |
|---------|---------------|------------------------|
| **Secrets** | Placeholders en YAML | Sealed Secrets / External Secrets |
| **TLS/HTTPS** | No configurado | cert-manager + Let's Encrypt |
| **Network Policies** | No implementadas | Segmentación de red por tiers |
| **Image Tags** | `:latest` | Tags inmutables (`v1.2.3` o SHA) |
| **Healthchecks** | TCP/HTTP básicos | Endpoints dedicados `/health`, `/ready` |
| **Rolling Updates** | Defaults de K8s | Estrategia explícita con maxUnavailable |
| **Disaster Recovery** | Sin PodDisruptionBudget | PDB para alta disponibilidad |
| **Observability** | Logs básicos | Prometheus/Grafana, tracing distribuido |

Consultar `k8s/PRODUCTION_CONSIDERATIONS.md` para guías detalladas de cada mejora.

### Pipeline recomendado

```bash
# 1. Validación de código
composer test
vendor/bin/phpstan analyse

# 2. Build y tageo de imágenes (usar tags semánticos)
docker build -f Dockerfile.clean-marvel -t 20luisma/clean-marvel:v1.0.0 .
docker push 20luisma/clean-marvel:v1.0.0

# 3. Despliegue en Kubernetes
kubectl apply -f k8s/

# 4. Validación de salud
kubectl rollout status deploy/clean-marvel
kubectl get pods -l app=clean-marvel
```

### Notas importantes

- **Opcionalidad**: El despliegue en Kubernetes es completamente opcional. La aplicación sigue operando en entornos locales y hosting tradicionales.
- **Host del Ingress**: `clean-marvel.local` es un placeholder. En producción ajustar al dominio real.
- **Secrets**: Los valores `CHANGEME-*` deben sustituirse por credenciales reales antes del despliegue (ver sección 2).
- **Namespace**: Por defecto usa `default`. En producción usar namespaces dedicados (ver [PRODUCTION_CONSIDERATIONS.md](./PRODUCTION_CONSIDERATIONS.md#namespaces-y-aislamiento)).
