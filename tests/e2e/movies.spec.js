const { test, expect } = require('@playwright/test');

test.describe('Películas', () => {
  test('La página de películas carga correctamente', async ({ page }) => {
    await page.goto('/movies', { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: /Marvel Movies/i })).toBeVisible();
    await expect(page.getByPlaceholder(/Buscar película/i)).toBeVisible();

    const movieCards = page.locator('#movies-grid article');
    await expect(movieCards.first()).toBeVisible();
    await expect(await movieCards.count()).toBeGreaterThan(0);
  });
});
