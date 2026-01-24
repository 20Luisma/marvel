# 🚀 Guía para Clonar la Arquitectura Clean Marvel

Esta guía te ayudará a comunicar correctamente los requisitos cuando quieras crear un nuevo proyecto empresarial basado en la arquitectura de Clean Marvel.

---

## 📋 PLANTILLA DE PROMPT

Copia y completa esta plantilla para crear un nuevo proyecto:

```
Quiero crear un nuevo proyecto basado en la arquitectura de Clean Marvel.

## 1. INFORMACIÓN GENERAL
- **Nombre del proyecto:** [ejemplo: clean-inventory, clean-crm, clean-ecommerce]
- **Descripción breve:** [Una frase que describa el propósito]
- **Tipo de empresa/sector:** [Retail, Fintech, Salud, Educación, etc.]

## 2. DOMINIO DEL NEGOCIO
### Entidades principales (equivalentes a Heroes/Albums):
- **Entidad 1:** [nombre] - [descripción breve]
- **Entidad 2:** [nombre] - [descripción breve]
- **Entidad 3:** [nombre] - [descripción breve]

### Relaciones entre entidades:
- [Entidad1] tiene muchos [Entidad2]
- [Entidad2] pertenece a [Entidad3]

## 3. FUNCIONALIDADES CLAVE
- [ ] CRUD completo de [entidad principal]
- [ ] Sistema de autenticación
- [ ] Dashboard/Panel de control
- [ ] Reportes/Estadísticas
- [ ] Notificaciones
- [ ] Integración con APIs externas
- [ ] Otra: [especificar]

## 4. STACK TECNOLÓGICO
### Mantener de Clean Marvel:
- [ ] PHP 8.2 + Clean Architecture
- [ ] Persistencia dual (JSON local + MySQL hosting)
- [ ] Microservicios de IA (OpenAI/RAG)
- [ ] GitHub Actions CI/CD
- [ ] SonarCloud
- [ ] Sentry
- [ ] Docker/Kubernetes

### Cambiar o añadir:
- [especificar cambios si los hay]

## 5. CONFIGURACIÓN DE PROYECTO
- **Ubicación:** [ruta donde crear el proyecto]
- **Repositorio GitHub:** [nombre-usuario/nombre-repo] (opcional)

## 6. PRIORIDAD DE DESARROLLO
1. [Primera funcionalidad a implementar]
2. [Segunda funcionalidad]
3. [Tercera funcionalidad]

## 7. NOTAS ADICIONALES
[Cualquier consideración especial, restricciones, o detalles importantes]
```

---

## 📖 EJEMPLOS COMPLETOS

### Ejemplo 1: Sistema de Gestión de Inventario

```
Quiero crear un nuevo proyecto basado en la arquitectura de Clean Marvel.

## 1. INFORMACIÓN GENERAL
- **Nombre del proyecto:** clean-inventory
- **Descripción breve:** Sistema de gestión de inventario para almacenes
- **Tipo de empresa/sector:** Retail / Logística

## 2. DOMINIO DEL NEGOCIO
### Entidades principales:
- **Product:** Producto con SKU, nombre, descripción, precio, stock
- **Warehouse:** Almacén físico con ubicación y capacidad
- **Movement:** Movimiento de stock (entrada/salida/transferencia)
- **Supplier:** Proveedor de productos
- **Category:** Categoría de productos

### Relaciones entre entidades:
- Warehouse tiene muchos Products (stock)
- Product pertenece a Category
- Movement afecta a Product en Warehouse
- Product tiene muchos Suppliers

## 3. FUNCIONALIDADES CLAVE
- [x] CRUD completo de productos
- [x] Gestión de almacenes
- [x] Registro de movimientos de stock
- [x] Sistema de autenticación
- [x] Dashboard con métricas de inventario
- [x] Alertas de stock bajo
- [x] Reportes de movimientos
- [ ] Integración con APIs externas (postergar)

## 4. STACK TECNOLÓGICO
### Mantener de Clean Marvel:
- [x] PHP 8.2 + Clean Architecture
- [x] Persistencia dual (JSON local + MySQL hosting)
- [ ] Microservicios de IA (no necesario inicialmente)
- [x] GitHub Actions CI/CD
- [x] SonarCloud
- [x] Sentry
- [x] Docker/Kubernetes

## 5. CONFIGURACIÓN DE PROYECTO
- **Ubicación:** /Users/admin/Desktop/Proyectos/clean-inventory
- **Repositorio GitHub:** miusuario/clean-inventory

## 6. PRIORIDAD DE DESARROLLO
1. Estructura base y autenticación
2. CRUD de productos y categorías
3. Gestión de almacenes y stock
4. Movimientos y reportes
5. Dashboard y alertas

## 7. NOTAS ADICIONALES
- Necesito exportar datos a Excel
- El sistema será usado por 5-10 usuarios simultáneos
- Quiero mantener la misma calidad de documentación que Marvel
```

