const { test, expect } = require('@playwright/test');

test.describe('Flujo Álbumes', () => {
  test('Crear → Persistir → Recuperar → Renderizar', async ({ page }) => {
    test.setTimeout(60000);
    const uniqueName = `Album E2E ${Date.now()}`;
    const normalizePath = (url) => {
      try {
        const parsed = new URL(url);
        return parsed.pathname.replace(/\/+$/, '') || '/';
      } catch {
        return url.replace(/\/+$/, '') || '/';
      }
    };

    page.on('request', (request) => {
      if (request.method() === 'POST' && request.url().includes('albums')) {
        console.log(`[e2e][request] ${request.method()} ${request.url()}`);
      }
    });
    page.on('response', (response) => {
      if (response.url().includes('albums')) {
        console.log(`[e2e][response] ${response.status()} ${response.url()}`);
      }
    });
    page.on('pageerror', (error) => {
      console.log(`[e2e][pageerror] ${error.message}`);
    });
    page.on('console', (message) => {
      if (message.type() === 'error') {
        console.log(`[e2e][console] ${message.text()}`);
      }
    });

    await page.goto('/albums', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#album-form')).toBeVisible({ timeout: 10000 });

    const nameInput = page.locator('#album-name');
    const submitButton = page.locator('#album-form button[type="submit"]');
    await expect(nameInput).toBeVisible();
    await expect(submitButton).toBeVisible();
    await expect(submitButton).toBeEnabled();

    await nameInput.fill(uniqueName);
    await expect(nameInput).toHaveValue(uniqueName);

    const createRequestPromise = page.waitForRequest((request) => {
      return request.method() === 'POST' && normalizePath(request.url()) === '/albums';
    }, { timeout: 15000 });

    await submitButton.click();

    const createRequest = await createRequestPromise;
    const createResponse = await createRequest.response();
    if (!createResponse) {
      throw new Error('POST /albums no response captured');
    }

    if (createResponse.status() !== 201) {
      const body = await createResponse.text();
      const messageHtml = await page.locator('#album-message').evaluate((el) => el.innerHTML).catch(() => '');
      throw new Error(`POST /albums failed with ${createResponse.status()}: ${body}\nUI: ${messageHtml}`);
    }

    await expect(page.locator('#album-message')).toContainText('Álbum', { timeout: 10000 });

    const createdCard = page.locator('.album-card-title', { hasText: uniqueName });
    await expect(createdCard).toBeVisible({ timeout: 15000 });

    await Promise.all([
      page.waitForResponse((response) => {
        return normalizePath(response.url()) === '/albums'
          && response.request().method() === 'GET'
          && response.status() >= 200
          && response.status() < 300;
      }),
      page.reload({ waitUntil: 'domcontentloaded' }),
    ]);

    const persistedCard = page.locator('.album-card-title', { hasText: uniqueName });
    await expect(persistedCard).toBeVisible({ timeout: 15000 });
  });
});
