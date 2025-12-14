const { test, expect } = require('@playwright/test');

function extractCsrfToken(html) {
  const match =
    html.match(/name=\"_token\" value=\"([^\"]+)\"/) ||
    html.match(/name=\"csrf_token\" value=\"([^\"]+)\"/);
  return match ? match[1] : null;
}

test.describe('Negative - Rate limit', () => {
  test('POST /login se bloquea con 429 tras exceder el límite', async ({ request }) => {
    const bootstrap = await request.get('/login', { headers: { Accept: 'text/html' } });
    expect(bootstrap.ok()).toBeTruthy();
    const html = await bootstrap.text();

    const token = extractCsrfToken(html);
    expect(token).toBeTruthy();

    const forwardedIp = `203.0.113.${Math.floor(Math.random() * 200) + 1}`;

    let lastResponse = null;
    for (let i = 0; i < 11; i++) {
      lastResponse = await request.post('/login', {
        headers: { Accept: 'application/json', 'X-Forwarded-For': forwardedIp },
        form: { _token: token, email: 'seguridadmarvel@gmail.com', password: 'seguridadmarvel2025' },
        maxRedirects: 0,
      });
    }

    expect(lastResponse).not.toBeNull();
    expect(lastResponse.status()).toBe(429);

    const body = await lastResponse.json();
    expect(body).toBeTruthy();
    expect(body.error).toBe('rate_limited');
  });
});
