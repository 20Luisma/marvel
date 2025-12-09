# Consideraciones para Producción - Kubernetes

## 📋 Índice

1. [Introducción](#introducción)
2. [Gestión Segura de Secrets](#1-gestión-segura-de-secrets)
3. [Alta Disponibilidad y Estrategias de Despliegue](#2-alta-disponibilidad-y-estrategias-de-despliegue)
4. [Observabilidad Avanzada](#3-observabilidad-avanzada)
5. [Seguridad de Red](#4-seguridad-de-red)
6. [TLS y Certificados Automáticos](#5-tls-y-certificados-automáticos)
7. [Gestión de Imágenes](#6-gestión-de-imágenes)
8. [Namespaces y Aislamiento](#7-namespaces-y-aislamiento)
9. [Autoescalado y Optimización de Recursos](#8-autoescalado-y-optimización-de-recursos)
10. [Backup y Disaster Recovery](#9-backup-y-disaster-recovery)
11. [Checklist de Pre-Producción](#checklist-de-pre-producción)

---

## Introducción

Este documento complementa [DEPLOY_K8S.md](./DEPLOY_K8S.md) con **mejoras críticas para entornos de producción**. La implementación actual es funcional y didáctica, pero requiere estas adaptaciones para cumplir estándares empresariales.

### 🎯 Objetivo

Proporcionar una **hoja de ruta clara** para evolucionar el despliegue de desarrollo/demo a un entorno production-ready, manteniendo la arquitectura base intacta.

### ⚠️ Importante

Implementar estas mejoras es **opcional** para demostraciones académicas, pero **obligatorio** para despliegues en producción con tráfico real, datos sensibles o SLAs estrictos.

---

## 1. Gestión Segura de Secrets

### 🔴 Problema Actual

```yaml
# k8s/clean-marvel-deployment.yaml (ACTUAL)
apiVersion: v1
kind: Secret
metadata:
  name: clean-marvel-secrets
type: Opaque
stringData:
  INTERNAL_API_KEY: "CHANGEME-STRONG-RANDOM"  # ❌ En repositorio
  OPENAI_API_KEY: "CHANGEME-OPENAI-KEY"      # ❌ Plaintext
```

**Riesgos:**
- ❌ Secrets en control de versiones (incluso con placeholders)
- ❌ Sin cifrado en reposo en etcd
- ❌ Sin rotación automatizada
- ❌ Exposición en logs de CI/CD

---

### ✅ Solución 1: Crear Secrets fuera del repositorio

#### Paso 1: Eliminar sección Secret del YAML

```yaml
# k8s/clean-marvel-deployment.yaml (MODIFICADO)
# Eliminar completamente el recurso Secret
# Mantener solo ConfigMaps
```

#### Paso 2: Crear Secret por línea de comando

```bash
# Generar credenciales fuertes
INTERNAL_API_KEY=$(openssl rand -base64 32)
OPENAI_API_KEY="sk-proj-..."  # Tu clave real de OpenAI

# Crear el Secret directamente en el cluster
kubectl create secret generic clean-marvel-secrets \
  --from-literal=INTERNAL_API_KEY="$INTERNAL_API_KEY" \
  --from-literal=OPENAI_API_KEY="$OPENAI_API_KEY" \
  --from-literal=ELEVENLABS_API_KEY="..." \
  --from-literal=TTS_INTERNAL_TOKEN="..." \
  --from-literal=GOOGLE_YT_API_KEY="..." \
  --from-literal=MARVEL_UPDATE_TOKEN="..." \
  --from-literal=WAVE_API_KEY="..." \
  --from-literal=PSI_API_KEY="..." \
  --from-literal=GITHUB_API_KEY="..." \
  --from-literal=SENTRY_DSN="..." \
  --from-literal=SENTRY_API_TOKEN="..." \
  --from-literal=SENTRY_ORG_SLUG="..." \
  --from-literal=SENTRY_PROJECT_SLUG="..." \
  --from-literal=HEATMAP_API_TOKEN="..." \
  --dry-run=client -o yaml | kubectl apply -f -
```

#### Paso 3: Automatizar en CI/CD

```yaml
# .github/workflows/deploy.yml (ejemplo)
- name: Create Kubernetes Secrets
  env:
    OPENAI_API_KEY: ${{ secrets.OPENAI_API_KEY }}
    INTERNAL_API_KEY: ${{ secrets.INTERNAL_API_KEY }}
  run: |
    kubectl create secret generic clean-marvel-secrets \
      --from-literal=OPENAI_API_KEY="$OPENAI_API_KEY" \
      --from-literal=INTERNAL_API_KEY="$INTERNAL_API_KEY" \
      --dry-run=client -o yaml | kubectl apply -f -
```

---

### ✅ Solución 2: Sealed Secrets (Recomendado para GitOps)

**Ventaja:** Allows almacenar secrets cifrados en Git de forma segura.

#### Instalación

```bash
# Instalar Sealed Secrets Controller
kubectl apply -f https://github.com/bitnami-labs/sealed-secrets/releases/download/v0.24.0/controller.yaml

# Instalar CLI kubeseal
brew install kubeseal  # macOS
# o
wget https://github.com/bitnami-labs/sealed-secrets/releases/download/v0.24.0/kubeseal-linux-amd64 -O kubeseal
```

#### Crear SealedSecret

```bash
# 1. Crear Secret temporal (NO commitear)
kubectl create secret generic clean-marvel-secrets \
  --from-literal=OPENAI_API_KEY="sk-..." \
  --dry-run=client -o yaml > /tmp/secret.yaml

# 2. Cifrar con kubeseal
kubeseal -f /tmp/secret.yaml -w k8s/clean-marvel-sealed-secret.yaml

# 3. Eliminar el archivo temporal
rm /tmp/secret.yaml

# 4. Commitear el SealedSecret (está cifrado)
git add k8s/clean-marvel-sealed-secret.yaml
git commit -m "Add sealed secrets"
```

**Resultado:**
```yaml
# k8s/clean-marvel-sealed-secret.yaml (SAFE TO COMMIT)
apiVersion: bitnami.com/v1alpha1
kind: SealedSecret
metadata:
  name: clean-marvel-secrets
spec:
  encryptedData:
    OPENAI_API_KEY: AgB7Y3J5cHRvMToxNT...  # ✅ Cifrado, seguro en Git
    INTERNAL_API_KEY: AgCkY3J5cHRvMToxNT...
```

---

### ✅ Solución 3: External Secrets Operator (Empresarial)

**Ventaja:** Sincroniza secrets desde gestores externos (AWS Secrets Manager, Vault, Azure Key Vault).

#### Instalación

```bash
helm repo add external-secrets https://charts.external-secrets.io
helm install external-secrets external-secrets/external-secrets -n external-secrets-system --create-namespace
```

#### Configuración con AWS Secrets Manager

```yaml
# k8s/external-secret.yaml
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: clean-marvel-secrets
spec:
  refreshInterval: 1h
  secretStoreRef:
    name: aws-secrets-manager
    kind: SecretStore
  target:
    name: clean-marvel-secrets
    creationPolicy: Owner
  data:
    - secretKey: OPENAI_API_KEY
      remoteRef:
        key: prod/clean-marvel/openai-api-key
    - secretKey: INTERNAL_API_KEY
      remoteRef:
        key: prod/clean-marvel/internal-api-key
```

---

### 🔐 Cifrado en Reposo (etcd)

Por defecto, Kubernetes almacena Secrets sin cifrar en etcd. Para producción **crítica**:

```yaml
# /etc/kubernetes/encryption-config.yaml (en el control plane)
apiVersion: apiserver.config.k8s.io/v1
kind: EncryptionConfiguration
resources:
  - resources:
      - secrets
    providers:
      - aescbc:
          keys:
            - name: key1
              secret: <BASE64_STRONG_KEY>  # 32 bytes aleatorios
      - identity: {}
```

```bash
# Generar clave de cifrado
head -c 32 /dev/urandom | base64

# Aplicar configuración (requiere reinicio del API server)
# Consultar documentación específica de tu cluster (EKS, GKE, AKS, etc.)
```

---

## 2. Alta Disponibilidad y Estrategias de Despliegue

### 🟠 Problema Actual

```yaml
# k8s/clean-marvel-deployment.yaml (ACTUAL)
spec:
  replicas: 2
  # Sin estrategia explícita
  # Sin PodDisruptionBudget
```

**Riesgos:**
- ⚠️ Posible downtime durante rolling updates
- ⚠️ Mantenimientos del cluster pueden tumbar todos los pods
- ⚠️ Sin control granular sobre el proceso de actualización

---

### ✅ Solución: Rolling Update Controlado

```yaml
# k8s/clean-marvel-deployment.yaml (MEJORADO)
apiVersion: apps/v1
kind: Deployment
metadata:
  name: clean-marvel
spec:
  replicas: 3  # ✅ Mínimo 3 para HA real
  
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxUnavailable: 0      # ✅ Nunca bajar pods antes de que nuevos estén ready
      maxSurge: 1            # ✅ Crear máximo 1 pod extra durante update
  
  minReadySeconds: 10         # ✅ Esperar 10s antes de marcar como ready
  revisionHistoryLimit: 5     # ✅ Mantener 5 versiones para rollback rápido
  
  selector:
    matchLabels:
      app: clean-marvel
      tier: frontend
  
  template:
    metadata:
      labels:
        app: clean-marvel
        tier: frontend
        version: v1.0.0  # ✅ Versionado para canary deploys
    spec:
      # ... resto igual
```

---

### ✅ PodDisruptionBudget (Evitar Downtime)

```yaml
# k8s/clean-marvel-pdb.yaml (NUEVO)
apiVersion: policy/v1
kind: PodDisruptionBudget
metadata:
  name: clean-marvel-pdb
spec:
  minAvailable: 2  # ✅ Siempre mantener 2 pods disponibles
  selector:
    matchLabels:
      app: clean-marvel
      tier: frontend

---
apiVersion: policy/v1
kind: PodDisruptionBudget
metadata:
  name: openai-service-pdb
spec:
  maxUnavailable: 1  # ✅ Permitir caída de máximo 1 pod
  selector:
    matchLabels:
      app: openai-service
      tier: backend
```

**Aplicar:**
```bash
kubectl apply -f k8s/clean-marvel-pdb.yaml
```

---

### ✅ Anti-Affinity (Distribución en Nodos)

```yaml
# k8s/clean-marvel-deployment.yaml (AGREGAR)
spec:
  template:
    spec:
      affinity:
        podAntiAffinity:
          preferredDuringSchedulingIgnoredDuringExecution:
            - weight: 100
              podAffinityTerm:
                labelSelector:
                  matchExpressions:
                    - key: app
                      operator: In
                      values:
                        - clean-marvel
                topologyKey: kubernetes.io/hostname  # ✅ No dos pods en el mismo nodo
      
      containers:
        - name: clean-marvel
          # ... resto
```

---

## 3. Observabilidad Avanzada

### 🟠 Problema Actual

```yaml
# openai-service-deployment.yaml (ACTUAL)
livenessProbe:
  tcpSocket:
    port: 8081  # ❌ Solo verifica que el puerto está abierto
```

**Riesgos:**
- ⚠️ TCP check NO valida funcionalidad real del servicio
- ⚠️ Sin métricas de performance
- ⚠️ Sin trazabilidad de errores internos

---

### ✅ Solución: Healthchecks HTTP Específicos

#### Paso 1: Crear endpoints de salud en microservicios

```python
# openai-service/app.py (AGREGAR)
from flask import jsonify
import requests
import os

@app.route('/health', methods=['GET'])
def health():
    """
    Liveness probe: verifica que el proceso está vivo.
    No debe depender de servicios externos.
    """
    return jsonify({
        "status": "healthy",
        "service": "openai-service",
        "version": "1.0.0"
    }), 200

@app.route('/ready', methods=['GET'])
def ready():
    """
    Readiness probe: verifica que el servicio está listo para tráfico.
    Valida dependencias críticas.
    """
    checks = {}
    all_ready = True
    
    # Check 1: OpenAI API key está configurada
    if not os.environ.get('OPENAI_API_KEY'):
        checks['openai_key'] = False
        all_ready = False
    else:
        checks['openai_key'] = True
    
    # Check 2: OpenAI API es alcanzable (opcional, con timeout corto)
    try:
        response = requests.get(
            'https://api.openai.com/v1/models',
            headers={'Authorization': f'Bearer {os.environ.get("OPENAI_API_KEY")}'},
            timeout=2
        )
        checks['openai_api'] = response.status_code == 200
    except:
        checks['openai_api'] = False
        all_ready = False
    
    status_code = 200 if all_ready else 503
    return jsonify({
        "status": "ready" if all_ready else "not ready",
        "checks": checks
    }), status_code

@app.route('/metrics', methods=['GET'])
def metrics():
    """
    Endpoint para Prometheus (formato simple).
    """
    # Implementar métricas básicas o usar prometheus_flask_exporter
    return f"""
# HELP openai_requests_total Total requests to OpenAI
# TYPE openai_requests_total counter
openai_requests_total 42

# HELP openai_request_duration_seconds Request duration
# TYPE openai_request_duration_seconds histogram
openai_request_duration_seconds_sum 120.5
openai_request_duration_seconds_count 42
""", 200, {'Content-Type': 'text/plain'}
```

```python
# rag-service/app.py (AGREGAR - similar)
@app.route('/health', methods=['GET'])
def health():
    return jsonify({"status": "healthy", "service": "rag-service"}), 200

@app.route('/ready', methods=['GET'])
def ready():
    checks = {}
    
    # Validar conexión a openai-service
    try:
        openai_url = os.environ.get('OPENAI_SERVICE_URL')
        response = requests.get(f"{openai_url.replace('/v1/chat', '/health')}", timeout=2)
        checks['openai_service'] = response.status_code == 200
    except:
        checks['openai_service'] = False
        return jsonify({"status": "not ready", "checks": checks}), 503
    
    return jsonify({"status": "ready", "checks": checks}), 200
```

#### Paso 2: Actualizar Probes

```yaml
# k8s/openai-service-deployment.yaml (MEJORADO)
livenessProbe:
  httpGet:
    path: /health
    port: 8081
    httpHeaders:
      - name: X-Health-Check
        value: "liveness"
  initialDelaySeconds: 15
  periodSeconds: 20
  timeoutSeconds: 5       # ✅ Timeout explícito
  failureThreshold: 3
  successThreshold: 1

readinessProbe:
  httpGet:
    path: /ready
    port: 8081
  initialDelaySeconds: 5
  periodSeconds: 10
  timeoutSeconds: 3
  failureThreshold: 3
  successThreshold: 1     # ✅ Cuántas veces debe pasar antes de ready

startupProbe:             # ✅ NUEVO: para startups lentos
  httpGet:
    path: /health
    port: 8081
  failureThreshold: 30    # ✅ 30 intentos * 10s = 5 minutos max startup
  periodSeconds: 10
```

---

### ✅ Integración con Prometheus

#### Paso 1: Anotar Services

```yaml
# k8s/clean-marvel-service.yaml (AGREGAR)
apiVersion: v1
kind: Service
metadata:
  name: clean-marvel
  labels:
    app: clean-marvel
    tier: frontend
  annotations:
    prometheus.io/scrape: "true"
    prometheus.io/port: "8080"
    prometheus.io/path: "/metrics"  # Si implementas endpoint /metrics
spec:
  # ... resto igual
```

#### Paso 2: Instalar Prometheus Stack

```bash
# Opción 1: Kube-Prometheus-Stack (completo)
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
helm install prometheus prometheus-community/kube-prometheus-stack \
  --namespace monitoring --create-namespace

# Opción 2: Prometheus Operator (minimalista)
kubectl apply -f https://raw.githubusercontent.com/prometheus-operator/prometheus-operator/main/bundle.yaml
```

#### Paso 3: ServiceMonitor

```yaml
# k8s/service-monitor.yaml (NUEVO)
apiVersion: monitoring.coreos.com/v1
kind: ServiceMonitor
metadata:
  name: clean-marvel-monitor
  labels:
    release: prometheus
spec:
  selector:
    matchLabels:
      app: clean-marvel
  endpoints:
    - port: http
      interval: 30s
      path: /metrics
```

---

### ✅ Distributed Tracing (Jaeger/Tempo)

```yaml
# k8s/jaeger-all-in-one.yaml (DESARROLLO)
apiVersion: apps/v1
kind: Deployment
metadata:
  name: jaeger
spec:
  replicas: 1
  selector:
    matchLabels:
      app: jaeger
  template:
    metadata:
      labels:
        app: jaeger
    spec:
      containers:
        - name: jaeger
          image: jaegertracing/all-in-one:latest
          ports:
            - containerPort: 16686  # UI
            - containerPort: 14268  # Collector
          env:
            - name: COLLECTOR_ZIPKIN_HOST_PORT
              value: ":9411"
---
apiVersion: v1
kind: Service
metadata:
  name: jaeger
spec:
  selector:
    app: jaeger
  ports:
    - name: ui
      port: 16686
      targetPort: 16686
    - name: collector
      port: 14268
      targetPort: 14268
```

---

## 4. Seguridad de Red

### 🟡 Problema Actual

- ❌ Sin NetworkPolicies: cualquier pod puede comunicarse con cualquier otro
- ❌ Frontend puede acceder directamente a bases de datos (si hubiera)
- ❌ Sin segmentación por capas

---

### ✅ Solución: Network Policies por Tier

```yaml
# k8s/network-policies.yaml (NUEVO)

# 1. Política para frontend (clean-marvel)
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: clean-marvel-network-policy
spec:
  podSelector:
    matchLabels:
      app: clean-marvel
      tier: frontend
  policyTypes:
    - Ingress
    - Egress
  
  ingress:
    # Permitir tráfico desde Ingress Controller
    - from:
        - namespaceSelector:
            matchLabels:
              name: ingress-nginx
      ports:
        - protocol: TCP
          port: 8080
  
  egress:
    # Permitir comunicación con servicios backend
    - to:
        - podSelector:
            matchLabels:
              tier: backend
      ports:
        - protocol: TCP
          port: 8081  # openai-service
        - protocol: TCP
          port: 80    # rag-service
    
    # Permitir DNS
    - to:
        - namespaceSelector:
            matchLabels:
              name: kube-system
        - podSelector:
            matchLabels:
              k8s-app: kube-dns
      ports:
        - protocol: UDP
          port: 53
    
    # Permitir HTTPS saliente (APIs externas)
    - to:
        - namespaceSelector: {}
      ports:
        - protocol: TCP
          port: 443

---
# 2. Política para backend (openai-service)
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: openai-service-network-policy
spec:
  podSelector:
    matchLabels:
      app: openai-service
      tier: backend
  policyTypes:
    - Ingress
    - Egress
  
  ingress:
    # Solo permitir tráfico desde frontend y rag-service
    - from:
        - podSelector:
            matchLabels:
              tier: frontend
        - podSelector:
            matchLabels:
              app: rag-service
      ports:
        - protocol: TCP
          port: 8081
  
  egress:
    # Permitir OpenAI API
    - to:
        - namespaceSelector: {}
      ports:
        - protocol: TCP
          port: 443
    
    # DNS
    - to:
        - namespaceSelector:
            matchLabels:
              name: kube-system
      ports:
        - protocol: UDP
          port: 53

---
# 3. Política para rag-service
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: rag-service-network-policy
spec:
  podSelector:
    matchLabels:
      app: rag-service
      tier: backend
  policyTypes:
    - Ingress
    - Egress
  
  ingress:
    # Solo desde frontend
    - from:
        - podSelector:
            matchLabels:
              tier: frontend
      ports:
        - protocol: TCP
          port: 80
  
  egress:
    # Comunicación con openai-service
    - to:
        - podSelector:
            matchLabels:
              app: openai-service
      ports:
        - protocol: TCP
          port: 8081
    
    # DNS
    - to:
        - namespaceSelector:
            matchLabels:
              name: kube-system
      ports:
        - protocol: UDP
          port: 53
```

**Aplicar:**
```bash
kubectl apply -f k8s/network-policies.yaml

# Verificar
kubectl get networkpolicies
kubectl describe networkpolicy clean-marvel-network-policy
```

---

### ✅ Deny-All por Defecto (Best Practice)

```yaml
# k8s/default-deny-all.yaml (NUEVO)
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: default-deny-all
spec:
  podSelector: {}  # Aplica a todos los pods
  policyTypes:
    - Ingress
    - Egress
  # Sin reglas = DENY ALL
```

**Nota:** Aplicar esto DESPUÉS de crear las políticas específicas, o romperá todo.

---

## 5. TLS y Certificados Automáticos

### 🟡 Problema Actual

```yaml
# k8s/ingress.yaml (ACTUAL)
spec:
  rules:
    - host: clean-marvel.local
      # Sin TLS
```

**Riesgos:**
- ⚠️ Tráfico sin cifrar (HTTP)
- ⚠️ Sin autenticación del servidor
- ⚠️ Vulnerable a MITM

---

### ✅ Solución: cert-manager + Let's Encrypt

#### Paso 1: Instalar cert-manager

```bash
kubectl apply -f https://github.com/cert-manager/cert-manager/releases/download/v1.13.0/cert-manager.yaml

# Verificar instalación
kubectl get pods -n cert-manager
```

#### Paso 2: Crear ClusterIssuer

```yaml
# k8s/cert-manager-issuer.yaml (NUEVO)

# Issuer para staging (testing)
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-staging
spec:
  acme:
    server: https://acme-staging-v02.api.letsencrypt.org/directory
    email: tu-email@example.com  # ✅ Cambiar por tu email
    privateKeySecretRef:
      name: letsencrypt-staging-key
    solvers:
      - http01:
          ingress:
            class: nginx

---
# Issuer para producción
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-prod
spec:
  acme:
    server: https://acme-v02.api.letsencrypt.org/directory
    email: tu-email@example.com  # ✅ Cambiar por tu email
    privateKeySecretRef:
      name: letsencrypt-prod-key
    solvers:
      - http01:
          ingress:
            class: nginx
```

```bash
kubectl apply -f k8s/cert-manager-issuer.yaml
kubectl get clusterissuer
```

#### Paso 3: Actualizar Ingress con TLS

```yaml
# k8s/ingress.yaml (MEJORADO)
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: clean-marvel-web
  annotations:
    cert-manager.io/cluster-issuer: "letsencrypt-prod"  # ✅ Certificado automático
    nginx.ingress.kubernetes.io/proxy-body-size: "10m"
    nginx.ingress.kubernetes.io/force-ssl-redirect: "true"  # ✅ Redirigir HTTP → HTTPS
    nginx.ingress.kubernetes.io/ssl-protocols: "TLSv1.2 TLSv1.3"  # ✅ Solo TLS moderno
    nginx.ingress.kubernetes.io/rate-limit: "100"  # ✅ 100 req/s por IP
    nginx.ingress.kubernetes.io/limit-connections: "10"
    nginx.ingress.kubernetes.io/enable-cors: "true"
    nginx.ingress.kubernetes.io/cors-allow-origin: "https://clean-marvel.com"
spec:
  ingressClassName: nginx
  
  tls:  # ✅ NUEVO
    - hosts:
        - clean-marvel.com  # ✅ Cambiar por tu dominio real
      secretName: clean-marvel-tls  # ✅ cert-manager creará este Secret
  
  rules:
    - host: clean-marvel.com  # ✅ Cambiar por tu dominio real
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: clean-marvel
                port:
                  number: 80
```

**Aplicar:**
```bash
kubectl apply -f k8s/ingress.yaml

# Verificar certificado
kubectl get certificate
kubectl describe certificate clean-marvel-tls

# Ver el Secret creado
kubectl get secret clean-marvel-tls
```

---

## 6. Gestión de Imágenes

### 🟡 Problema Actual

```yaml
image: 20luisma/clean-marvel:latest  # ❌ Tag mutable
imagePullPolicy: IfNotPresent
```

**Riesgos:**
- ⚠️ `:latest` puede cambiar sin previo aviso
- ⚠️ Dificulta rollbacks (¿cuál era la versión anterior?)
- ⚠️ Sin validation de integridad

---

### ✅ Solución 1: Tags Semánticos Inmutables

```yaml
# k8s/clean-marvel-deployment.yaml (MEJORADO)
containers:
  - name: clean-marvel
    image: 20luisma/clean-marvel:v1.2.3  # ✅ Versionado semántico
    imagePullPolicy: Always  # ✅ Siempre verificar registry
```

**Workflow de versionado:**
```bash
# Build con versión específica
VERSION="v1.2.3"
docker build -t 20luisma/clean-marvel:${VERSION} .
docker push 20luisma/clean-marvel:${VERSION}

# Actualizar deployment
kubectl set image deployment/clean-marvel \
  clean-marvel=20luisma/clean-marvel:${VERSION}

# Rollback si falla
kubectl rollout undo deployment/clean-marvel
```

---

### ✅ Solución 2: Usar SHA256 (Máxima Inmutabilidad)

```bash
# Obtener digest de la imagen
docker pull 20luisma/clean-marvel:v1.2.3
docker inspect --format='{{index .RepoDigests 0}}' 20luisma/clean-marvel:v1.2.3
# Output: 20luisma/clean-marvel@sha256:abc123...

# Usar en deployment
kubectl set image deployment/clean-marvel \
  clean-marvel=20luisma/clean-marvel@sha256:abc123...
```

```yaml
# k8s/clean-marvel-deployment.yaml (MÁXIMA SEGURIDAD)
containers:
  - name: clean-marvel
    image: 20luisma/clean-marvel@sha256:abc123...  # ✅ Inmutable por diseño
```

---

### ✅ Scanning de Vulnerabilidades

#### Opción 1: Trivy (Open Source)

```bash
# Instalar Trivy
brew install aquasecurity/trivy/trivy

# Escanear imagen
trivy image 20luisma/clean-marvel:v1.2.3

# Escanear y fallar en CI si hay vulnerabilidades HIGH o CRITICAL
trivy image --exit-code 1 --severity HIGH,CRITICAL 20luisma/clean-marvel:v1.2.3
```

**Integración en CI/CD:**
```yaml
# .github/workflows/build.yml
- name: Scan image with Trivy
  run: |
    docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
      aquasec/trivy:latest image --severity HIGH,CRITICAL \
      20luisma/clean-marvel:${{ github.sha }}
```

#### Opción 2: Snyk

```bash
snyk container test 20luisma/clean-marvel:v1.2.3
```

---

### ✅ Firma de Imágenes con Cosign

```bash
# Instalar Cosign
brew install cosign

# Generar claves
cosign generate-key-pair

# Firmar imagen
cosign sign --key cosign.key 20luisma/clean-marvel:v1.2.3

# Verificar firma
cosign verify --key cosign.pub 20luisma/clean-marvel:v1.2.3
```

**Política de admisión (avanzado):**
```yaml
# Requiere Sigstore Policy Controller
apiVersion: policy.sigstore.dev/v1beta1
kind: ClusterImagePolicy
metadata:
  name: require-signed-images
spec:
  images:
    - glob: "20luisma/*"
  authorities:
    - key:
        data: |
          -----BEGIN PUBLIC KEY-----
          ...tu clave pública...
          -----END PUBLIC KEY-----
```

---

## 7. Namespaces y Aislamiento

### 🟡 Problema Actual

Todos los recursos van al namespace `default`.

**Riesgos:**
- ⚠️ Sin aislamiento entre entornos (dev/staging/prod)
- ⚠️ Sin límites de recursos por equipo/proyecto
- ⚠️ Dificulta gestión de permisos RBAC

---

### ✅ Solución: Namespaces Dedicados

```yaml
# k8s/namespaces.yaml (NUEVO)
apiVersion: v1
kind: Namespace
metadata:
  name: clean-marvel-prod
  labels:
    environment: production
    team: marvel-dev

---
apiVersion: v1
kind: Namespace
metadata:
  name: clean-marvel-staging
  labels:
    environment: staging
    team: marvel-dev

---
apiVersion: v1
kind: Namespace
metadata:
  name: clean-marvel-dev
  labels:
    environment: development
    team: marvel-dev
```

**Actualizar manifiestos:**
```yaml
# k8s/clean-marvel-deployment.yaml (AGREGAR)
apiVersion: apps/v1
kind: Deployment
metadata:
  name: clean-marvel
  namespace: clean-marvel-prod  # ✅ AGREGAR
spec:
  # ... resto igual
```

**Aplicar en namespace específico:**
```bash
kubectl apply -f k8s/clean-marvel-deployment.yaml -n clean-marvel-prod
kubectl get pods -n clean-marvel-prod
```

---

### ✅ Resource Quotas

```yaml
# k8s/resource-quotas.yaml (NUEVO)
apiVersion: v1
kind: ResourceQuota
metadata:
  name: clean-marvel-quota
  namespace: clean-marvel-prod
spec:
  hard:
    # Límites computacionales
    requests.cpu: "4"
    requests.memory: 8Gi
    limits.cpu: "8"
    limits.memory: 16Gi
    
    # Límites de objetos
    pods: "20"
    services: "10"
    persistentvolumeclaims: "5"
    secrets: "20"
    configmaps: "20"

---
apiVersion: v1
kind: LimitRange
metadata:
  name: clean-marvel-limits
  namespace: clean-marvel-prod
spec:
  limits:
    # Límites por container
    - type: Container
      default:
        cpu: "500m"
        memory: "512Mi"
      defaultRequest:
        cpu: "200m"
        memory: "256Mi"
      max:
        cpu: "2"
        memory: "2Gi"
      min:
        cpu: "100m"
        memory: "128Mi"
    
    # Límites por pod
    - type: Pod
      max:
        cpu: "4"
        memory: "4Gi"
```

---

## 8. Autoescalado y Optimización de Recursos

### ✅ HorizontalPodAutoscaler (HPA)

```yaml
# k8s/hpa.yaml (NUEVO)
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: clean-marvel-hpa
  namespace: clean-marvel-prod
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: clean-marvel
  
  minReplicas: 2
  maxReplicas: 10
  
  metrics:
    # CPU target
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70  # Escalar al 70% CPU
    
    # Memoria target
    - type: Resource
      resource:
        name: memory
        target:
          type: Utilization
          averageUtilization: 80  # Escalar al 80% memoria
  
  behavior:
    scaleDown:
      stabilizationWindowSeconds: 300  # Esperar 5min antes de bajar pods
      policies:
        - type: Percent
          value: 50
          periodSeconds: 60  # Máximo bajar 50% de pods por minuto
    scaleUp:
      stabilizationWindowSeconds: 0
      policies:
        - type: Percent
          value: 100
          periodSeconds: 30  # Doblar pods cada 30s si es necesario
```

**Requisito:** Metrics Server instalado
```bash
kubectl apply -f https://github.com/kubernetes-sigs/metrics-server/releases/latest/download/components.yaml
```

---

### ✅ Vertical Pod Autoscaler (VPA)

```yaml
# k8s/vpa.yaml (NUEVO)
apiVersion: autoscaling.k8s.io/v1
kind: VerticalPodAutoscaler
metadata:
  name: clean-marvel-vpa
  namespace: clean-marvel-prod
spec:
  targetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: clean-marvel
  
  updatePolicy:
    updateMode: "Auto"  # Ajustar automáticamente resources
  
  resourcePolicy:
    containerPolicies:
      - containerName: clean-marvel
        minAllowed:
          cpu: "100m"
          memory: "128Mi"
        maxAllowed:
          cpu: "2"
          memory: "2Gi"
```

---

## 9. Backup y Disaster Recovery

### ✅ Velero (Backup de Cluster)

```bash
# Instalar Velero
velero install \
  --provider aws \
  --plugins velero/velero-plugin-for-aws:v1.8.0 \
  --bucket clean-marvel-backups \
  --secret-file ./credentials-velero \
  --use-volume-snapshots=true \
  --backup-location-config region=us-east-1

# Crear backup manual
velero backup create clean-marvel-backup-$(date +%Y%m%d) \
  --include-namespaces clean-marvel-prod

# Programar backups automáticos
velero schedule create clean-marvel-daily \
  --schedule="0 2 * * *" \
  --include-namespaces clean-marvel-prod

# Restaurar desde backup
velero restore create --from-backup clean-marvel-backup-20231209
```

---

### ✅ Backup de Base de Datos (si aplica)

```yaml
# k8s/mysql-backup-cronjob.yaml (EJEMPLO)
apiVersion: batch/v1
kind: CronJob
metadata:
  name: mysql-backup
  namespace: clean-marvel-prod
spec:
  schedule: "0 2 * * *"  # Diario a las 2 AM
  jobTemplate:
    spec:
      template:
        spec:
          containers:
            - name: mysql-backup
              image: mysql:8.0
              command:
                - /bin/sh
                - -c
                - |
                  mysqldump -h mysql-service -u root -p${MYSQL_ROOT_PASSWORD} \
                    --all-databases > /backup/dump-$(date +%Y%m%d).sql
              volumeMounts:
                - name: backup-storage
                  mountPath: /backup
              env:
                - name: MYSQL_ROOT_PASSWORD
                  valueFrom:
                    secretKeyRef:
                      name: mysql-secret
                      key: root-password
          volumes:
            - name: backup-storage
              persistentVolumeClaim:
                claimName: mysql-backup-pvc
          restartPolicy: OnFailure
```

---

## Checklist de Pre-Producción

### 🔴 Crítico (Obligatorio)

- [ ] **Secrets gestionados externamente** (Sealed Secrets / External Secrets / manual)
- [ ] **TLS configurado** (cert-manager + Let's Encrypt)
- [ ] **Resource limits y requests** definidos en todos los containers
- [ ] **Rolling update strategy** explícita
- [ ] **Healthchecks HTTP** con endpoints `/health` y `/ready`
- [ ] **Network Policies** implementadas por tier
- [ ] **Image tags inmutables** (semver o SHA256)
- [ ] **PodDisruptionBudgets** configurados
- [ ] **Namespaces dedicados** (no usar `default`)
- [ ] **Monitoring básico** (Prometheus/Grafana o equivalente)

### 🟠 Alta Prioridad

- [ ] **HorizontalPodAutoscaler** configurado
- [ ] **Anti-affinity** para distribución en nodos
- [ ] **Image scanning** en CI/CD (Trivy/Snyk)
- [ ] **Resource Quotas** por namespace
- [ ] **Distributed tracing** (Jaeger/Tempo)
- [ ] **Backup automatizado** (Velero o similar)
- [ ] **DNS configurado** en dominio real
- [ ] **Secrets cifrados en etcd**
- [ ] **RBAC** configurado (Service Accounts con mínimos privilegios)

### 🟡 Mejoras Adicionales

- [ ] **VerticalPodAutoscaler** para optimización automática
- [ ] **Service Mesh** (Istio/Linkerd) para tráfico avanzado
- [ ] **OPA/Gatekeeper** para políticas de seguridad
- [ ] **Falco** para runtime security
- [ ] **Loki** para centralización de logs
- [ ] **Chaos Engineering** (Chaos Mesh/Litmus)
- [ ] **GitOps** (ArgoCD/FluxCD)
- [ ] **Multi-cluster** setup para DR

---

## Recursos y Referencias

### Documentación Oficial
- [Kubernetes Best Practices](https://kubernetes.io/docs/concepts/configuration/overview/)
- [CNCF Security Whitepaper](https://www.cncf.io/wp-content/uploads/2020/08/CNCF_Kubernetes_Security_Whitepaper_Aug2020.pdf)
- [NSA/CISA Kubernetes Hardening Guide](https://media.defense.gov/2022/Aug/29/2003066362/-1/-1/0/CTR_KUBERNETES_HARDENING_GUIDANCE_1.2_20220829.PDF)

### Herramientas
- [cert-manager](https://cert-manager.io/)
- [Sealed Secrets](https://github.com/bitnami-labs/sealed-secrets)
- [External Secrets Operator](https://external-secrets.io/)
- [Prometheus Operator](https://github.com/prometheus-operator/prometheus-operator)
- [Velero](https://velero.io/)
- [Trivy](https://aquasecurity.github.io/trivy/)

### Cursos y Certificaciones
- **CKA** (Certified Kubernetes Administrator)
- **CKAD** (Certified Kubernetes Application Developer)
- **CKS** (Certified Kubernetes Security Specialist)

---

## Conclusión

Esta guía proporciona una **hoja de ruta completa** para llevar el despliegue de Kubernetes de un entorno de desarrollo/demo a producción enterprise-grade.

**Recuerda:**
- ✅ La implementación actual es **válida para demos y desarrollo**
- 🚀 Las mejoras aquí descritas son **recomendaciones progresivas**
- 🎯 Prioriza según tus **requisitos específicos de producción**

Para cualquier duda, consulta la documentación oficial de Kubernetes o los recursos listados arriba.
