# Automatización — Tasks de VS Code

El proyecto incluye un archivo `.vscode/tasks.json` que automatiza el flujo de trabajo diario.

### Servidor de desarrollo
Inicia el servidor PHP embebido:
```bash
php -S localhost:8080 -t public
```

### Ejecutar tests
```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --colors=always --testdox --coverage-clover coverage.xml
```

### PHPStan
```bash
vendor/bin/phpstan analyse --memory-limit=512M
```

### QA completo
Ejecuta PHPUnit → PHPStan → Composer Validate:
```bash
Desde VS Code: "Tasks: Run Task" → seleccionar la tarea de QA (ver etiquetas en `.vscode/tasks.json`)
```
