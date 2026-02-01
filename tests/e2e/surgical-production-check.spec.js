const { test, expect } = require('@playwright/test');

/**
 * 🏥 SUITE DE DIAGNÓSTICO QUIRÚRGICO (PRE-DEPLOYMENT)
 * Este test es el guardián de la producción. Si falla, el deploy se detiene.
 */

test.describe('🛡️ Quality Gate: Surgical Production Check', () => {

  test.beforeEach(async ({ page }) => {
    // Aumentamos el timeout para operaciones de IA que pueden ser lentas
    test.setTimeout(60000);
  });

  test('APIs Críticas: Las rutas base deben responder 200', async ({ request }) => {
    const criticalPaths = [
      '/heroes',
      '/api/marvel-agent.php',
      '/api/ai-token-metrics.php'
    ];

    for (const path of criticalPaths) {
      const response = await request.get(path);
      const status = response.status();
      
      // marvel-agent.php devuelve 400 si no hay parámetros, lo cual es correcto (está vivo)
      if (path === '/api/marvel-agent.php') {
         expect([200, 400], `La API en ${path} respondió con status ${status}`).toContain(status);
      } else {
         expect(status, `La API en ${path} está caída! (Recibido: ${status})`).toBe(200);
      }
    }
  });

  // 2. AGENTE IA (RAG)
  test('IA Agent: Debe ser capaz de razonar y responder (RAG Check)', async ({ page }) => {
    await page.goto('/comic'); // El agente suele estar accesible desde aquí o tiene su propia sección
    
    // Asumimos que hay un chat o un botón para invocar al agente
    // Basado en el código, el agente se suele consultar vía API o en el comparador
    const response = await page.request.post('/api/marvel-agent.php', {
      form: { question: '¿Qué es Clean Marvel Album?' }
    });
    
    expect(response.ok(), `Error al llamar a marvel-agent.php: ${response.status()} ${response.statusText()}`).toBeTruthy();
    const data = await response.json();
    expect(data.answer, `El Agente IA no devolvió 'answer'. Respuesta: ${JSON.stringify(data)}`).toBeDefined();
    expect(data.answer.length).toBeGreaterThan(10);
  });

  // 3. COMPARADOR DE HÉROES
  test('Comparador: Debe analizar dos héroes y devolver una conclusión', async ({ page }) => {
    const response = await page.request.post('/api/marvel-agent.php', {
      form: { 
        question: 'compara a Iron Man con Spider-Man',
        context: 'compare_heroes'
      }
    });

    expect(response.ok(), `Error en Comparador: ${response.status()} - ${await response.text()}`).toBeTruthy();
    const data = await response.json();
    expect(data.answer, 'No hay respuesta en comparador').toBeDefined();
    expect(data.answer.toLowerCase()).toContain('man');
  });

  // 4. CRUD DE ÁLBUMES (CREAR Y ELIMINAR)
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

  // 5. SISTEMA DE RESET (MÁQUINA DEL TIEMPO)
  test('Demo Reset: El endpoint de restauración debe funcionar', async ({ request }) => {
    const response = await request.post('/api/reset-demo.php');
    expect(response.ok()).toBeTruthy();
    const data = await response.json();
    expect(data.ok).toBeTruthy();
  });

});
