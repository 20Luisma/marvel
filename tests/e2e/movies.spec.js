const { test, expect } = require('@playwright/test');

test.describe('Películas', () => {
  test('La página de películas carga correctamente', async ({ page }) => {
    await page.goto('/movies', { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: /Marvel Movies/i })).toBeVisible();
    await expect(page.getByPlaceholder(/Buscar película/i)).toBeVisible();

    const movieCards = page.locator('[data-testid="movie-card"], #movies-grid article');
    const emptyState = page.getByText(/No hay películas|Configura tu API key|No se encontraron películas/i);
    const statusMessage = page.locator('#movies-status');

    const cardCount = await movieCards.count();
    if (cardCount > 0) {
      await expect(movieCards.first()).toBeVisible();
    } else {
      const emptyCount = await emptyState.count();
      if (emptyCount > 0) {
        await expect(emptyState.first()).toBeVisible();
      } else {
        await expect(statusMessage).toBeVisible();
      }
    }
  });
});
