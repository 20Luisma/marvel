const { test, expect } = require('@playwright/test');

test.describe('Auth UI', () => {
  test('La página de login renderiza el formulario', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: /Secret Room/i })).toBeVisible();
    await expect(page.getByLabel(/Correo/i)).toBeVisible();
    await expect(page.getByLabel(/Contraseñ?a/i)).toBeVisible();
    await expect(page.getByRole('button', { name: /Entrar/i })).toBeVisible();
  });

  test('Enviar login vacío no redirige y marca inputs inválidos', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });

    await page.getByRole('button', { name: /Entrar/i }).click();
    await expect(page).toHaveURL(/\/login(?:\\?|$)/);
    await expect(page.locator('form[action="/login"] input:invalid')).toHaveCount(2);
  });
});

