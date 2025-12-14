const { test, expect } = require('@playwright/test');

test.describe('Negative - CSRF', () => {
  test('POST /login sin token devuelve 403 JSON controlado', async ({ request }) => {
    const response = await request.post('/login', {
      headers: { Accept: 'application/json' },
      form: { email: 'marvel@gmail.com', password: 'marvel2025' },
      maxRedirects: 0,
    });

    expect(response.status()).toBe(403);

    const contentType = response.headers()['content-type'] || '';
    expect(contentType).toContain('application/json');

    const body = await response.json();
    expect(body).toBeTruthy();
    expect(body.error).toBe('Invalid CSRF token');
  });
});

