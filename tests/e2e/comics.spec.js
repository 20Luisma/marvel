const { test, expect } = require('@playwright/test');

test.describe('Cómics', () => {
  test('La página de cómics muestra el formulario o panel de generación', async ({ page }) => {
    await page.goto('/comic', { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: /Crear tu cómic/i, level: 2 })).toBeVisible();
    await expect(page.getByRole('heading', { name: /Héroes disponibles/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /Generar cómic/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /Comparar héroes/i })).toBeVisible();
  });
});
