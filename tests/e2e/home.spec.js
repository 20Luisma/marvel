const { test, expect } = require('@playwright/test');

test.describe('Home', () => {
  test('La home carga correctamente', async ({ page }) => {
    const response = await page.goto('/', { waitUntil: 'domcontentloaded' });
    expect(response?.ok()).toBeTruthy();

    await expect(page).toHaveTitle(/Clean Marvel Album/i);
    await expect(page.getByText(/UN GRAN PODER CONLLEVA UNA GRAN RESPONSABILIDAD/i)).toBeVisible();
    await expect(page.getByAltText(/Intro Marvel Clean Album/i)).toBeVisible();
  });

  test('El menú principal muestra enlaces clave', async ({ page }) => {
    await page.goto('/readme', { waitUntil: 'domcontentloaded' });

    // Heading actual del README
    await expect(
      page.getByRole('heading', { name: /Clean Architecture with Marvel/i })
    ).toBeVisible();

    // Enlaces clave del menú por texto accesible
    const links = [
      /Inicio/i,
      /Crear Cómic/i,
      /Marvel Movies/i,
      /Secret Room/i,
    ];

    for (const name of links) {
      await expect(page.getByRole('link', { name })).toBeVisible();
    }
  });
});
