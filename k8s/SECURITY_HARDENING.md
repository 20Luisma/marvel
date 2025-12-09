# Security Hardening para Kubernetes

## 📋 Índice

1. [Introducción](#introducción)
2. [Modelo de Seguridad en Capas](#modelo-de-seguridad-en-capas)
3. [Capa 1: Control Plane Security](#capa-1-control-plane-security)
4. [Capa 2: RBAC y Gestión de Identidades](#capa-2-rbac-y-gestión-de-identidades)
5. [Capa 3: Network Security](#capa-3-network-security)
6. [Capa 4: Secrets Management](#capa-4-secrets-management)
7. [Capa 5: Pod Security Standards](#capa-5-pod-security-standards)
8. [Capa 6: Image Security](#capa-6-image-security)
9. [Capa 7: Runtime Security](#capa-7-runtime-security)
10. [Capa 8: Audit y Compliance](#capa-8-audit-y-compliance)
11. [Capa 9: Data Protection](#capa-9-data-protection)
12. [Capa 10: Incident Response](#capa-10-incident-response)
13. [Security Checklist Completo](#security-checklist-completo)

---

## Introducción

Este documento establece **prácticas de hardening de seguridad** para el despliegue de Kubernetes de Clean Marvel Album, alineadas con las **10 capas de seguridad** ya implementadas en la aplicación PHP.

### 🎯 Objetivo

Extender la **defensa en profundidad** del proyecto al nivel de orquestación, creando un entorno Kubernetes que refleje los mismos principios de seguridad multicapa de la aplicación.

### 📚 Frameworks de Referencia

- **CIS Kubernetes Benchmark** v1.8
- **NSA/CISA Kubernetes Hardening Guide**
- **OWASP Kubernetes Security Cheat Sheet**
- **NIST SP 800-190** (Application Container Security)

---

## Modelo de Seguridad en Capas

### Mapeando las 10 Capas de Clean Marvel a Kubernetes

| Capa App (PHP) | Equivalente Kubernetes | Prioridad |
|----------------|------------------------|-----------|
| 1. Security Headers | Ingress Annotations | 🔴 Alta |
| 2. CSP | Ingress + App Config | 🟠 Media |
| 3. CSRF | App-level (no K8s) | N/A |
| 4. Rate Limiting | Ingress + NetworkPolicies | 🔴 Alta |
| 5. API Firewall | NetworkPolicies + AdmissionControllers | 🔴 Alta |
| 6. Input Sanitization | App-level (no K8s) | N/A |
| 7. Secrets Protection | Sealed Secrets + Encryption at Rest | 🔴 Crítica |
| 8. Anti-Replay | App-level (no K8s) | N/A |
| 9. Security Logging | Audit Logs + Falco | 🟠 Media |
| 10. Security Monitoring | Prometheus + Sentry + Falco | 🟠 Media |

---

## Capa 1: Control Plane Security

### 🔒 Objetivo
Asegurar que el plano de control de Kubernetes (API Server, etcd, scheduler, controller-manager) esté protegido contra accesos no autorizados.

### ✅ Best Practices

#### 1.1. API Server Flags Seguros

```yaml
# /etc/kubernetes/manifests/kube-apiserver.yaml (Control Plane Node)
apiVersion: v1
kind: Pod
metadata:
  name: kube-apiserver
spec:
  containers:
    - name: kube-apiserver
      command:
        - kube-apiserver
        
        # Autenticación
        - --anonymous-auth=false                    # ✅ Deshabilitar auth anónimo
        - --enable-bootstrap-token-auth=false       # ✅ Si no usas tokens de bootstrap
        
        # Autorización
        - --authorization-mode=Node,RBAC            # ✅ Solo RBAC y Node
        
        # Auditoría
        - --audit-log-path=/var/log/kube-audit.log
        - --audit-log-maxage=30
        - --audit-log-maxbackup=10
        - --audit-log-maxsize=100
        - --audit-policy-file=/etc/kubernetes/audit-policy.yaml
        
        # Cifrado
        - --encryption-provider-config=/etc/kubernetes/encryption-config.yaml
        - --tls-cert-file=/etc/kubernetes/pki/apiserver.crt
        - --tls-private-key-file=/etc/kubernetes/pki/apiserver.key
        - --tls-cipher-suites=TLS_ECDHE_RSA_WITH_AES_128_GCM_SHA256,TLS_ECDHE_RSA_WITH_AES_256_GCM_SHA384
        
        # API Features
        - --enable-admission-plugins=NodeRestriction,PodSecurityPolicy,AlwaysPullImages
        - --disable-admission-plugins=AlwaysAdmit
```

---

#### 1.2. etcd Security

```yaml
# /etc/kubernetes/manifests/etcd.yaml
apiVersion: v1
kind: Pod
metadata:
  name: etcd
spec:
  containers:
    - name: etcd
      command:
        - etcd
        - --client-cert-auth=true  # ✅ Requiere certificados cliente
        - --peer-client-cert-auth=true
        - --peer-auto-tls=false
        - --auto-tls=false
```

**Backup cifrado de etcd:**
```bash
# Backup
ETCDCTL_API=3 etcdctl snapshot save /backup/etcd-snapshot-$(date +%Y%m%d).db \
  --endpoints=https://127.0.0.1:2379 \
  --cacert=/etc/kubernetes/pki/etcd/ca.crt \
  --cert=/etc/kubernetes/pki/etcd/server.crt \
  --key=/etc/kubernetes/pki/etcd/server.key

# Cifrar backup
gpg --encrypt --recipient admin@clean-marvel.com /backup/etcd-snapshot.db
```

---

#### 1.3. Audit Policy

```yaml
# /etc/kubernetes/audit-policy.yaml
apiVersion: audit.k8s.io/v1
kind: Policy
rules:
  # No auditar health checks (ruido)
  - level: None
    users: ["system:kube-proxy"]
    verbs: ["watch"]
    resources:
      - group: ""
        resources: ["endpoints", "services"]

  # Registrar metadata de lectura de secrets
  - level: Metadata
    resources:
      - group: ""
        resources: ["secrets", "configmaps"]
    verbs: ["get", "list", "watch"]

  # Registrar TODO sobre secrets/configmaps modificados
  - level: RequestResponse
    resources:
      - group: ""
        resources: ["secrets", "configmaps"]
    verbs: ["create", "update", "patch", "delete"]

  # Registrar metadata de creación/modificación de pods
  - level: Metadata
    resources:
      - group: ""
        resources: ["pods"]
    verbs: ["create", "update", "patch", "delete"]

  # Registrar exec, attach, port-forward (potencialmente peligrosos)
  - level: RequestResponse
    resources:
      - group: ""
        resources: ["pods/exec", "pods/attach", "pods/portforward"]

  # Registrar TODO sobre roles/rolebindings (RBAC)
  - level: RequestResponse
    resources:
      - group: "rbac.authorization.k8s.io"

  # Registrar metadata para el resto
  - level: Metadata
    omitStages:
      - RequestReceived
```

---

## Capa 2: RBAC y Gestión de Identidades

### 🔒 Objetivo
Aplicar **mínimo privilegio** a todos los componentes, usuarios y procesos.

### ✅ Service Accounts Dedicados

```yaml
# k8s/service-accounts.yaml (NUEVO)

# Service Account para clean-marvel (frontend)
apiVersion: v1
kind: ServiceAccount
metadata:
  name: clean-marvel-sa
  namespace: clean-marvel-prod
automountServiceAccountToken: false  # ✅ No montar token por defecto

---
# Role mínimo para clean-marvel
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  name: clean-marvel-role
  namespace: clean-marvel-prod
rules:
  # Solo permitir lectura de ConfigMaps y Secrets propios
  - apiGroups: [""]
    resources: ["configmaps", "secrets"]
    resourceNames: ["clean-marvel-config", "clean-marvel-secrets"]
    verbs: ["get"]

---
# Binding
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: clean-marvel-binding
  namespace: clean-marvel-prod
subjects:
  - kind: ServiceAccount
    name: clean-marvel-sa
    namespace: clean-marvel-prod
roleRef:
  kind: Role
  name: clean-marvel-role
  apiGroup: rbac.authorization.k8s.io

---
# Service Account para microservicios backend
apiVersion: v1
kind: ServiceAccount
metadata:
  name: ai-services-sa
  namespace: clean-marvel-prod
automountServiceAccountToken: false

---
# Role para servicios AI (sin permisos de lectura de secrets del frontend)
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  name: ai-services-role
  namespace: clean-marvel-prod
rules:
  - apiGroups: [""]
    resources: ["configmaps"]
    resourceNames: ["openai-service-config", "rag-service-config"]
    verbs: ["get"]
  # No acceso a clean-marvel-secrets

---
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: ai-services-binding
  namespace: clean-marvel-prod
subjects:
  - kind: ServiceAccount
    name: ai-services-sa
    namespace: clean-marvel-prod
roleRef:
  kind: Role
  name: ai-services-role
  apiGroup: rbac.authorization.k8s.io
```

**Actualizar Deployments:**
```yaml
# k8s/clean-marvel-deployment.yaml (AGREGAR)
spec:
  template:
    spec:
      serviceAccountName: clean-marvel-sa  # ✅ AGREGAR
      automountServiceAccountToken: false  # ✅ AGREGAR
      containers:
        - name: clean-marvel
          # ... resto
```

---

### ✅ RBAC para Usuarios Humanos

```yaml
# k8s/rbac-developers.yaml (EJEMPLO)

# Role para desarrolladores (lectura en prod, escritura en dev)
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  name: developer-role
  namespace: clean-marvel-prod
rules:
  # Lectura de recursos
  - apiGroups: ["", "apps", "batch"]
    resources: ["pods", "deployments", "services", "jobs", "cronjobs"]
    verbs: ["get", "list", "watch"]
  
  # Logs y exec (para debugging)
  - apiGroups: [""]
    resources: ["pods/log", "pods/exec"]
    verbs: ["get", "create"]
  
  # NO pueden leer secrets
  - apiGroups: [""]
    resources: ["secrets"]
    verbs: []  # Vacío = sin permisos

---
# RoleBinding para grupo de desarrolladores
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: developers-binding
  namespace: clean-marvel-prod
subjects:
  - kind: Group
    name: developers  # Grupo en tu proveedor de identidad (OIDC/LDAP)
    apiGroup: rbac.authorization.k8s.io
roleRef:
  kind: Role
  name: developer-role
  apiGroup: rbac.authorization.k8s.io
```

---

## Capa 3: Network Security

### 🔒 Objetivo
Implementar **zero-trust networking**: negar todo por defecto, permitir explícitamente.

### ✅ Default Deny + Allow Explícito

```yaml
# k8s/network-policies-strict.yaml (NUEVO)

# 1. DENY ALL por defecto en el namespace
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: default-deny-all
  namespace: clean-marvel-prod
spec:
  podSelector: {}
  policyTypes:
    - Ingress
    - Egress
  # Sin reglas = DENY TODO

---
# 2. Permitir DNS (requerido por todos)
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: allow-dns
  namespace: clean-marvel-prod
spec:
  podSelector: {}
  policyTypes:
    - Egress
  egress:
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
        - protocol: TCP
          port: 53

---
# 3. Frontend → Backend (solo puertos específicos)
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: frontend-to-backend
  namespace: clean-marvel-prod
spec:
  podSelector:
    matchLabels:
      tier: frontend
  policyTypes:
    - Egress
  egress:
    # A openai-service
    - to:
        - podSelector:
            matchLabels:
              app: openai-service
      ports:
        - protocol: TCP
          port: 8081
    
    # A rag-service
    - to:
        - podSelector:
            matchLabels:
              app: rag-service
      ports:
        - protocol: TCP
          port: 80
    
    # A APIs externas (HTTPS)
    - to:
        - namespaceSelector: {}
      ports:
        - protocol: TCP
          port: 443

---
# 4. Backend puede hablar entre sí
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: backend-to-backend
  namespace: clean-marvel-prod
spec:
  podSelector:
    matchLabels:
      tier: backend
  policyTypes:
    - Egress
    - Ingress
  
  ingress:
    # Desde frontend
    - from:
        - podSelector:
            matchLabels:
              tier: frontend
    # Desde otros backend
    - from:
        - podSelector:
            matchLabels:
              tier: backend
  
  egress:
    # A otros backend
    - to:
        - podSelector:
            matchLabels:
              tier: backend
    # A APIs externas
    - to:
        - namespaceSelector: {}
      ports:
        - protocol: TCP
          port: 443

---
# 5. Ingress Controller puede llegar al frontend
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: allow-ingress-to-frontend
  namespace: clean-marvel-prod
spec:
  podSelector:
    matchLabels:
      tier: frontend
  policyTypes:
    - Ingress
  ingress:
    - from:
        - namespaceSelector:
            matchLabels:
              name: ingress-nginx
      ports:
        - protocol: TCP
          port: 8080
```

---

### ✅ Ingress Security Annotations

```yaml
# k8s/ingress-secure.yaml (MEJORADO)
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: clean-marvel-web
  annotations:
    # TLS
    cert-manager.io/cluster-issuer: "letsencrypt-prod"
    nginx.ingress.kubernetes.io/force-ssl-redirect: "true"
    nginx.ingress.kubernetes.io/ssl-protocols: "TLSv1.2 TLSv1.3"
    nginx.ingress.kubernetes.io/ssl-ciphers: "ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384"
    
    # Rate Limiting (alineado con la app)
    nginx.ingress.kubernetes.io/rate-limit: "100"  # 100 req/s
    nginx.ingress.kubernetes.io/limit-rps: "100"
    nginx.ingress.kubernetes.io/limit-connections: "10"
    nginx.ingress.kubernetes.io/limit-burst-multiplier: "5"
    
    # Security Headers (complementa los de la app)
    nginx.ingress.kubernetes.io/configuration-snippet: |
      more_set_headers "X-Frame-Options: DENY";
      more_set_headers "X-Content-Type-Options: nosniff";
      more_set_headers "X-XSS-Protection: 1; mode=block";
      more_set_headers "Referrer-Policy: strict-origin-when-cross-origin";
      more_set_headers "Permissions-Policy: geolocation=(), microphone=(), camera=()";
    
    # CORS (si aplica)
    nginx.ingress.kubernetes.io/enable-cors: "true"
    nginx.ingress.kubernetes.io/cors-allow-origin: "https://clean-marvel.com"
    nginx.ingress.kubernetes.io/cors-allow-methods: "GET, POST, OPTIONS"
    nginx.ingress.kubernetes.io/cors-allow-credentials: "true"
    
    # Body size y timeouts
    nginx.ingress.kubernetes.io/proxy-body-size: "10m"
    nginx.ingress.kubernetes.io/proxy-connect-timeout: "5"
    nginx.ingress.kubernetes.io/proxy-send-timeout: "60"
    nginx.ingress.kubernetes.io/proxy-read-timeout: "60"
    
    # WAF (si ModSecurity está habilitado en Ingress)
    nginx.ingress.kubernetes.io/enable-modsecurity: "true"
    nginx.ingress.kubernetes.io/enable-owasp-core-rules: "true"

spec:
  ingressClassName: nginx
  tls:
    - hosts:
        - clean-marvel.com
      secretName: clean-marvel-tls
  rules:
    - host: clean-marvel.com
      # ... resto
```

---

## Capa 4: Secrets Management

### 🔒 Objetivo
Garantizar que **ningún secret esté en plaintext** en repositorio o etcd sin cifrar.

### ✅ Implementación con Sealed Secrets

Ver [PRODUCTION_CONSIDERATIONS.md - Sección 1](./PRODUCTION_CONSIDERATIONS.md#1-gestión-segura-de-secrets) para detalles completos.

**Quick Reference:**
```bash
# Crear SealedSecret
kubectl create secret generic clean-marvel-secrets \
  --from-literal=OPENAI_API_KEY="sk-..." \
  --dry-run=client -o yaml | \
kubeseal -o yaml > k8s/clean-marvel-sealed-secret.yaml

# Commitear (seguro)
git add k8s/clean-marvel-sealed-secret.yaml
git commit -m "Add sealed secrets"
```

---

### ✅ Rotación Automática de Secrets

```yaml
# k8s/secret-rotation-cronjob.yaml (EJEMPLO)
apiVersion: batch/v1
kind: CronJob
metadata:
  name: rotate-internal-api-key
  namespace: clean-marvel-prod
spec:
  schedule: "0 2 1 * *"  # Primer día de cada mes a las 2 AM
  jobTemplate:
    spec:
      template:
        spec:
          serviceAccountName: secret-rotator-sa
          containers:
            - name: rotator
              image: bitnami/kubectl:latest
              command:
                - /bin/bash
                - -c
                - |
                  # Generar nueva clave
                  NEW_KEY=$(openssl rand -base64 32)
                  
                  # Actualizar Secret
                  kubectl patch secret clean-marvel-secrets \
                    -p "{\"data\":{\"INTERNAL_API_KEY\":\"$(echo -n $NEW_KEY | base64)\"}}"
                  
                  # Reiniciar pods para recargar
                  kubectl rollout restart deployment/clean-marvel
                  
                  # Notificar (Slack, PagerDuty, etc.)
                  curl -X POST $SLACK_WEBHOOK \
                    -d '{"text":"🔄 INTERNAL_API_KEY rotado exitosamente"}'
              env:
                - name: SLACK_WEBHOOK
                  valueFrom:
                    secretKeyRef:
                      name: notification-secrets
                      key: slack-webhook
          restartPolicy: OnFailure

---
# RBAC para el rotador
apiVersion: v1
kind: ServiceAccount
metadata:
  name: secret-rotator-sa
  namespace: clean-marvel-prod

---
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  name: secret-rotator-role
  namespace: clean-marvel-prod
rules:
  - apiGroups: [""]
    resources: ["secrets"]
    resourceNames: ["clean-marvel-secrets"]
    verbs: ["get", "patch"]
  - apiGroups: ["apps"]
    resources: ["deployments"]
    resourceNames: ["clean-marvel"]
    verbs: ["get", "patch"]

---
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: secret-rotator-binding
  namespace: clean-marvel-prod
subjects:
  - kind: ServiceAccount
    name: secret-rotator-sa
roleRef:
  kind: Role
  name: secret-rotator-role
  apiGroup: rbac.authorization.k8s.io
```

---

## Capa 5: Pod Security Standards

### 🔒 Objetivo
Aplicar **Pod Security Admission** para prevenir pods privilegiados, root containers, etc.

### ✅ Habilitar Pod Security Admission

```yaml
# k8s/namespace-with-psa.yaml (MEJORADO)
apiVersion: v1
kind: Namespace
metadata:
  name: clean-marvel-prod
  labels:
    # Pod Security Standards (v1.25+)
    pod-security.kubernetes.io/enforce: restricted  # ✅ Más estricto
    pod-security.kubernetes.io/audit: restricted
    pod-security.kubernetes.io/warn: restricted
```

**Niveles:**
- **privileged**: Sin restricciones (inseguro)
- **baseline**: Previene escalaciones obvias
- **restricted**: Hardening fuerte (recomendado)

---

### ✅ Adaptar Deployments a `restricted`

```yaml
# k8s/clean-marvel-deployment.yaml (HARDENED)
spec:
  template:
    spec:
      # Security Context a nivel de Pod
      securityContext:
        runAsNonRoot: true           # ✅ No permitir root
        runAsUser: 1000              # ✅ UID específico
        fsGroup: 1000
        seccompProfile:              # ✅ Seccomp profile
          type: RuntimeDefault
      
      containers:
        - name: clean-marvel
          image: 20luisma/clean-marvel:v1.0.0
          
          # Security Context a nivel de Container
          securityContext:
            allowPrivilegeEscalation: false  # ✅ No escalación
            readOnlyRootFilesystem: true     # ✅ Filesystem inmutable
            runAsNonRoot: true
            runAsUser: 1000
            capabilities:
              drop:
                - ALL                         # ✅ Drop todas las capabilities
              # add: ["NET_BIND_SERVICE"]     # Solo agregar si es necesario
          
          # Volumes para permitir escrituras específicas
          volumeMounts:
            - name: tmp
              mountPath: /tmp
            - name: cache
              mountPath: /var/www/html/cache
            - name: logs
              mountPath: /var/www/html/logs
          
          # ... resto (ports, probes, resources)
      
      volumes:
        - name: tmp
          emptyDir: {}
        - name: cache
          emptyDir:
            sizeLimit: 500Mi
        - name: logs
          emptyDir:
            sizeLimit: 1Gi
```

**Nota:** Si `readOnlyRootFilesystem: true` rompe la app, identifica qué directorios necesitan escritura y mónttalos como `emptyDir`.

---

### ✅ OPA/Gatekeeper para Políticas Personalizadas

```bash
# Instalar Gatekeeper
kubectl apply -f https://raw.githubusercontent.com/open-policy-agent/gatekeeper/master/deploy/gatekeeper.yaml
```

```yaml
# k8s/gatekeeper-policies.yaml (EJEMPLO)

# Política: Requiere que todas las imágenes vengan de registry aprobado
apiVersion: templates.gatekeeper.sh/v1
kind: ConstraintTemplate
metadata:
  name: allowedrepos
spec:
  crd:
    spec:
      names:
        kind: AllowedRepos
      validation:
        openAPIV3Schema:
          properties:
            repos:
              type: array
              items:
                type: string
  targets:
    - target: admission.k8s.gatekeeper.sh
      rego: |
        package allowedrepos
        
        violation[{"msg": msg}] {
          container := input.review.object.spec.containers[_]
          not startswith(container.image, input.parameters.repos[_])
          msg := sprintf("Image '%v' no viene de registry aprobado", [container.image])
        }

---
# Aplicar política
apiVersion: constraints.gatekeeper.sh/v1beta1
kind: AllowedRepos
metadata:
  name: repo-is-dockerhub
spec:
  match:
    kinds:
      - apiGroups: [""]
        kinds: ["Pod"]
    namespaces: ["clean-marvel-prod"]
  parameters:
    repos:
      - "20luisma/"  # Solo imágenes de tu usuario
      - "docker.io/20luisma/"
```

---

## Capa 6: Image Security

### 🔒 Objetivo
Garantizar que solo **imágenes escaneadas, firmadas y aprobadas** se desplieguen.

### ✅ Image Scanning en CI/CD

```yaml
# .github/workflows/security-scan.yml
name: Security Scan

on:
  push:
    branches: [main]
  pull_request:

jobs:
  trivy-scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Build image
        run: docker build -t 20luisma/clean-marvel:${{ github.sha }} .
      
      - name: Run Trivy vulnerability scanner
        uses: aquasecurity/trivy-action@master
        with:
          image-ref: '20luisma/clean-marvel:${{ github.sha }}'
          format: 'sarif'
          output: 'trivy-results.sarif'
          severity: 'CRITICAL,HIGH'
          exit-code: '1'  # ✅ Fallar build si hay vulnerabilidades
      
      - name: Upload Trivy results to GitHub Security
        uses: github/codeql-action/upload-sarif@v2
        with:
          sarif_file: 'trivy-results.sarif'
  
  grype-scan:
    runs-on: ubuntu-latest
    steps:
      - name: Scan with Grype
        uses: anchore/scan-action@v3
        with:
          image: "20luisma/clean-marvel:${{ github.sha }}"
          fail-build: true
          severity-cutoff: high
```

---

### ✅ Image Signing con Cosign

```bash
# Generar par de claves
cosign generate-key-pair

# Firmar imagen
cosign sign --key cosign.key 20luisma/clean-marvel:v1.0.0

# Verificar firma
cosign verify --key cosign.pub 20luisma/clean-marvel:v1.0.0
```

**Admission Controller para verificar firmas:**
```yaml
# Sigstore Policy Controller (requiere instalación previa)
apiVersion: policy.sigstore.dev/v1beta1
kind: ClusterImagePolicy
metadata:
  name: require-signed-images-clean-marvel
spec:
  images:
    - glob: "20luisma/clean-marvel*"
    - glob: "20luisma/openai-service*"
    - glob: "20luisma/rag-service*"
  authorities:
    - key:
        data: |
          -----BEGIN PUBLIC KEY-----
          MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE...
          -----END PUBLIC KEY-----
```

---

## Capa 7: Runtime Security

### 🔒 Objetivo
Detectar y bloquear **comportamientos anómalos en runtime** (ejecuciones de shell, accesos no autorizados, etc.).

### ✅ Falco para Runtime Detection

#### Instalación

```bash
helm repo add falcosecurity https://falcosecurity.github.io/charts
helm install falco falcosecurity/falco \
  --namespace falco-system --create-namespace \
  --set falco.grpc.enabled=true \
  --set falco.grpcOutput.enabled=true
```

#### Reglas Personalizadas

```yaml
# k8s/falco-rules.yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: falco-rules
  namespace: falco-system
data:
  custom-rules.yaml: |
    # Detectar shells ejecutadas en pods de producción
    - rule: Terminal shell in container
      desc: A shell was spawned inside a container
      condition: >
        spawned_process and
        container.namespace = "clean-marvel-prod" and
        proc.name in (bash, sh, zsh, fish)
      output: >
        Shell spawned in container
        (user=%user.name container=%container.name pod=%k8s.pod.name
        command=%proc.cmdline)
      priority: WARNING
      tags: [shell, mitre_execution]
    
    # Detectar acceso modificación de /etc
    - rule: Write below etc in container
      desc: An attempt to write below /etc directory
      condition: >
        write and
        container.namespace = "clean-marvel-prod" and
        fd.name startswith /etc
      output: >
        Write below /etc detected
        (user=%user.name file=%fd.name container=%container.name)
      priority: ERROR
      tags: [filesystem]
    
    # Detectar exfiltración de secrets
    - rule: Read sensitive file in container
      desc: Attempt to read sensitive files
      condition: >
        open_read and
        container.namespace = "clean-marvel-prod" and
        fd.name in (
          /run/secrets/kubernetes.io/serviceaccount/token,
          /var/run/secrets/kubernetes.io/serviceaccount/token
        )
      output: >
        Sensitive file read
        (user=%user.name file=%fd.name container=%container.name)
      priority: CRITICAL
      tags: [secrets]
    
    # Detectar conexiones salientes sospechosas
    - rule: Unexpected outbound connection
      desc: Outbound connection to unexpected destination
      condition: >
        outbound and
        container.namespace = "clean-marvel-prod" and
        not fd.sip in (
          "10.0.0.0/8",
          "172.16.0.0/12",
          "192.168.0.0/16"
        )
      output: >
        Unexpected outbound connection
        (container=%container.name dest=%fd.sip:%fd.sport)
      priority: WARNING
      tags: [network]
```

---

### ✅ Integración Falco → Sentry/Slack

```yaml
# k8s/falco-sidekick.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: falco-sidekick
  namespace: falco-system
spec:
  replicas: 1
  selector:
    matchLabels:
      app: falco-sidekick
  template:
    metadata:
      labels:
        app: falco-sidekick
    spec:
      containers:
        - name: falco-sidekick
          image: falcosecurity/falco-sidekick:latest
          env:
            - name: SLACK_WEBHOOKURL
              value: "https://hooks.slack.com/services/YOUR/WEBHOOK"
            - name: SLACK_MINIMUMPRIORITY
              value: "warning"
            
            - name: SENTRY_DSN
              valueFrom:
                secretKeyRef:
                  name: sentry-secret
                  key: dsn
            - name: SENTRY_MINIMUMPRIORITY
              value: "error"
```

---

## Capa 8: Audit y Compliance

### 🔒 Objetivo
Mantener **logs de auditoría completos** y generar reportes de compliance.

### ✅ Logging Centralizado con Loki

```bash
# Instalar Loki Stack (Loki + Promtail + Grafana)
helm repo add grafana https://grafana.github.io/helm-charts
helm install loki grafana/loki-stack \
  --namespace logging --create-namespace \
  --set grafana.enabled=true \
  --set prometheus.enabled=true \
  --set promtail.enabled=true
```

**Queries útiles en Grafana:**
```logql
# Todos los logs del namespace
{namespace="clean-marvel-prod"}

# Errores de autenticación
{namespace="clean-marvel-prod"} |= "401" or "403"

# Accesos a secrets
{namespace="clean-marvel-prod"} |= "secret"

# Tráfico desde IPs sospechosas
{namespace="clean-marvel-prod"} |~ "suspicious-country-code"
```

---

### ✅ Compliance Scanning con Kube-bench

```bash
# Ejecutar CIS Benchmark
kubectl apply -f https://raw.githubusercontent.com/aquasecurity/kube-bench/main/job.yaml

# Ver resultados
kubectl logs -l app=kube-bench

# Exportar reporte
kubectl logs -l app=kube-bench > kube-bench-report.txt
```

---

## Capa 9: Data Protection

### 🔒 Objetivo
Proteger **datos en tránsito y en reposo**.

### ✅ Encryption in Transit

```yaml
# Service Mesh (Istio) para mTLS automático
apiVersion: security.istio.io/v1beta1
kind: PeerAuthentication
metadata:
  name: default
  namespace: clean-marvel-prod
spec:
  mtls:
    mode: STRICT  # ✅ Requiere mTLS para todo el tráfico interno
```

**O con Linkerd (más ligero):**
```bash
linkerd install | kubectl apply -f -
linkerd inject k8s/clean-marvel-deployment.yaml | kubectl apply -f -
```

---

### ✅ Encryption at Rest (etcd)

```yaml
# /etc/kubernetes/encryption-config.yaml
apiVersion: apiserver.config.k8s.io/v1
kind: EncryptionConfiguration
resources:
  - resources:
      - secrets
      - configmaps  # Opcional
    providers:
      - aescbc:
          keys:
            - name: key1
              secret: <BASE64_32_BYTES>  # head -c 32 /dev/urandom | base64
      - identity: {}  # Fallback para recursos ya existentes sin cifrar
```

**Aplicar:**
```bash
# 1. Crear el archivo en todos los masters
# 2. Actualizar kube-apiserver.yaml con:
#    --encryption-provider-config=/etc/kubernetes/encryption-config.yaml
# 3. Reiniciar API server
# 4. Re-cifrar secrets existentes
kubectl get secrets --all-namespaces -o json | kubectl replace -f -
```

---

## Capa 10: Incident Response

### 🔒 Objetivo
Tener **playbooks claros** para responder a incidentes de seguridad.

### ✅ Incident Response Playbook

#### 1. Detección de Pod Comprometido

```bash
# Aislar pod (quitar de Service)
kubectl label pod <pod-name> quarantine=true --overwrite
kubectl patch service clean-marvel -p '{"spec":{"selector":{"quarantine":"!true"}}}'

# Capturar estado
kubectl describe pod <pod-name> > /tmp/pod-forensics.txt
kubectl logs <pod-name> --all-containers > /tmp/pod-logs.txt

# Obtener shell para investigación (NO en producción crítica)
kubectl exec -it <pod-name> -- /bin/sh

# Eliminar pod comprometido
kubectl delete pod <pod-name>
```

#### 2. Detección de Secret Leak

```bash
# Rotar inmediatamente todos los secrets
./scripts/rotate-all-secrets.sh

# Reiniciar todos los pods
kubectl rollout restart deployment -n clean-marvel-prod

# Auditar accesos recientes a secrets
kubectl get events --all-namespaces --field-selector reason=SecretCreated
kubectl logs -n kube-system -l component=kube-apiserver | grep "secrets"
```

#### 3. Ataque DDoS Detectado

```bash
# Aumentar rate limit en Ingress
kubectl annotate ingress clean-marvel-web \
  nginx.ingress.kubernetes.io/rate-limit="10" --overwrite

# Escalar horizontalmente
kubectl scale deployment/clean-marvel --replicas=10

# Activar CloudFlare (si aplica)
# O configurar IP blocking en nivel de cloud provider
```

---

### ✅ Checklist de Post-Incident

- [ ] **Root Cause Analysis** completado
- [ ] **Timeline** del incidente documentado
- [ ] **Secrets rotados** si hubo exposición
- [ ] **Patches aplicados** para prevenir recurrencia
- [ ] **Políticas actualizadas** (NetworkPolicies, RBAC, etc.)
- [ ] **Equipo notificado** y lecciones aprendidas compartidas
- [ ] **Compliance reportado** (GDPR breach notification si aplica)

---

## Security Checklist Completo

### 🔴 Crítico (Antes de Producción)

#### Control Plane
- [ ] API Server sin `--anonymous-auth`
- [ ] etcd con client cert auth habilitado
- [ ] Audit logging activo y centralizado
- [ ] Secrets cifrados en etcd

#### RBAC & Identities
- [ ] Service Accounts dedicados por componente
- [ ] `automountServiceAccountToken: false` donde no se necesite
- [ ] RBAC con mínimo privilegio aplicado
- [ ] No uso del namespace `default`

#### Network Security
- [ ] Default Deny NetworkPolicies activas
- [ ] Allow explícito solo para tráfico necesario
- [ ] Ingress con TLS obligatorio
- [ ] Rate limiting configurado

#### Secrets
- [ ] Sin secrets en plaintext en Git
- [ ] Sealed Secrets o External Secrets implementado
- [ ] Rotación de secrets programada

#### Pod Security
- [ ] Pod Security Admission en nivel `restricted`
- [ ] `runAsNonRoot: true` en todos los containers
- [ ] `readOnlyRootFilesystem: true` donde sea posible
- [ ] Capabilities dropped (`drop: [ALL]`)

#### Image Security
- [ ] Tags inmutables (no `:latest`)
- [ ] Image scanning en CI/CD (Trivy/Grype)
- [ ] Imágenes firmadas con Cosign
- [ ] Solo registry aprobado

### 🟠 Alta Prioridad

- [ ] Runtime security (Falco) instalado
- [ ] Service Mesh para mTLS (Istio/Linkerd)
- [ ] Compliance scanning (kube-bench) ejecutado
- [ ] Logging centralizado (Loki/ELK)
- [ ] Backup automatizado de etcd
- [ ] Incident Response Playbook documentado
- [ ] Security training para equipo

### 🟡 Mejoras Continuas

- [ ] Chaos Engineering (Chaos Mesh)
- [ ] Penetration testing programado
- [ ] Bug Bounty program considerado
- [ ] Security Champions designados
- [ ] Compliance certifications (SOC2, ISO27001)

---

## Conclusión

Este documento proporciona una **guía exhaustiva de hardening** para Kubernetes, aplicando el mismo rigor de seguridad multicapa que ya existe en la aplicación Clean Marvel Album.

**Recordatorio:**
- ✅ La seguridad es un **proceso continuo**, no un estado
- 🔄 Revisa y actualiza estas políticas **regularmente**
- 🎓 Educa al equipo en **mejores prácticas**
- 📊 Mide y monitorea **continuamente**

---

## Referencias

- [CIS Kubernetes Benchmark](https://www.cisecurity.org/benchmark/kubernetes)
- [NSA/CISA Kubernetes Hardening Guide](https://media.defense.gov/2022/Aug/29/2003066362/-1/-1/0/CTR_KUBERNETES_HARDENING_GUIDANCE_1.2_20220829.PDF)
- [OWASP Kubernetes Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Kubernetes_Security_Cheat_Sheet.html)
- [Falco Rules](https://github.com/falcosecurity/rules)
- [Kubernetes Security Best Practices](https://kubernetes.io/docs/concepts/security/)
