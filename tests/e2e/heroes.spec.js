const { test, expect } = require('@playwright/test');

const albumId = '51f11956-6c06-4a49-8d2e-c8f5ea9b7a9d';
const albumName = 'Avengers';

test.describe('Héroes', () => {
  test('La página de héroes lista contenido', async ({ page }) => {
    await page.goto(`/heroes?albumId=${albumId}&albumName=${encodeURIComponent(albumName)}`);

    await expect(page.getByRole('heading', { name: /Galería de Héroes/i })).toBeVisible();
    await expect(page.getByText(new RegExp(`Álbum:\\s*${albumName}`, 'i'))).toBeVisible();

    const heroCards = page.locator('.hero-card');
    await expect(heroCards.first()).toBeVisible();
  });
});
