# Testing del microservicio

## Suite propia
- Configuración dedicada en `rag-service/phpunit.xml`.
- Tests ubicados en `rag-service/tests/` (Application, Infrastructure, fixtures).

## Ejecutar tests

```bash
cd rag-service
../vendor/bin/phpunit
```

## Notas
- No se requiere red; los dobles de prueba evitan llamadas a OpenAI.
- La cobertura se centra en ranking, fallback y carga de conocimiento local.
