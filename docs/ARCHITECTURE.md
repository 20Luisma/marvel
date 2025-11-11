# 🧱 Arquitectura del Proyecto — Clean Marvel Album

Este documento describe la estructura de **Arquitectura Limpia (Clean Architecture)** implementada en el proyecto.

## Capas principales

```
Presentation (public/, src/Controllers)
└── Application (UseCases, DTOs)
    └── Domain (Entities, Repositories, Events)
        └── Infrastructure (JSON Persistence, EventBus)
```

- **Presentation:** Maneja las peticiones HTTP y renderiza vistas o JSON.
- **Application:** Contiene la lógica de casos de uso.
- **Domain:** Modela las entidades y sus reglas de negocio.
- **Infrastructure:** Implementa persistencia, bus de eventos y adaptadores externos.

### Flujo típico
1. El usuario realiza una petición al controlador.
2. El controlador invoca el caso de uso correspondiente.
3. El caso de uso manipula entidades del dominio.
4. Se publican eventos en el EventBus.
5. Los manejadores de eventos generan notificaciones o acciones adicionales.
