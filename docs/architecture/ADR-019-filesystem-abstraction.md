# ADR-019: Abstracción de Filesystem e Inversión de Dependencias en Almacenamiento

## Estado
✅ Implementado

## Fecha
2026-02-12

## Contexto
La subida de portadas de álbumes estaba acoplada directamente al sistema de archivos local mediante funciones nativas de PHP como `move_uploaded_file`. Esto impedía la portabilidad de la aplicación a entornos "cloud-native" (como AWS Lambda o Google Cloud Run) donde el almacenamiento es efímero y se requieren servicios como AWS S3.

Además, el `AlbumController` violaba el SRP al gestionar la lógica de nombres de archivos y rutas físicas además de las peticiones HTTP.

## Decisión
1.  **Abstracción**: Crear `App\Shared\Domain\Filesystem\FilesystemInterface` para definir las operaciones de almacenamiento necesarias (guardar, asegurar directorios, obtener URLs públicas).
2.  **Infraestructura**: Implementar `App\Shared\Infrastructure\Filesystem\LocalFilesystem` para mantener la funcionalidad actual en servidores tradicionales.
3.  **Capa de Aplicación**: Crear `App\Albums\Application\UseCase\UploadAlbumCoverUseCase` para orquestar el proceso (validar álbum, generar nombre seguro, guardar vía abstracción y actualizar entidad).
4.  **Dependency Injection**: Inyectar la interfaz en lugar de la implementación en los servicios.

## Ventajas
- **Flexibilidad Cloud**: Podemos cambiar a un `S3Filesystem` simplemente creando el adapter e inyectándolo en el bootstrap, sin tocar una sola línea de lógica de negocio.
- **Testing sin Efectos Secundarios**: Los tests pueden mockear el `FilesystemInterface` evitando crear archivos reales en el disco durante las pruebas unitarias.
- **Desacoplamiento Total**: La capa de aplicación no sabe dónde se guardan los archivos, solo que "se guardan".

## Archivos Clave
- `src/Shared/Domain/Filesystem/FilesystemInterface.php`
- `src/Shared/Infrastructure/Filesystem/LocalFilesystem.php`
- `src/Albums/Application/UseCase/UploadAlbumCoverUseCase.php`
- `src/Controllers/AlbumController.php` (Refactorizado)
