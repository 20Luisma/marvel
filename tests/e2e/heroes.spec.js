const { test, expect } = require('@playwright/test');

const albumId = '51f11956-6c06-4a49-8d2e-c8f5ea9b7a9d';
const albumName = 'Avengers';

test.describe('Héroes', () => {
  test('La página de héroes lista contenido', async ({ page }) => {
    await page.goto(`/heroes?albumId=${albumId}&albumName=${encodeURIComponent(albumName)}`, {
      waitUntil: 'domcontentloaded',
    });

    await expect(page.getByRole('heading', { name: /Galería de Héroes/i })).toBeVisible();
    await expect(page.getByRole('heading', { name: /Añadir Héroe/i, level: 2 })).toBeVisible();

    const heroCards = page.locator('[data-testid="hero-card"], .hero-card, #heroes-grid article');
    const emptyState = page.getByText(/No hay héroes|Sin héroes/i);

    const cardCount = await heroCards.count();
    if (cardCount > 0) {
      await expect(heroCards.first()).toBeVisible();
    } else {
      await expect(emptyState).toBeVisible();
    }

    await expect(page.getByRole('button', { name: /Añadir Héroe/i })).toBeVisible();
  });
});
