const { test, expect } = require('@playwright/test');

test.describe('Negative - 404', () => {
  test('Ruta inexistente devuelve 404 y no rompe la UI', async ({ page }) => {
    const response = await page.goto('/ruta-inexistente', { waitUntil: 'domcontentloaded' });
    expect(response).not.toBeNull();
    expect(response.status()).toBe(404);

    await expect(page.getByRole('heading', { name: /^404$/ })).toBeVisible();
    await expect(
      page.getByText(/La ruta solicitada no existe/i)
    ).toBeVisible();
  });
});

