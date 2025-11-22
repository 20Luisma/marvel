const { test, expect } = require('@playwright/test');

test.describe('Home', () => {
  test('La home carga correctamente', async ({ page }) => {
    await page.goto('/');

    await expect(page).toHaveTitle(/Clean Marvel Album/i);
    await expect(page.getByRole('heading', { name: /inicia sesión de prueba/i })).toBeVisible();
    await expect(page.getByText(/gran poder conlleva una gran responsabilidad/i)).toBeVisible();
  });

  test('El menú principal muestra enlaces clave', async ({ page }) => {
    await page.goto('/readme');

    await expect(page.getByRole('link', { name: /Inicio/i })).toBeVisible();
    await expect(page.getByRole('link', { name: /Crear Cómic/i })).toBeVisible();
    await expect(page.getByRole('link', { name: /Marvel Movies/i })).toBeVisible();
    await expect(page.getByText(/héroes/i)).toBeVisible();
  });
});
