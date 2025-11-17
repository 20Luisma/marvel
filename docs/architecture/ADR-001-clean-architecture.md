# ADR-001 – Elección de Clean Architecture en PHP

## Estado
Accepted

## Contexto
El proyecto necesita una base didáctica que muestre la separación de responsabilidades para álbumes, héroes y microservicios IA, sin acoplar reglas de negocio a detalles HTTP o de infraestructura.

## Decisión
Adoptar una arquitectura limpia organizada en capas: Presentación (vistas, router, controladores), Aplicación (casos de uso y servicios), Dominio (entidades, eventos, interfaces) e Infraestructura (adaptadores JSON/DB, EventBus). El front controller `public/index.php` despacha a `Src\Shared\Http\Router`, y `src/bootstrap.php` configura dependencias y resoluciones de servicio.

## Justificación
- Permite enseñar cómo fluyen las dependencias (Presentation → Application → Domain) mientras se mantiene un backend funcional.  
- Facilita pruebas unitarias y de integración por cada capa.  
- Aislamos los microservicios (OpenAI, RAG) fuera del dominio principal, lo que refuerza el ejemplo educativo.

## Consecuencias
### Positivas
- Fácil de explicar a nuevos desarrolladores por capas bien delimitadas.  
- Los adaptadores (JSON, PDO, eventos, microservicios) se pueden sustituir sin tocar la lógica del dominio.  
### Negativas
- Mayor cantidad de archivos y bootstrap detallado.  
- Requiere coordinar la configuración manual de `service.php` y `.env` para cada entorno.

## Opciones descartadas
- Integrar todos los servicios directamente en un monolito sin capas (demasiado acoplado).  
- Exponer vistas y lógica de negocio en la misma clase (rompe la separación de responsabilidades).

## Supersede
Ninguno hasta la fecha. En futuras revisiones, referenciar este ADR si se cambia el patrón fundamental.
