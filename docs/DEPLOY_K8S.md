# Despliegue en Kubernetes (Clean Marvel Album)

Esta guía formaliza el despliegue en Kubernetes de la aplicación principal y de los microservicios IA. Se asume un clúster con Ingress Controller NGINX y acceso a un registro de contenedores. Todos los manifiestos residen en `k8s/` y usan etiquetas `app` + `tier` para selección coherente de pods.

## 1) Construcción de imágenes Docker

Use las etiquetas de los manifiestos (`:latest` por defecto) o sustituya por tags inmutables (`:sha-<commit>`, `:vX.Y.Z`):

- **Aplicación principal** (Dockerfile de referencia, al no existir en la raíz):

```Dockerfile
FROM php:8.2-apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN a2enmod rewrite \
 && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN php -r "copy('https://getcomposer.org/installer','composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php \
    && composer install --no-dev --optimize-autoloader --no-interaction
COPY . .
RUN chown -R www-data:www-data /var/www/html
EXPOSE 8080
CMD ["php","-S","0.0.0.0:8080","-t","public"]
```

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

## 5) Notas para Máster / Tribunal

- La solución está orquestada para Kubernetes con Deployments escalables, Services `ClusterIP` e Ingress NGINX con reglas separadas para frontend y microservicios.
- ConfigMaps aíslan parámetros no sensibles; Secrets concentran tokens y claves, facilitando la gestión externa (Sealed Secrets/External Secrets).
- Los manifiestos contemplan probes, recursos mínimos y etiquetado consistente (`app`, `tier`) para simplificar observabilidad y políticas.
- El pipeline recomendado es: tests y análisis estático → build/push de imágenes → `kubectl apply -f k8s/` → validación de rollout.
- El host del Ingress es un placeholder (`clean-marvel.local`); debe ajustarse al dominio real y acompañarse de TLS gestionado (cert-manager o secreto TLS).
- Todo el despliegue es opcional: la aplicación sigue operando en los flujos local/hosting tradicionales, pero queda lista para contenedorización y orquestación académica.
