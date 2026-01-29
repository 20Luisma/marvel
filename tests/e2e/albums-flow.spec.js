const { test, expect } = require('@playwright/test');

test.describe('Flujo Álbumes', () => {
  test('Crear → Persistir → Recuperar → Renderizar', async ({ page }) => {
    const uniqueName = `Album E2E ${Date.now()}`;

    await page.goto('/albums', { waitUntil: 'domcontentloaded' });

    await page.fill('#album-name', uniqueName);
    await page.click('button:has-text("Crear Álbum")');

    const createdCard = page.locator('.album-card-title', { hasText: uniqueName });
    await expect(createdCard).toBeVisible();

    await page.reload({ waitUntil: 'domcontentloaded' });

    const persistedCard = page.locator('.album-card-title', { hasText: uniqueName });
    await expect(persistedCard).toBeVisible();
  });
});
