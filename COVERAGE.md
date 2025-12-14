# Cobertura de código (PHPUnit)

## Métrica verificada

Este repositorio mide la cobertura de **código PHP en `src/`** mediante PHPUnit (Clover).

Para obtener el valor actual de cobertura:

```bash
composer test:coverage
```

El reporte se genera en `coverage.xml` y el pipeline CI aplica un umbral mínimo con `scripts/coverage-gate.php`.

### Alcance de cobertura

- Incluido: `src/` (lógica PHP medida por PHPUnit; ver `coverage.xml`)
- Excluido: `public/` y `views/` (no se mide con PHPUnit; se valida con tests E2E y auditorías de navegador)

### Desglose

| Directory | Coverage | Verificación |
|-----------|----------|--------------|
| `src/` | **90.45%** (statements) | `coverage.xml` (Clover) |
| `public/`, `views/` | N/A (no se mide con PHPUnit) | Tests E2E y auditorías en CI |

\* *El frontend (assets/vistas) se valida con herramientas de navegador (Playwright) y auditorías de accesibilidad/rendimiento en CI. No se refleja en la cobertura de PHPUnit.*

### Configuración SonarCloud

```properties
sonar.sources=src
sonar.tests=tests
```

Esto hace que la métrica de cobertura se aplique únicamente a `src/` (código PHP cubierto por PHPUnit).

### Umbral en CI

El workflow `.github/workflows/ci.yml` ejecuta un "coverage gate" con `scripts/coverage-gate.php` (umbral configurable con `COVERAGE_THRESHOLD`).

```bash
COVERAGE_THRESHOLD=75 php scripts/coverage-gate.php coverage.xml
```

---

Nota: el porcentaje exacto puede variar con cambios en el repositorio. Para recalcularlo, ejecuta los comandos anteriores o revisa los logs de GitHub Actions.
