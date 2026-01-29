const { test, expect } = require('@playwright/test');

test.describe('Flujo Álbumes', () => {
  test('Crear → Persistir → Recuperar → Renderizar', async ({ page }) => {
    const uniqueName = `Album E2E ${Date.now()}`;

    await page.goto('/albums', { waitUntil: 'domcontentloaded' });

    await page.fill('#album-name', uniqueName);
    await Promise.all([
      page.waitForResponse((response) => {
        return response.url().endsWith('/albums')
          && response.request().method() === 'POST'
          && response.status() === 201;
      }),
      page.click('button:has-text("Crear Álbum")'),
    ]);

    await expect(page.locator('#album-message')).toContainText('Álbum', { timeout: 10000 });

    const createdCard = page.locator('.album-card-title', { hasText: uniqueName });
    await expect(createdCard).toBeVisible({ timeout: 15000 });

    await Promise.all([
      page.waitForResponse((response) => {
        return response.url().endsWith('/albums')
          && response.request().method() === 'GET'
          && response.status() === 200;
      }),
      page.reload({ waitUntil: 'domcontentloaded' }),
    ]);

    const persistedCard = page.locator('.album-card-title', { hasText: uniqueName });
    await expect(persistedCard).toBeVisible({ timeout: 15000 });
  });
});
