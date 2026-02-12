# ADR-017: Desacoplamiento del Cliente LLM (Dependency Inversion)

## Estado
✅ Implementado

## Fecha
2026-02-12

## Contexto

El `ComicController` dependía directamente de la clase concreta `OpenAIComicGenerator`. Esta dependencia directa violaba el **Principio de Inversión de Dependencias** (DIP - SOLID) y creaba varios problemas:

1. **Vendor lock-in**: Cambiar de OpenAI a otro proveedor (Claude, Gemini, Llama) requeriría modificar el controller y toda la cadena de inyección.
2. **Testabilidad limitada**: No se podía inyectar un mock o stub fácilmente para tests unitarios del controller.
3. **Acoplamiento innecesario**: La lógica de negocio (generar un cómic) no debería saber qué LLM se usa internamente.

## Decisión

Extraer una interfaz `ComicGeneratorInterface` que defina el contrato mínimo para cualquier generador de cómics basado en LLM:

```php
interface ComicGeneratorInterface
{
    public function isConfigured(): bool;
    public function generateComic(array $heroes): array;
}
```

`OpenAIComicGenerator` se convierte en un **adapter** que implementa esta interfaz. Todos los consumers (controllers, servicios) dependen de la abstracción, no del concreto.

## Arquitectura resultante

```
ComicController ──depends on──→ ComicGeneratorInterface (abstracción)
                                        ↑ implements
                               OpenAIComicGenerator (adapter actual)
                               ClaudeComicGenerator (futuro)
                               GeminiComicGenerator (futuro)
```

## Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `src/AI/ComicGeneratorInterface.php` | **Nuevo** — Contrato abstracto |
| `src/AI/OpenAIComicGenerator.php` | Añadido `implements ComicGeneratorInterface` |
| `src/Controllers/ComicController.php` | Type-hint cambiado a la interfaz |
| `src/Shared/Http/Router.php` | `instanceof` check contra la interfaz |

## Consecuencias

### Positivas
- **Intercambiable**: Se puede cambiar de proveedor LLM con una sola línea en el bootstrap
- **Testable**: Los tests pueden inyectar un `FakeComicGenerator` que devuelve datos fijos
- **Extensible**: Nuevos proveedores solo necesitan implementar 2 métodos
- **SOLID**: Cumple DIP (Dependency Inversion) y OCP (Open/Closed Principle)

### Negativas
- Ninguna significativa. La interfaz es minimal (2 métodos) y no añade complejidad innecesaria

## Patrón aplicado
- **Strategy Pattern** (GoF) — La interfaz define la estrategia, los adapters la implementan
- **Dependency Inversion Principle** (SOLID-D) — Los módulos de alto nivel no dependen de los de bajo nivel
