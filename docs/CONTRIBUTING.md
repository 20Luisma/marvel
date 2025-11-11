# 🤝 Contribuyendo al proyecto — Clean Marvel Album

Gracias por contribuir. Este proyecto sigue principios de **arquitectura limpia y buenas prácticas SOLID**.

## Cómo colaborar
1. Clona el repositorio  
2. Ejecuta `composer install`
3. Inicia el servidor con `php -S localhost:8080 -t public`
4. Ejecuta QA antes del push (`🧪 QA completo` task)

## Estilo de commits
Usa prefijos semánticos:
- `feat:` nueva funcionalidad  
- `fix:` corrección  
- `docs:` documentación  
- `refactor:` refactorización sin cambio funcional

## Normas de código
- Sigue **PSR-12**.  
- No mezcles lógica de dominio con controladores.  
- Cada módulo (`Albums`, `Heroes`, etc.) mantiene su propio subdominio.
