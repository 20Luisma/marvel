# ADR-018: Refactor de Capa de Aplicación (GenerateComicUseCase)

## Estado
✅ Implementado

## Fecha
2026-02-12

## Contexto
El `ComicController` estaba acumulando excesiva responsabilidad (**Fat Controller**). Realizaba tareas de:
1. Validación de entrada (IDs de héroes).
2. Orquestación de búsqueda de entidades (usando `FindHeroUseCase`).
3. Gestión de la interfaz de IA (`ComicGeneratorInterface`).
4. Manejo de excepciones de negocio y de infraestructura.

Esto violaba el **Single Responsibility Principle (SRP)** y dificultaba el testeo de la lógica de negocio sin levantar el entorno HTTP completo.

## Decisión
Extraer la orquestación de la generación de cómics a un servicio de aplicación dedicado: `App\Application\Comics\GenerateComicUseCase`.

El flujo queda ahora así:
1. **Controller**: Recibe el JSON, comprueba que `heroIds` existe, llama al Use Case y devuelve éxito/error. (**Skinny Controller**).
2. **Use Case**: Valida reglas de negocio, coordina la búsqueda de héroes, valida que haya suficientes héroes válidos y ejecuta la generación mediante el cliente de IA.

## Ventajas
- **SRP Violado → Corregido**: El controlador solo sabe de HTTP. La aplicación solo sabe de Cómics.
- **Reutilización**: Esta lógica de generación ahora podría llamarse desde un comando CLI o un job programado sin tocar el controlador.
- **Testabilidad**: Es mucho más sencillo crear un test unitario para `GenerateComicUseCase` mockeando el repositorio y la IA, sin necesidad de fingir peticiones JSON.
- **Mantenibilidad**: El código es más legible al estar dividido en piezas pequeñas y enfocadas.

## Archivos Clave
- `src/Application/Comics/GenerateComicUseCase.php` (Nueva Lógica)
- `src/Controllers/ComicController.php` (Refactorizado a Skinny)
- `src/Bootstrap/AppBootstrap.php` (Inyección de dependencias)

## Notas de Implementación
Se ha mantenido una validación básica en el controlador para devolver un error `422` rápido si el payload es nulo o vacío, evitando llamadas innecesarias a la capa de aplicación.
