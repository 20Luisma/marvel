# ADR-010 – Value Objects para Configuración Tipada

## Estado
Accepted

## Contexto
El sistema de bootstrap (`AppBootstrap`, `SecurityBootstrap`) utilizaba arrays asociativos `array<string, mixed>` para pasar configuración de seguridad. Esto causaba:

- **Falta de autocompletado** en IDEs
- **Errores en tiempo de ejecución** por claves mal escritas
- **Valores por defecto dispersos** en múltiples lugares
- **Sin validación de invariantes** (ej: email válido, TTL > 0)
- **PHPStan no podía validar** tipos en arrays genéricos

## Decisión
Implementar **Value Objects inmutables** para encapsular configuración tipada, comenzando con `SecurityConfig`:

```php
final readonly class SecurityConfig
{
    public function __construct(
        public string $adminEmail,
        public string $adminPasswordHash,
        public int $sessionTtlMinutes = 30,
        public int $sessionLifetimeHours = 8,
        // ...
    ) {
        $this->validate();
    }
}
```

### Características

| Característica | Implementación |
|----------------|----------------|
| **Inmutabilidad** | `readonly class` (PHP 8.2+) |
| **Validación** | Constructor con `validate()` que lanza `InvalidArgumentException` |
| **Factory methods** | `fromEnv()` para producción, `forTesting()` para tests |
| **Serialización** | `toArray()` que excluye datos sensibles |
| **Helpers** | `verifyPassword()`, `sessionTtlSeconds()`, etc. |

## Justificación

- **Type Safety**: PHPStan puede verificar todos los accesos a propiedades
- **Fail-Fast**: Errores de configuración detectados al iniciar, no en runtime
- **Self-Documenting**: El constructor documenta qué parámetros existen y sus tipos
- **Testable**: Factory `forTesting()` facilita tests sin configurar env vars
- **Reducción de exposición accidental**: `toArray()` no expone `adminPasswordHash`

## Uso

```php
// Producción: lee de $_ENV / getenv()
$config = SecurityConfig::fromEnv();

// Tests: valores predefinidos sin env
$config = SecurityConfig::forTesting();

// Manual: control total
$config = new SecurityConfig(
    adminEmail: 'admin@example.com',
    adminPasswordHash: password_hash('secret', PASSWORD_BCRYPT),
    sessionTtlMinutes: 60
);

// Uso
if ($config->verifyPassword($userInput)) {
    // Login exitoso
}
```

## Consecuencias

### Positivas
- Autocompletado en IDE
- Errores de tipo detectados por PHPStan
- Validación centralizada con mensajes claros
- Inmutabilidad garantizada por el lenguaje

### Negativas
- Más archivos (1 clase + 1 test por Value Object)
- Migración gradual requerida (no rompe código existente)

## Archivos

- `src/Bootstrap/Config/SecurityConfig.php` - Value Object
- `tests/Bootstrap/Config/SecurityConfigTest.php` - tests de validación del Value Object

## Próximos Pasos (Opcional)

1. **`PersistenceConfig`**: DSN, credenciales, timeouts de BD
2. **`ObservabilityConfig`**: DSN Sentry, nivel de logging
3. **`MicroservicesConfig`**: URLs de OpenAI/RAG, timeouts
4. **Integrar en bootstrap**: Reemplazar arrays por Value Objects en `AppBootstrap::init()`

## Referencias

- Martin Fowler: Value Object pattern
- Matthias Noback: "Object Design Style Guide" (2019)
- ADR-007: Bootstrap modularization (contexto original)
