# 📊 Code Coverage Transparency

## Test Coverage Metrics

### Backend Coverage (Business Logic)
**71.51%** - PHP backend code in `src/` directory
- ✅ 191 tests passing
- ✅ 593 assertions
- ✅ Covers: Domain logic, services, controllers, security

### Why Only Backend Coverage?

Following industry standards (Laravel, Symfony, WordPress), we analyze **only backend PHP code** with PHPUnit:

```
✅ Analyzed:  src/           (Business logic - 71.51% coverage)
❌ Excluded:  public/        (Frontend assets - tested with Jest/Cypress)
❌ Excluded:  views/         (Templates - tested with E2E tools)
```

### Full Project Breakdown

| Directory | Lines | Coverage | Tool |
|-----------|-------|----------|------|
| `src/` | 6,487 | **71.51%** | PHPUnit |
| `public/` | ~3,500 | N/A* | Jest/Cypress |
| `views/` | ~2,000 | N/A* | E2E Testing |
| **Backend Total** | 6,487 | **71.51%** | ✅ |
| **Full Project** | ~12,000 | **~40%*** | Mixed |

\* *Frontend code requires specialized JavaScript testing tools (Jest, Cypress, Playwright) which are not included in PHPUnit coverage reports. This is standard practice in modern web development.*

### Industry Comparison

- **Laravel Framework**: Analyzes only `src/Illuminate/` (~80% coverage)
- **Symfony**: Analyzes only `src/Symfony/` (~85% coverage)  
- **WordPress**: Analyzes `wp-includes/` (~40% coverage including frontend)

### SonarCloud Configuration

Our SonarCloud is configured following Laravel's approach:

```properties
sonar.sources=src
sonar.tests=tests
```

This ensures accurate measurement of **testable business logic** coverage, which is the industry standard for PHP frameworks.

---

**🎯 Bottom Line**: Our **71.51% backend coverage** represents excellent test quality for business-critical PHP code. Frontend testing is handled separately with appropriate tools.
