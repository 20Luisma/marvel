---

# 🔥 Microservicio Heatmap – Python + Flask + Docker + Google Cloud

El sistema de mapa de calor del proyecto **Clean Marvel Album** funciona sobre un microservicio externo, totalmente desacoplado del backend PHP. Procesa clics reales desde la web y los almacena en una base de datos interna para ser consultados desde el panel `/secret-heatmap`.

Este servicio corre de forma independiente, en producción, dentro de una instancia de Google Cloud.

---

## 🧱 Arquitectura del Microservicio Heatmap

```
Navegador (JS Tracker)
        ↓
PHP Proxy: /api/heatmap/click.php
        ↓
Microservicio Heatmap (Python + Flask + Docker)
        ↓
SQLite (heatmap.db)
        ↓
PHP Proxy de Lectura → Panel Heatmap
```

---

## 🌍 Tecnologías

- **Python 3.10**
- **Flask 3**
- **docker** + contenedor aislado
- **Google Cloud Compute Engine** (VM Debian)
- **SQLite** como almacenamiento principal
- **Token HTTP (X-API-Token)** para autenticación
- Proxies PHP para abstracción total

---

## 🏗 Estructura del microservicio

```
heatmap-service/
├── app.py
├── heatmap.db              ← SQLite (montado como volumen)
├── Dockerfile
├── requirements.txt
└── .env                    ← API_TOKEN=***
```

---

## 🚀 Endpoints del microservicio

### 🔹 `GET /`
Comprueba que el servicio está vivo.

### 🔹 `GET /health`
Devuelve estado general del servicio.

### 🔹 `POST /track`
Registra un clic.

**Headers:**
```
X-API-Token: <token-valido>
Content-Type: application/json
```

**Body esperado:**
```json
{
  "page_url": "/albums/avengers",
  "x": 0.45,
  "y": 0.30,
  "viewport_width": 1440,
  "viewport_height": 900,
  "scroll_y": 350
}
```

### 🔹 `GET /events`
Devuelve todos los clics ordenados por fecha.

Soporta:
- `limit=`
- `page_url=`

---

## 🔐 Seguridad

El microservicio exige autenticación en **todos los endpoints sensibles** vía cabecera:

```
X-API-Token: <token>
```

El token se almacena únicamente en:

- `.env` del microservicio  
- `.env` del backend PHP en `HEATMAP_API_TOKEN`  

Nunca se expone al navegador.

---

## 🐳 Dockerización completa

El servicio se ejecuta exclusivamente en contenedor:

```bash
sudo docker build -t heatmap-service .
sudo docker run -d   --name heatmap-container   -p 8080:8080   -v /home/luismpallante/heatmap-service/heatmap.db:/app/heatmap.db   --env-file /home/luismpallante/heatmap-service/.env   heatmap-service:latest
```

Volumen montado:

```
/home/luismpallante/heatmap-service/heatmap.db → /app/heatmap.db
```

Garantiza persistencia incluso al reiniciar contenedor o VM.

---

## ☁️ Despliegue en Google Cloud

- VM Debian estándar
- Firewall abierto solo al puerto 8080
- Docker instalado manualmente
- Deploy manual:
  - subir archivos
  - reconstruir imagen
  - reiniciar contenedor

El servicio queda expuesto globalmente en:

```
http://34.74.102.123:8080
```

---

## 🎯 Integración con Clean Marvel Album (PHP)

### 🔹 JavaScript Tracker (frontend)

Se ejecuta globalmente en todas las vistas:

```js
fetch("http://34.74.102.123:8080/track", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    "X-API-Token": "<token>"
  },
  body: JSON.stringify({...})
});
```

### 🔹 Proxy PHP de escritura  
`/api/heatmap/click.php`

Responsabilidades:
- sanitización
- mapping de campos
- autenticación con X-API-Token
- reenvío seguro a /track

### 🔹 Proxy PHP de lectura  
`/api/heatmap/summary.php`  
`/api/heatmap/pages.php`

Ambos llaman a `/events`, adaptan la respuesta y generan:

- grid 20×20
- total de clics
- ranking de páginas
- eventos para modo visual

### 🔹 Variables de entorno (PHP)

`.env`:

```
HEATMAP_API_BASE_URL=http://34.74.102.123:8080
HEATMAP_API_TOKEN=super-secreto-123
```

---

## 📊 Panel de Visualización del Heatmap

Ubicado en:

```
/secret-heatmap
```

Incluye:

- Tabla de KPIs
- Visualización con canvas
- Grid 20×20
- Lista de páginas más clicadas
- Scroll depth y distribución
- Filtros por página

---

## 📦 Base de datos SQLite

Nombre: `heatmap.db`  
Tabla principal:

```sql
CREATE TABLE click_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_url TEXT NOT NULL,
    x REAL NOT NULL,
    y REAL NOT NULL,
    viewport_width INTEGER,
    viewport_height INTEGER,
    scroll_y INTEGER,
    user_agent TEXT,
    created_at TEXT NOT NULL
);
```

Consultar desde la VM:

```bash
sqlite3 heatmap.db "SELECT COUNT(*) FROM click_events;"
```

---

## 🔥 Estado actual

✔ Microservicio operativo en GCP  
✔ Docker funcionando  
✔ Persistencia estable con SQLite  
✔ Integración 100% con Marvel Album  
✔ Datos en tiempo real visibles en `/secret-heatmap`

---
