# Demo Script (10-15 min) - Clean Marvel Album

Este guion permite ejecutar una demo reproducible del proyecto en local. Los pasos estan pensados para un entorno sin red, salvo cuando se usan servicios externos (OpenAI, WAVE, PSI, ElevenLabs).

## Preparacion (2-3 min)
1) Instalar dependencias en la app principal:
```bash
composer install
```
2) Instalar dependencias en microservicios (si se usan en la demo):
```bash
cd openai-service && composer install
cd ../rag-service && composer install
```
3) Configurar `.env` desde `.env.example` en la raiz y en cada microservicio.
- Si no hay claves, dejar placeholders y documentar la limitacion.

## Arranque de servicios (2-3 min)
1) App principal (puerto 8080):
```bash
php -S localhost:8080 -t public
```
2) OpenAI Service (puerto 8081):
```bash
cd openai-service
php -S localhost:8081 -t public
```
3) RAG Service (puerto 8082):
```bash
cd rag-service
php -S localhost:8082 -t public
```

## Demo funcional (6-8 min)
1) Home
- Abrir `http://localhost:8080/`.
- Verificar que la pagina carga y el menu principal esta disponible.

2) Albums
- Navegar a Albums.
- Crear un album (nombre y portada).
- Verificar que aparece en la lista.

3) Heroes
- Navegar a Heroes.
- Crear un heroe asociado al album.
- Verificar que aparece en la lista.

4) Comic IA (si OpenAI Service esta configurado)
- Ir a la seccion de Comic.
- Seleccionar heroes y generar comic.
- Verificar que se recibe una historia con paneles.

5) Comparacion RAG (si `rag-service` esta configurado)
- Ir a la seccion de comparacion.
- Enviar dos heroes.
- Verificar respuesta comparativa.

6) Paneles (si hay claves configuradas)
- Accesibilidad: abrir el panel y ejecutar WAVE.
- Performance: abrir el panel y ejecutar PageSpeed.

7) Reset de Demo (Limpieza)
- Ejecutar la acción de "Restaurar Demo".
- Verificar que los datos creados se eliminan y el sistema vuelve al estado inicial.
- **Nota técnica:** Este endpoint es público por diseño para facilitar este flujo en la demo académica.

## Checklist de "que debe verse"
- [ ] Home carga sin errores visibles.
- [ ] Albums: alta y listado funcionan.
- [ ] Heroes: alta y listado funcionan.
- [ ] Comic IA: se recibe respuesta si OpenAI Service esta operativo.
- [ ] RAG: se recibe respuesta si `rag-service` esta operativo.
- [ ] Paneles: accesibilidad y performance muestran resultados si hay claves.

## Evidencias a capturar
Guardar capturas en `docs/evidence/screenshots/` con fecha.
- Home, Albums, Heroes.
- Comic IA y comparacion RAG (si aplican).
- Paneles (accesibilidad, performance).
- CI en verde (si hay URL).
