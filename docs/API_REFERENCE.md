# 🌐 Referencia de API — Clean Marvel Album

### 📂 Álbumes
| Método | Endpoint | Descripción |
|---------|-----------|-------------|
| GET | `/albums` | Lista todos los álbumes |
| POST | `/albums` | Crea un álbum nuevo |
| DELETE | `/albums/{albumId}` | Elimina un álbum |

### 🦸‍♂️ Héroes
| Método | Endpoint | Descripción |
|---------|-----------|-------------|
| GET | `/albums/{albumId}/heroes` | Lista héroes del álbum |
| POST | `/albums/{albumId}/heroes` | Crea un héroe nuevo |
| DELETE | `/heroes/{heroId}` | Elimina un héroe |

### 🔔 Notificaciones
| Método | Endpoint | Descripción |
|---------|-----------|-------------|
| GET | `/notifications` | Lista notificaciones |
| DELETE | `/notifications` | Limpia el log |

### 🤖 IA Cómics
| Método | Endpoint | Descripción |
|---------|-----------|-------------|
| POST | `/comics/generate` | Genera un cómic con héroes seleccionados |
