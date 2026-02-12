const { test, expect } = require('@playwright/test');

test.describe('Health & Smoke', () => {
  test('Landing (/) responde 200 y renderiza elementos base', async ({ page, request }) => {
    const response = await request.get('/');
    expect(response.ok()).toBeTruthy();

    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/Clean Marvel Album/i);
    await expect(page.getByRole('main')).toBeVisible();
  });

  test('Si existe `/health`, responde 200 y devuelve JSON con claves básicas', async ({ request }) => {
    const response = await request.get('/health');

    test.skip(
      response.status() === 404,
      'La app principal no expone `/health` (solo microservicios/heatmap en docs).'
    );

    expect(response.ok()).toBeTruthy();

    const contentType = response.headers()['content-type'] || '';
    expect(contentType).toContain('application/json');

    const body = await response.json();
    expect(typeof body).toBe('object');
    expect(body).not.toBeNull();

    const allowedKeys = ['status', 'services', 'timestamp', 'trace_id', 'environment', 'response_time_ms'];
    const bodyKeys = Object.keys(body);
    expect(bodyKeys.some((key) => allowedKeys.includes(key))).toBeTruthy();
  });
});