---

### Ejemplo 2: CRM para Agencia de Marketing

```
Quiero crear un nuevo proyecto basado en la arquitectura de Clean Marvel.

## 1. INFORMACIÓN GENERAL
- **Nombre del proyecto:** clean-crm
- **Descripción breve:** CRM para gestión de clientes y campañas de marketing
- **Tipo de empresa/sector:** Marketing Digital

## 2. DOMINIO DEL NEGOCIO
### Entidades principales:
- **Client:** Cliente con datos de contacto y empresa
- **Campaign:** Campaña de marketing con presupuesto y fechas
- **Lead:** Prospecto/Oportunidad de venta
- **Task:** Tarea asignada a un usuario
- **User:** Usuario del sistema (comercial/admin)
- **Interaction:** Registro de interacciones con clientes

### Relaciones entre entidades:
- Client tiene muchas Campaigns
- Client tiene muchos Leads
- Campaign tiene muchas Tasks
- User gestiona muchos Clients
- Client tiene muchas Interactions

## 3. FUNCIONALIDADES CLAVE
- [x] CRUD completo de clientes
- [x] Gestión de campañas
- [x] Pipeline de leads
- [x] Sistema de autenticación con roles
- [x] Dashboard con KPIs
- [x] Calendario de tareas
- [x] Integración con OpenAI para análisis de leads

## 4. STACK TECNOLÓGICO
### Mantener de Clean Marvel:
- [x] PHP 8.2 + Clean Architecture
- [x] Persistencia dual
- [x] Microservicios de IA (OpenAI para análisis)
- [x] GitHub Actions CI/CD
- [x] Todo el stack de calidad

## 5. CONFIGURACIÓN DE PROYECTO
- **Ubicación:** /Users/admin/Desktop/Proyectos/clean-crm

## 6. PRIORIDAD DE DESARROLLO
1. Autenticación y usuarios
2. Gestión de clientes
3. Campañas y tareas
4. Pipeline de leads
5. Integración IA
6. Dashboard y reportes

## 7. NOTAS ADICIONALES
- Roles: Admin, Comercial, Viewer
- Integrar con Google Calendar en futuro
```

---

### Ejemplo 3: Sistema de Tickets / Help Desk

```
Quiero crear un nuevo proyecto basado en la arquitectura de Clean Marvel.

## 1. INFORMACIÓN GENERAL
- **Nombre del proyecto:** clean-helpdesk
- **Descripción breve:** Sistema de tickets para soporte técnico
- **Tipo de empresa/sector:** IT / Soporte

## 2. DOMINIO DEL NEGOCIO
### Entidades principales:
- **Ticket:** Incidencia con título, descripción, prioridad, estado
- **User:** Usuario (cliente o agente de soporte)
- **Category:** Categoría de tickets (Hardware, Software, Red, etc.)
- **Comment:** Comentario/respuesta en un ticket
- **Attachment:** Archivo adjunto
- **SLA:** Acuerdo de nivel de servicio

### Relaciones entre entidades:
- Ticket pertenece a User (creador)
- Ticket asignado a User (agente)
- Ticket tiene muchos Comments
- Ticket tiene muchos Attachments
- Ticket pertenece a Category
- Category tiene SLA

## 3. FUNCIONALIDADES CLAVE
- [x] Creación y gestión de tickets
- [x] Asignación automática/manual
- [x] Sistema de comentarios
- [x] Autenticación con roles (Cliente/Agente/Admin)
- [x] Dashboard con métricas de SLA
- [x] Notificaciones por email
- [x] Base de conocimiento con RAG

## 4. STACK TECNOLÓGICO
### Mantener de Clean Marvel:
- [x] PHP 8.2 + Clean Architecture
- [x] Persistencia dual
- [x] RAG Service para base de conocimiento
- [x] OpenAI para sugerencias automáticas
- [x] Sistema de notificaciones
- [x] Todo el stack de CI/CD

## 5. CONFIGURACIÓN DE PROYECTO
- **Ubicación:** /Users/admin/Desktop/Proyectos/clean-helpdesk

## 6. PRIORIDAD DE DESARROLLO
1. Usuarios y autenticación
2. CRUD de tickets
3. Sistema de comentarios
4. Asignaciones y estados
5. Notificaciones
6. Dashboard y SLA
7. Integración IA

## 7. NOTAS ADICIONALES
- Prioridades: Crítica, Alta, Media, Baja
- Estados: Abierto, En Progreso, Pendiente, Resuelto, Cerrado
- SLA por categoría y prioridad
```

