---

# Microservicio Heatmap – Python + Flask + Docker + Google Cloud

El sistema de mapa de calor del proyecto **Clean Marvel Album** funciona sobre un microservicio externo, totalmente desacoplado del backend PHP. Procesa clics reales desde la web y los almacena en una base de datos interna para ser consultados desde el panel `/secret-heatmap`.

Este servicio corre de forma independiente. En el entorno de hosting se configura un endpoint desplegado en Google Cloud (ver variables de entorno y URLs configuradas en el proyecto).

---

## Arquitectura del microservicio Heatmap

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

## Tecnologías

- **Python 3.10**
- **Flask 3**
- **docker** + contenedor aislado
- **Google Cloud Compute Engine** (VM Debian)
- **SQLite** como almacenamiento principal
- **Token HTTP (X-API-Token)** para autenticación
- Proxies PHP para abstracción total

---

## Estructura del microservicio

```
heatmap-service/
├── app.py
├── heatmap.db              ← SQLite (montado como volumen)
├── Dockerfile
├── requirements.txt
└── .env                    ← API_TOKEN=***
```

---

## Endpoints del microservicio

### `GET /`
Comprueba que el servicio está vivo.

### `GET /health`
Devuelve estado general del servicio.

### `POST /track`
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

### `GET /events`
Devuelve todos los clics ordenados por fecha.

Soporta:
- `limit=`
- `page_url=`

---

## Seguridad

El microservicio exige autenticación en **todos los endpoints sensibles** vía cabecera:

```
X-API-Token: <token>
```

El token se almacena únicamente en:

- `.env` del microservicio  
- `.env` del backend PHP en `HEATMAP_API_TOKEN`  

Nunca se expone al navegador.

---

## Dockerización

El servicio se ejecuta exclusivamente en contenedor:

```bash
sudo docker build -t heatmap-service .
sudo docker run -d   --name heatmap-container   -p 8080:8080   -v /home/luismpallante/heatmap-service/heatmap.db:/app/heatmap.db   --env-file /home/luismpallante/heatmap-service/.env   heatmap-service:latest
```

Volumen montado:

```
/home/luismpallante/heatmap-service/heatmap.db → /app/heatmap.db
```

Permite persistencia incluso al reiniciar contenedor o VM (mientras se mantenga el volumen montado).

---

## ☁️ Despliegue Multi-Cloud (GCP + AWS)
El microservicio está desplegado en dos nubes distintas para garantizar alta disponibilidad:

### 🔵 Nodo Primario: Google Cloud (GCP)
- **URL**: `http://34.74.102.123:8080`
- **Ubicación**: Región `us-east1-b` (USA).
- **Tecnología**: VM e2-micro.

### 🟠 Nodo Secundario: Amazon Web Services (AWS)
- **URL**: `http://35.181.60.162:8080`
- **Ubicación**: Región `eu-west-3` (París).
- **Tecnología**: EC2 t3.micro.

---

## Integración con Clean Marvel Album (PHP)

### Estrategia de Failover
La aplicación principal utiliza un cliente inteligente que monitoriza el estado de los nodos:
1. Siempre intenta enviar el evento al **Nodo Primario (GCP)**.
2. Si falla (timeout o error 5xx), redirige automáticamente el tráfico al **Nodo Secundario (AWS)**.

### JavaScript tracker (frontend)

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

### Proxy PHP de escritura
`/api/heatmap/click.php`

Responsabilidades:
- sanitización
- mapping de campos
- autenticación con X-API-Token
- reenvío seguro a /track

### Proxy PHP de lectura
`/api/heatmap/summary.php`  
`/api/heatmap/pages.php`

Ambos llaman a `/events`, adaptan la respuesta y generan:

- grid 20×20
- total de clics
- ranking de páginas
- eventos para modo visual

### Variables de entorno (PHP)

`.env`:

```
HEATMAP_API_BASE_URL=http://34.74.102.123:8080
HEATMAP_API_TOKEN=your-heatmap-token
```

---

## Panel de visualización del heatmap

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

## Base de datos SQLite

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

## Estado actual

Componentes verificados en el entorno descrito:

- Microservicio operativo en GCP  
- Docker funcionando  
- Persistencia con SQLite  
- Integración con la app (panel `/secret-heatmap`)  

---
