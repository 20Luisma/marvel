const { test, expect } = require('@playwright/test');

/**
 * 🔒 TEST DE HARDENING Y SUPERFICIE DE ATAQUE
 * Este test verifica que el servidor de producción no tenga "puertas abiertas" comunes.
 * 
 * Nota: Se incluye un helper con reintentos porque Hostinger puede
 * bloquear o throttlear peticiones desde IPs de CI (GitHub Actions runners).
 */

const baseURL = process.env.APP_URL || 'https://iamasterbigschool.contenido.creawebes.com';

/** Helper: petición con reintentos ante fallos de red */
async function resilientGet(request, url, maxRetries = 3) {
  let lastError;
  for (let i = 0; i < maxRetries; i++) {
    try {
      const response = await request.get(url, { timeout: 15000 });
      return response;
    } catch (err) {
      lastError = err;
      // Esperar antes de reintentar (backoff exponencial: 2s, 4s, 8s)
      await new Promise(r => setTimeout(r, 2000 * Math.pow(2, i)));
    }
  }
  throw lastError;
}

test.describe('🛡️ Security Sentinel: Production Hardening', () => {

  test('No debe exponer archivos de configuración (.env)', async ({ request }) => {
    const response = await resilientGet(request, `${baseURL}/.env`);
    // Esperamos un 403 (Forbidden) o 404 (Not Found)
    expect(response.status(), '¡ALERTA! El archivo .env es accesible públicamente').not.toBe(200);
  });

  test('No debe exponer el directorio .git', async ({ request }) => {
    const response = await resilientGet(request, `${baseURL}/.git/config`);
    expect(response.status(), '¡ALERTA! El directorio .git está expuesto').not.toBe(200);
  });

  test('No debe exponer logs de errores visibles (php.log)', async ({ request }) => {
    const response = await resilientGet(request, `${baseURL}/error_log`);
    expect(response.status(), '¡ALERTA! El log de errores de Hostinger está expuesto').not.toBe(200);
  });

  test('Escaneo de Cabeceras de Seguridad', async ({ request }) => {
    const response = await resilientGet(request, baseURL);
    const headers = response.headers();
    
    // Verificamos cabeceras esenciales de protección
    const securityHeaders = [
      'x-frame-options',
      'x-content-type-options',
      'referrer-policy'
    ];

    securityHeaders.forEach(header => {
      expect(headers[header], `Falta la cabecera de seguridad: ${header}`).toBeDefined();
    });
  });

});