---

## 🎯 MAPEO DE EQUIVALENCIAS

| Clean Marvel | Tu Nuevo Proyecto |
|--------------|-------------------|
| Heroes | [Entidad principal 1] |
| Albums | [Entidad principal 2] |
| Activities | [Entidad de registro/log] |
| HeroRepository | [NombreEntidad]Repository |
| CreateHeroUseCase | Create[NombreEntidad]UseCase |
| HeroController | [NombreEntidad]Controller |
| OpenAI Service | [Mantener o adaptar] |
| RAG Service | [Mantener o adaptar] |

---

## 📁 ESTRUCTURA QUE SE GENERARÁ

```
clean-[tu-proyecto]/
├── public/                 # Front controller y endpoints
├── src/
│   ├── [Entidad1]/        # (equivalente a Heroes/)
│   │   ├── Domain/
│   │   ├── Application/
│   │   └── Infrastructure/
│   ├── [Entidad2]/        # (equivalente a Albums/)
│   ├── Security/          # Autenticación y autorización
│   ├── Shared/            # Componentes compartidos
│   ├── Controllers/       # Controladores HTTP
│   └── Bootstrap/         # Configuración de la app
├── views/                  # Vistas de presentación
├── storage/                # Persistencia local
├── tests/                  # Tests unitarios e integración
├── docs/
│   ├── architecture/      # ADRs
│   ├── api/               # Documentación de API
│   └── guides/            # Guías de uso
├── .github/workflows/      # CI/CD
├── config/                 # Configuración
├── composer.json
├── phpunit.xml.dist
├── README.md
└── .env.example
```

---

## ✅ CHECKLIST ANTES DE ENVIAR EL PROMPT

- [ ] He definido el nombre del proyecto
- [ ] He identificado al menos 3-5 entidades principales
- [ ] He descrito las relaciones entre entidades
- [ ] He marcado las funcionalidades que necesito
- [ ] He especificado qué partes del stack mantener
- [ ] He indicado la ubicación donde crear el proyecto
- [ ] He establecido prioridades de desarrollo

---

## 💡 CONSEJOS

1. **Sé específico con las entidades**: Cuanto más claro seas con tus entidades y sus atributos, mejor será el resultado.

2. **Piensa en los casos de uso**: ¿Qué acciones necesitas? Crear, listar, buscar, actualizar, eliminar, exportar...

3. **Define roles si aplica**: Admin, Usuario, Visor, etc.

4. **Indica restricciones**: Límites de usuarios, requisitos de rendimiento, integraciones obligatorias.

5. **Prioriza**: No todo tiene que estar en la v1. Indica qué es MVP y qué es futuro.

---

## 🔗 COMANDO RÁPIDO

Una vez tengas todo claro, puedes usar este prompt simplificado:

```
Clona la arquitectura de Clean Marvel para crear [nombre-proyecto]:
- Entidades: [lista de entidades]
- Funcionalidades: [lista de funcionalidades]  
- Ubicación: [ruta]
- Mantener: [componentes del stack a mantener]
```

---

*Última actualización: Enero 2026*
*Basado en: Clean Marvel Album v1.0*
