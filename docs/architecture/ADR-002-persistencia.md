# ADR-002 – Persistencia dual JSON + Database fallback

## Estado
Accepted

## Contexto
El despliegue en hosting requiere bases de datos, pero en local es más ágil trabajar con archivos JSON. Se busca mantener consistencia y disponibilidad cuando la BD no está disponible.

## Decisión
Al arrancar, `src/bootstrap.php` comprueba `APP_ENV`.  
- En `local`, se usan repositorios de archivos (`storage/albums.json`, `storage/heroes.json`, `storage/actividad/`).  
- En `hosting`, se intenta abrir una conexión PDO con las credenciales de `.env`. Si la conexión tiene éxito, se instancian `Db*Repository`; en caso de error, se registran los mensajes y se cae al modo JSON como fallback transparente.

## Justificación
- Permite a quienes aprenden el proyecto usar JSON sin configurar bases de datos.  
- Obtenemos una capa persistente en producción sin duplicar lógica.  
- Reduce el impacto de fallos de BD manteniendo un fallback a JSON.

## Consecuencias
### Positivas
- Desarrollo local rápido y reproducible.  
- Hosting con MySQL puede activarse simplemente ajustando `.env` y ejecutando el script `php bin/migrar-json-a-db.php`.
### Negativas
- Se debe documentar bien el fallback (el script CLI migrador y las rutas a JSON).  
- El código de bootstrap debe manejar excepciones y registrar errores para no ocultar fallos de DB.

## Opciones descartadas
- Forzar MySQL en local: rompe la experiencia educativa.  
- Mantener solo JSON en hosting: no aprovecha la estabilidad de BD ni permite consultas SQL avanzadas.

## Supersede
Usar cuando se planee eliminar el fallback o introducir nuevos adaptadores (por ejemplo, persistencia en Redis).
