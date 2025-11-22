const { test, expect } = require('@playwright/test');

test.describe('Álbumes', () => {
  test('La página de álbumes se renderiza correctamente', async ({ page }) => {
    await page.goto('/albums');

    await expect(page.getByRole('heading', { name: /Mis Álbumes/i })).toBeVisible();

    const albumCards = page.locator('.album-card');
    await expect(albumCards.first()).toBeVisible();
    await expect(page.getByText(/Avengers/i)).toBeVisible();
  });
});
