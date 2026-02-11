const { test, expect } = require('@playwright/test');

/**
 * Tests del Monitor de APIs Real (Slide 21).
 *
 * Estos tests verifican que el monitor de ecosistema hace peticiones fetch()
 * reales a las APIs y muestra estados honestos (online/integrated/offline).
 *
 * Se usa la URL directa al HTML porque la presentación es un archivo estático
 * que no depende del router PHP de la aplicación.
 */

const PRESENTATION_URL = `file://${__dirname}/../../public/presentation/tfm-presentation.html`;

test.describe('Presentation – API Monitor Real', () => {

  /** Activa el slide 21 programáticamente y espera al diagnóstico */
  async function goToSlide21(page) {
    await page.goto(PRESENTATION_URL, { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
      const slides = Array.from(document.querySelectorAll('.slide'));
      const target = slides.findIndex(s => s.dataset.slide === '21');
      if (target >= 0) {
        slides.forEach((s, i) => s.classList.toggle('active', i === target));
      }
    });
  }

  test('El monitor de APIs ejecuta verificaciones reales via fetch()', async ({ page }) => {
    await goToSlide21(page);

    // Verificar título del monitor
    await expect(page.locator('.api-monitor-title')).toContainText('Verificación en Tiempo Real');

    // Esperar a que TODAS las verificaciones terminen (sin LEDs en checking)
    await expect(page.locator('.status-led.checking')).toHaveCount(0, { timeout: 15000 });

    // Servicios verificables: deben tener estado resuelto (online u offline)
    await expect(page.locator('#status-github')).toHaveClass(/online|offline/, { timeout: 10000 });
    await expect(page.locator('#status-deploy')).toHaveClass(/online|offline/, { timeout: 10000 });
    await expect(page.locator('#status-sonar')).toHaveClass(/online|offline/, { timeout: 10000 });

    // Servicios integrados (CORS): deben quedar en azul
    await expect(page.locator('#status-openai')).toHaveClass(/integrated/);
    await expect(page.locator('#status-sentry')).toHaveClass(/integrated/);
    await expect(page.locator('#status-rag')).toHaveClass(/integrated/);
    await expect(page.locator('#status-n8n')).toHaveClass(/integrated/);
    await expect(page.locator('#status-coderabbit')).toHaveClass(/integrated/);

    // Leyenda honesta visible
    await expect(page.getByText('Online (verificado)')).toBeVisible();
    await expect(page.getByText('Integrada (sin CORS)')).toBeVisible();
    await expect(page.getByText('No disponible')).toBeVisible();

    // Nota técnica sobre fetch() real
    await expect(page.getByText('fetch()')).toBeVisible();
  });

  test('Los LEDs verificables hacen peticiones reales (interceptar red)', async ({ page }) => {
    const fetchedUrls = [];
    page.on('request', (req) => {
      const url = req.url();
      if (url.includes('api.github.com') || url.includes('sonarcloud.io') || url.includes('creawebes.com')) {
        fetchedUrls.push(url);
      }
    });

    await goToSlide21(page);

    // Esperar a que todas las verificaciones terminen
    await expect(page.locator('.status-led.checking')).toHaveCount(0, { timeout: 15000 });

    // Verificar que se hicieron peticiones reales a las APIs
    expect(fetchedUrls.length).toBeGreaterThanOrEqual(2);
    expect(fetchedUrls.some(u => u.includes('api.github.com'))).toBeTruthy();
  });
});
