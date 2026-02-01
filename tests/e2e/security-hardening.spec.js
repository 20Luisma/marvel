const { test, expect } = require('@playwright/test');

/**
 * 🔒 TEST DE HARDENING Y SUPERFICIE DE ATAQUE
 * Este test verifica que el servidor de producción no tenga "puertas abiertas" comunes.
 */

test.describe('🛡️ Security Sentinel: Production Hardening', () => {

  const baseURL = process.env.APP_URL || 'https://iamasterbigschool.contenido.creawebes.com';

  test('No debe exponer archivos de configuración (.env)', async ({ request }) => {
    const response = await request.get(`${baseURL}/.env`);
    // Esperamos un 403 (Forbidden) o 404 (Not Found)
    expect(response.status(), '¡ALERTA! El archivo .env es accesible públicamente').not.toBe(200);
  });

  test('No debe exponer el directorio .git', async ({ request }) => {
    const response = await request.get(`${baseURL}/.git/config`);
    expect(response.status(), '¡ALERTA! El directorio .git está expuesto').not.toBe(200);
  });

  test('No debe exponer logs de errores visibles (php.log)', async ({ request }) => {
    const response = await request.get(`${baseURL}/error_log`);
    expect(response.status(), '¡ALERTA! El log de errores de Hostinger está expuesto').not.toBe(200);
  });

  test('Escaneo de Cabeceras de Seguridad', async ({ request }) => {
    const response = await request.get(baseURL);
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
