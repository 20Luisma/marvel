const { test, expect } = require('@playwright/test');

async function postWithRetries(request, url, options, attempts = 3, delayMs = 2000) {
  let lastError;
  for (let i = 0; i < attempts; i++) {
    try {
      const response = await request.post(url, options);
      if (response.ok()) {
        return response;
      }
      lastError = new Error(`HTTP ${response.status()} ${response.statusText()}`);
    } catch (error) {
      lastError = error;
    }
    if (i + 1 < attempts) {
      await new Promise(r => setTimeout(r, delayMs));
    }
  }
  throw lastError;
}

/**
 * 🏥 SUITE DE DIAGNÓSTICO QUIRÚRGICO (PRE-DEPLOYMENT)
 * Este test es el guardián de la producción. Si falla, el deploy se detiene.
 */

test.describe('🛡️ Quality Gate: Surgical Production Check', () => {

  test.beforeEach(async ({ page }) => {
    // Aumentamos el timeout para operaciones de IA que pueden ser lentas
    test.setTimeout(120000);
  });

  test('APIs Críticas: Las rutas base deben responder 200', async ({ request }) => {
    const criticalPaths = [
      '/heroes',
      '/api/marvel-agent.php',
      '/api/ai-token-metrics.php'
    ];

    // Códigos aceptables por endpoint:
    // - 200: OK (público)
    // - 400: Falta payload requerido (marvel-agent.php sin parámetros)
    // - 403: Endpoint protegido por auth (ai-token-metrics.php requiere admin)
    // Cualquiera de estos confirma que el servicio está VIVO y responde.
    const acceptableStatus = {
      '/heroes': [200],
      '/api/marvel-agent.php': [200, 400],
      '/api/ai-token-metrics.php': [200, 403],
    };

    for (const path of criticalPaths) {
      const response = await request.get(path);
      const status = response.status();
      const allowed = acceptableStatus[path] || [200];
      
      expect(allowed, `La API en ${path} está caída! (Recibido: ${status})`).toContain(status);
    }
  });

  // 2. AGENTE IA (RAG)
  test('IA Agent: Debe ser capaz de razonar y responder (RAG Check)', async ({ page }) => {
    await page.goto('/comic');
    
    const response = await postWithRetries(page.request, '/api/marvel-agent.php', {
      form: { question: '¿Qué es Clean Marvel Album?' }
    }, 3, 2000);
    
    expect(response.ok(), `Error al llamar a marvel-agent.php: ${response.status()} ${response.statusText()}`).toBeTruthy();
    const data = await response.json();
    expect(data.answer, `El Agente IA no devolvió 'answer'. Respuesta: ${JSON.stringify(data)}`).toBeDefined();
    expect(data.answer.length).toBeGreaterThan(10);
  });

  // 3. COMPARADOR DE HÉROES
  test('Comparador: Debe analizar dos héroes y devolver una conclusión', async ({ page }) => {
    const response = await postWithRetries(page.request, '/api/marvel-agent.php', {
      form: {
        question: 'compara a Iron Man con Spider-Man',
        context: 'compare_heroes'
      }
    }, 3, 2000);

    expect(response.ok(), `Error en Comparador: ${response.status()} - ${await response.text()}`).toBeTruthy();
    const data = await response.json();
    expect(data.answer, 'No hay respuesta en comparador').toBeDefined();
    expect(data.answer.toLowerCase()).toContain('man');
  });

  // 4. GENERACIÓN DE CÓMIC CON IA
  test('Cómic: Debe generar historia y viñetas con IA', async ({ request }) => {
    const heroesResponse = await request.get('/heroes');
    expect(heroesResponse.ok(), `No se pudo obtener héroes: ${heroesResponse.status()} ${heroesResponse.statusText()}`).toBeTruthy();
    const heroesPayload = await heroesResponse.json();
    const heroes = Array.isArray(heroesPayload?.datos) ? heroesPayload.datos : [];
    expect(heroes.length, 'No hay héroes disponibles para generar cómic').toBeGreaterThan(0);

    const heroIds = heroes
      .map(hero => hero?.heroId)
      .filter(id => typeof id === 'string' && id.trim() !== '')
      .slice(0, 2);

    expect(heroIds.length, 'No se encontraron heroIds válidos para el cómic').toBeGreaterThan(0);

    const response = await postWithRetries(request, '/comics/generate', {
      data: { heroIds }
    }, 3, 2000);

    expect(response.ok(), `Error al generar cómic: ${response.status()} ${response.statusText()}`).toBeTruthy();
    const payload = await response.json();
    expect(payload?.estado, `Respuesta inválida en cómic: ${JSON.stringify(payload)}`).toBe('éxito');
    const story = payload?.datos?.story || {};
    expect(typeof story.summary).toBe('string');
    expect(story.summary.length).toBeGreaterThan(10);
    expect(Array.isArray(story.panels)).toBeTruthy();
    expect(story.panels.length).toBeGreaterThan(0);
  });

  // 5. CRUD DE ÁLBUMES (CREAR Y ELIMINAR)
  test('Ciclo CRUD: Debe poder crear un álbum y luego eliminarlo', async ({ page }) => {
    await page.goto('/');
    
    // Crear álbum
    await page.fill('#album-name', 'TEST_ALBUM_QUIRURGICO');
    await page.click('#album-form button[type="submit"]');
    
    // Esperamos a que aparezca en el grid
    const albumCard = page.locator('.album-card', { hasText: 'TEST_ALBUM_QUIRURGICO' });
    await expect(albumCard).toBeVisible({ timeout: 10000 });

    // Eliminar álbum 
    // Primero hay que interceptar el confirm de window.confirm
    page.on('dialog', dialog => dialog.accept());
    
    // El botón eliminar está dentro de las acciones de la tarjeta
    const deleteBtn = albumCard.locator('button.btn-danger');
    await deleteBtn.click();

    // Verificamos que desaparezca
    await expect(albumCard).not.toBeVisible({ timeout: 10000 });
  });

  // 6. SISTEMA DE RESET (MÁQUINA DEL TIEMPO)
  test('Demo Reset: El endpoint de restauración debe funcionar', async ({ request }) => {
    const response = await request.post('/api/reset-demo.php');
    expect(response.ok()).toBeTruthy();
    const data = await response.json();
    expect(data.ok).toBeTruthy();
  });

  // 7. VERIFICACIÓN DE RECURSOS VISUALES (MARVEL TECH)
  test('Visual: Las imágenes de MARVEL TECH deben cargar correctamente', async ({ page }) => {
    await page.goto('/marveltech');
    
    // Obtener todas las imágenes del carrusel
    const images = page.locator('.carousel-slide img');
    const count = await images.count();
    expect(count, 'No se encontraron imágenes en el carrusel de Marvel Tech').toBeGreaterThan(0);

    for (let i = 0; i < count; i++) {
      const img = images.nth(i);
      
      // 1. Verificar que el atributo src no esté vacío
      const src = await img.getAttribute('src');
      expect(src, `La imagen ${i} no tiene atributo src`).not.toBeNull();
      expect(src.length, `El src de la imagen ${i} está vacío`).toBeGreaterThan(0);

      // 2. Verificar que la imagen se ha cargado físicamente (naturalWidth > 0)
      const isLoaded = await img.evaluate((node) => node.complete && node.naturalWidth > 0);
      expect(isLoaded, `La imagen con src "${src}" está rota o no carga`).toBeTruthy();
    }
  });

});
