const { test, expect } = require('@playwright/test');

test.describe('Álbumes', () => {
  test('La página de álbumes se renderiza correctamente', async ({ page }) => {
    await page.goto('/albums', { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: /Álbumes/i })).toBeVisible();
    await expect(page.getByRole('heading', { name: /Crear Álbum/i, level: 2 })).toBeVisible();

    const albumCards = page.locator('[data-testid="album-card"], .album-card, #albums-grid article');
    const cardCount = await albumCards.count();
    if (cardCount > 0) {
      await expect(albumCards.first()).toBeVisible();
    }

    await expect(page.getByRole('button', { name: /Crear Álbum/i })).toBeVisible();
  });
});
