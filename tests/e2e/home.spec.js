const { test, expect } = require('@playwright/test');

test.describe('Home', () => {
  test('La home carga correctamente', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    await expect(page).toHaveTitle(/Clean Marvel Album/i);
    const loginHeading = page.getByText(/Inicia sesión de prueba/i);
    await expect(loginHeading).toHaveCount(1);
    const loginCard = page.locator('.login-card');
    await expect(loginCard).toHaveCount(1);
    const entrarCallToAction = loginCard.getByText(/Entrar/i);
    await expect(entrarCallToAction).toHaveCount(1);
    await expect(page.getByText(/gran poder conlleva una gran responsabilidad/i)).toHaveCount(1);
  });

  test('El menú principal muestra enlaces clave', async ({ page }) => {
    await page.goto('/readme', { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: /Clean Architecture with Marvel/i })).toBeVisible();
    await expect(page.getByRole('link', { name: /Inicio/i })).toBeVisible();
    await expect(page.getByRole('link', { name: /Crear Cómic/i })).toBeVisible();
    await expect(page.getByRole('link', { name: /Marvel Movies/i })).toBeVisible();
    await expect(page.getByRole('link', { name: /Secret Room/i })).toBeVisible();
  });
});
