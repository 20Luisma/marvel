const { test, expect } = require('@playwright/test');

test.describe('Flujo Álbumes', () => {
  test('Crear → Persistir → Recuperar → Renderizar', async ({ page }) => {
    test.setTimeout(60000);
    const uniqueName = `Album E2E ${Date.now()}`;

    await page.goto('/albums', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#album-form')).toBeVisible({ timeout: 10000 });

    await page.fill('#album-name', uniqueName);
    const [createResponse] = await Promise.all([
      page.waitForResponse((response) => {
        return response.url().endsWith('/albums')
          && response.request().method() === 'POST';
      }),
      page.click('button:has-text("Crear Álbum")'),
    ]);

    if (createResponse.status() !== 201) {
      const body = await createResponse.text();
      throw new Error(`POST /albums failed with ${createResponse.status()}: ${body}`);
    }

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
