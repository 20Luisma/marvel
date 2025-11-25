# Guía de arranque rápido

1. **Instala dependencias**  
   - Raíz: `composer install`  
   - Microservicios: `composer install` dentro de `openai-service/` y `rag-service/` si trabajas en esas carpetas.
2. **Configura el entorno**  
   - Copia `.env.example` (si existe) o prepara tu `.env`.  
   - Define `APP_ENV` (`local` para JSON, `hosting` para MySQL con fallback a JSON).  
   - Proporciona credenciales de Sentry, SonarCloud, GitHub (`GITHUB_API_KEY`), OpenAI/ElevenLabs, WAVE, PSI y tokens opcionales según necesites.
3. **Levanta el monolito**  
   - `composer serve` (expone `localhost:8080`).  
   - Alternativa: `php -S localhost:8080 -t public`.
4. **Inicia microservicios IA**  
   - `cd openai-service && php -S localhost:8081 -t public`  
   - `cd rag-service && php -S localhost:8082 -t public`
5. **Verifica el panel**  
   - Accede a `/albums` para ver la UI principal.  
   - Usa los botones de la cabecera para navegar entre cómics, GitHub, SonarCloud y Sentry.  
6. **Logs y persistencia**  
   - Datos y activity logs quedan en `storage/` (JSON).  
   - Puedes migrar a la BD con `php bin/migrar-json-a-db.php` si configuras MySQL y `APP_ENV=hosting`.
7. **Servicios opcionales**  
   - Heatmap: configura `HEATMAP_API_BASE_URL` y `HEATMAP_API_TOKEN` si quieres habilitar `/secret-heatmap` (ver `docs/microservicioheatmap/README.md`).
   - Narración ElevenLabs: activa los botones de audio en `/comic` con `ELEVENLABS_API_KEY`.

Mantén abierto el terminal para ver errores y usa `logs/` o `storage/` si necesitas investigar fallos de IO.
