import {
  debounce,
  formatDateTime,
  normalize,
  showMessage
} from '/assets/js/main.js';

const initAlbumsPage = () => {
  // Refs
  const albumForm = document.getElementById('album-form');
  const albumNameInput = document.getElementById('album-name');
  const albumCoverUrlInput = document.getElementById('album-cover-url');
  const albumCoverFileInput = document.getElementById('album-cover-file');
  const albumMessage = document.getElementById('album-message');
  const albumsGrid = document.getElementById('albums-grid');
  const refreshButton = document.getElementById('refresh-albums');

  // Filtros
  const filterInput = document.getElementById('filter-q');
  const orderSelect = document.getElementById('filter-order');
  const albumsCounter = document.getElementById('albums-counter');

  // Actividad (una sola visible + navegación)
  const activityEmpty = document.getElementById('album-activity-empty');
  const activityView  = document.getElementById('album-activity-view');
  const clearActivityButton = document.getElementById('clear-album-activity');
  const activityPrevBtn = document.getElementById('album-activity-prev');
  const activityNextBtn = document.getElementById('album-activity-next');
  const tagEl   = document.getElementById('album-activity-tag');
  const dateEl  = document.getElementById('album-activity-date');
  const countEl = document.getElementById('album-activity-counter');
  const titleEl = document.getElementById('album-activity-title');
  const testsBox = document.getElementById('tests-box');
  const testsBody = document.getElementById('tests-body');
  const testsToggle = document.getElementById('tests-toggle');
  const testRunButton = document.getElementById('run-tests-btn');
  const testRunnerMessage = document.getElementById('test-runner-message');
  const testsSummary = document.getElementById('tests-summary');
  const testSummaryGrid = document.getElementById('test-summary-grid');
  const testStatusBreakdown = document.getElementById('test-status-breakdown');
  const testsTbody = document.getElementById('tests-tbody');
  const testStatusChip = document.getElementById('test-status-chip');

  // Estado
  let albumsAll = [];
  let albumsFiltered = [];
  let activityLog = [];
  let activityIndex = 0; // 0 = más reciente

  const ALBUM_PLACEHOLDER_CLASSES = 'cover-ph text-white text-2xl font-bold px-4 text-center';
  const ACTIVITY_ENDPOINT = '/activity/albums';
  const ACTIVITY_COLORS = {
    CREADO: 'text-emerald-400 border-emerald-500/40',
    EDITADO: 'text-sky-400 border-sky-500/40',
    ELIMINADO: 'text-rose-400 border-rose-500/40'
  };
  const TESTS_PANEL_STORAGE_KEY = 'clean-marvel:albums:testsPanelCollapsed';
  const TEST_STATUS_LABELS = {
    passed: 'OK',
    failed: 'Falló',
    error: 'Error',
    skipped: 'Omitido',
    running: 'En progreso',
    info: 'Info'
  };
  const TEST_STATUS_ICONS = {
    passed: '✅',
    failed: '⚠️',
    error: '⛔',
    skipped: '⏭️',
    running: '⏳',
    info: 'ℹ️'
  };
  const TEST_STATUS_STYLES = {
    passed: {
      chip: 'bg-emerald-500/10 border-emerald-500/40 text-emerald-300',
      message: 'text-emerald-300',
      badge: 'border border-emerald-500/40 bg-emerald-500/10 text-emerald-200'
    },
    failed: {
      chip: 'bg-amber-500/10 border-amber-500/40 text-amber-200',
      message: 'text-amber-300',
      badge: 'border border-amber-500/40 bg-amber-500/10 text-amber-200'
    },
    error: {
      chip: 'bg-rose-500/10 border-rose-500/40 text-rose-200',
      message: 'text-rose-300',
      badge: 'border border-rose-500/40 bg-rose-500/10 text-rose-200'
    },
    skipped: {
      chip: 'bg-slate-500/10 border-slate-500/40 text-slate-200',
      message: 'text-slate-300',
      badge: 'border border-slate-500/40 bg-slate-500/10 text-slate-200'
    },
    running: {
      chip: 'bg-sky-500/10 border-sky-500/40 text-sky-200',
      message: 'text-sky-300',
      badge: 'border border-sky-500/40 bg-sky-500/10 text-sky-200'
    },
    info: {
      chip: 'bg-slate-500/10 border-slate-500/40 text-slate-200',
      message: 'text-slate-300',
      badge: 'border border-slate-500/40 bg-slate-500/10 text-slate-200'
    }
  };
  const albumEventBus = new EventTarget();

  // Utils
  function clearFileInput(input) { input.value = ''; }

  function formatNumber(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '0';
    return numeric.toLocaleString('es-ES');
  }

  function formatSeconds(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '0.000s';
    return `${numeric.toFixed(3)}s`;
  }

  let equalizeAlbumCardsRaf = null;

  function equalizeAlbumCardHeights() {
    if (typeof window === 'undefined') {
      return;
    }

    const cards = document.querySelectorAll('.album-card');
    if (cards.length === 0) {
      return;
    }

    cards.forEach((card) => {
      card.style.minHeight = '';
    });

    let maxHeight = 0;
    cards.forEach((card) => {
      const height = card.offsetHeight;
      if (height > maxHeight) {
        maxHeight = height;
      }
    });

    if (maxHeight === 0) {
      return;
    }

    cards.forEach((card) => {
      card.style.minHeight = `${maxHeight}px`;
    });
  }

  function scheduleAlbumCardEqualization() {
    if (typeof window === 'undefined') {
      return;
    }

    if (equalizeAlbumCardsRaf !== null) {
      cancelAnimationFrame(equalizeAlbumCardsRaf);
    }

    equalizeAlbumCardsRaf = requestAnimationFrame(() => {
      equalizeAlbumCardsRaf = null;
      equalizeAlbumCardHeights();
    });
  }

  let coverUploadIdCounter = 0;

  function resetTestResults() {
    testsSummary?.classList.add('hidden');
    testSummaryGrid?.replaceChildren();
    testStatusBreakdown?.replaceChildren();
    testsTbody?.replaceChildren();
    if (testStatusBreakdown) {
      testStatusBreakdown.classList.add('hidden');
    }
    if (testStatusChip) {
      testStatusChip.classList.add('hidden');
    }
  }

  function renderTestMessage(text, status = 'info') {
    if (!testRunnerMessage) return;
    if (!text) {
      testRunnerMessage.classList.add('hidden');
      return;
    }
    const style = TEST_STATUS_STYLES[status] ?? TEST_STATUS_STYLES.info;
    testRunnerMessage.textContent = text;
    testRunnerMessage.className = `text-sm font-semibold leading-tight ${style.message}`;
    testRunnerMessage.classList.remove('hidden');
  }

  function setTestStatusChip(status, totalTests) {
    if (!testStatusChip) return;
    const style = TEST_STATUS_STYLES[status] ?? TEST_STATUS_STYLES.info;
    const baseClasses = 'inline-flex items-center gap-2 text-[0.65rem] font-black uppercase tracking-[0.18em] px-3 py-1 rounded-full border';
    testStatusChip.className = `${baseClasses} ${style.chip}`;
    const label = TEST_STATUS_LABELS[status] ?? status.toUpperCase();
    const suffix = Number.isFinite(totalTests) ? ` · ${totalTests} tests` : '';
    const icon = TEST_STATUS_ICONS[status] ?? '';
    testStatusChip.textContent = icon ? `${icon} ${label}${suffix}` : `${label}${suffix}`;
    testStatusChip.classList.remove('hidden');
  }

  function renderTestBreakdown(statusCounts) {
    if (!testStatusBreakdown) return;
    testStatusBreakdown.replaceChildren();
    const order = ['passed', 'failed', 'error', 'skipped'];
    let hasAny = false;
    order.forEach((status) => {
      const count = Number(statusCounts?.[status] ?? 0);
      if (!count) return;
      hasAny = true;
      const badge = document.createElement('span');
      badge.className = `inline-flex items-center gap-1 px-3 py-1 rounded-full font-semibold ${TEST_STATUS_STYLES[status]?.badge ?? TEST_STATUS_STYLES.info.badge}`;
      badge.textContent = `${TEST_STATUS_ICONS[status] ?? ''} ${TEST_STATUS_LABELS[status] ?? status}: ${formatNumber(count)}`.trim();
      testStatusBreakdown.appendChild(badge);
    });
    if (hasAny) {
      testStatusBreakdown.classList.remove('hidden');
    } else {
      testStatusBreakdown.classList.add('hidden');
    }
  }

  function renderTestSummarySection(result) {
    if (!testsSummary || !testSummaryGrid) return;
    testsSummary.classList.remove('hidden');
    testSummaryGrid.replaceChildren();

    const summary = result?.summary ?? {};
    const rows = [
      { label: 'Tests', value: formatNumber(summary.tests ?? 0), accent: 'text-slate-100' },
      { label: 'Asserts', value: formatNumber(summary.assertions ?? 0), accent: 'text-slate-200' },
      { label: 'Fallos', value: formatNumber(summary.failures ?? 0), accent: (summary.failures ?? 0) ? 'text-amber-300' : 'text-slate-400' },
      { label: 'Errores', value: formatNumber(summary.errors ?? 0), accent: (summary.errors ?? 0) ? 'text-rose-300' : 'text-slate-400' },
      { label: 'Omitidos', value: formatNumber(summary.skipped ?? 0), accent: (summary.skipped ?? 0) ? 'text-sky-300' : 'text-slate-400' },
      { label: 'Tiempo reportado', value: formatSeconds(summary.time ?? 0), accent: 'text-slate-200' },
      { label: 'Duración total', value: formatSeconds(result?.duration ?? summary.time ?? 0), accent: 'text-slate-200' },
    ];

    rows.forEach((row) => {
      const card = document.createElement('div');
      card.className = 'rounded-xl bg-slate-800/50 border border-slate-700/60 px-4 py-3 flex flex-col gap-1';
      const labelEl = document.createElement('span');
      labelEl.className = 'text-[0.7rem] uppercase tracking-[0.22em] text-slate-400';
      labelEl.textContent = row.label;
      const valueEl = document.createElement('span');
      valueEl.className = `text-xl font-semibold ${row.accent}`;
      valueEl.textContent = row.value;
      card.append(labelEl, valueEl);
      testSummaryGrid.appendChild(card);
    });

    renderTestBreakdown(result?.statusCounts ?? {});
    renderTestResults(result?.tests ?? []);
  }

  function renderTestResults(tests) {
    if (!testsTbody) return;
    testsTbody.replaceChildren();
    if (!Array.isArray(tests) || tests.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'text-xs italic text-slate-400';
      empty.textContent = 'No se registraron ejecuciones.';
      testsTbody.appendChild(empty);
      return;
    }

    const fragment = document.createDocumentFragment();
    tests.slice(0, 200).forEach((test) => {
      const status = test.status ?? 'info';
      const style = TEST_STATUS_STYLES[status] ?? TEST_STATUS_STYLES.info;
      const item = document.createElement('article');
      item.className = 'rounded-xl border border-slate-700/60 bg-slate-800/40 sm:px-4 px-3 sm:py-4 py-3 flex flex-col justify-between gap-3 first:mt-0 last:mb-0 scroll-mt-4 sm:min-h-[88px] min-h-[72px]';

      const header = document.createElement('div');
      header.className = 'flex items-start gap-3';
      const title = document.createElement('p');
      title.className = 'test-item-title text-sm sm:text-base font-semibold text-slate-100 leading-tight';
      title.textContent = test.name ?? 'Test';
      const badgeClass = `inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[0.65rem] font-bold uppercase tracking-[0.18em] ${style.badge}`;
      const badgeText = `${TEST_STATUS_ICONS[status] ?? ''} ${TEST_STATUS_LABELS[status] ?? status}`.trim();
      header.append(title);

      const classLine = document.createElement('p');
      classLine.className = 'test-item-path text-xs text-slate-400 font-mono';
      classLine.style.overflowWrap = 'anywhere';
      classLine.textContent = test.class ?? '';

      const meta = document.createElement('div');
      meta.className = 'text-xs text-slate-500 flex items-center justify-between gap-3 pr-1 sm:pr-2';
      const time = Number(test.time ?? 0);
      const timeEl = document.createElement('span');
      timeEl.textContent = `⏱️ ${formatSeconds(time)}`;
      const statusEl = document.createElement('span');
      statusEl.className = badgeClass;
      statusEl.textContent = badgeText;
      statusEl.setAttribute('aria-hidden', 'true');
      meta.append(timeEl, statusEl);

      item.append(header);
      if (classLine.textContent) item.append(classLine);
      item.append(meta);

      const message = (test.message ?? '').toString().trim();
      if (message) {
        const messageEl = document.createElement('p');
        messageEl.className = 'text-xs text-slate-300 border-l border-slate-700/70 pl-3 whitespace-pre-wrap break-words';
        messageEl.textContent = message;
        item.append(messageEl);
      }

      fragment.appendChild(item);
    });

    testsTbody.appendChild(fragment);
  }

  let testsPanelCollapsed = false;

  function applyTestsPanelState(collapsed, { animate = true } = {}) {
    if (!testsBody || !testsToggle) return;

    testsPanelCollapsed = Boolean(collapsed);
    const targetCollapsed = testsPanelCollapsed;
    testsToggle.setAttribute('aria-expanded', String(!targetCollapsed));
    const toggleLabel = testsToggle.querySelector('.tests-toggle-label');
    const toggleIcon = testsToggle.querySelector('.tests-toggle-icon');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (prefersReducedMotion) {
      testsToggle.classList.remove('transition-colors', 'duration-150');
      toggleIcon?.classList.remove('transition-transform', 'duration-150');
    } else {
      testsToggle.classList.add('transition-colors', 'duration-150');
      toggleIcon?.classList.add('transition-transform', 'duration-150');
    }

    const collapsedButtonClasses = ['bg-slate-800/80', 'border-slate-500/40', 'text-slate-200', 'hover:bg-slate-700/60', 'hover:border-slate-400/40'];
    const expandedButtonClasses = ['bg-emerald-400/15', 'border-emerald-300/40', 'text-emerald-100', 'hover:bg-emerald-300/20', 'hover:border-emerald-200/40'];

    if (targetCollapsed) {
      testsToggle.classList.remove(...expandedButtonClasses);
      testsToggle.classList.add(...collapsedButtonClasses);
    } else {
      testsToggle.classList.remove(...collapsedButtonClasses);
      testsToggle.classList.add(...expandedButtonClasses);
    }

    if (toggleLabel) {
      toggleLabel.textContent = targetCollapsed ? 'Mostrar tests' : 'Ocultar tests';
    }
    if (toggleIcon) {
      if (targetCollapsed) {
        toggleIcon.classList.add('rotate-90');
        toggleIcon.classList.remove('rotate-0');
        toggleIcon.classList.remove('opacity-90');
        toggleIcon.classList.add('opacity-70');
      } else {
        toggleIcon.classList.remove('rotate-90');
        toggleIcon.classList.add('rotate-0');
        toggleIcon.classList.remove('opacity-70');
        toggleIcon.classList.add('opacity-90');
      }
    }

    if (!animate) {
      testsBody.style.transition = '';
      testsBody.style.overflow = '';
      if (targetCollapsed) {
        testsBody.style.display = 'none';
        testsBody.style.height = '';
        testsBody.style.opacity = '0';
      } else {
        testsBody.style.display = 'block';
        testsBody.style.height = '';
        testsBody.style.opacity = '1';
      }
      return;
    }

    const duration = 320;
    testsBody.style.transition = `height ${duration}ms ease, opacity ${duration}ms ease`;

    if (targetCollapsed) {
      const currentHeight = testsBody.getBoundingClientRect().height || testsBody.scrollHeight;
      testsBody.style.display = 'block';
      testsBody.style.overflow = 'hidden';
      testsBody.style.height = `${currentHeight}px`;
      testsBody.style.opacity = '1';
      requestAnimationFrame(() => {
        testsBody.style.height = '0px';
        testsBody.style.opacity = '0';
      });
      const onCollapseEnd = (event) => {
        if (event.target !== testsBody || event.propertyName !== 'height') {
          return;
        }
        testsBody.removeEventListener('transitionend', onCollapseEnd);
        testsBody.style.display = 'none';
        testsBody.style.height = '';
        testsBody.style.opacity = '0';
        testsBody.style.overflow = '';
        testsBody.style.transition = '';
      };
      testsBody.addEventListener('transitionend', onCollapseEnd);
    } else {
      testsBody.style.display = 'block';
      const targetHeight = testsBody.scrollHeight;
      testsBody.style.overflow = 'hidden';
      testsBody.style.height = '0px';
      testsBody.style.opacity = '0';
      requestAnimationFrame(() => {
        testsBody.style.height = `${targetHeight}px`;
        testsBody.style.opacity = '1';
      });
      const onExpandEnd = (event) => {
        if (event.target !== testsBody || event.propertyName !== 'height') {
          return;
        }
        testsBody.removeEventListener('transitionend', onExpandEnd);
        testsBody.style.height = '';
        testsBody.style.opacity = '1';
        testsBody.style.overflow = '';
        testsBody.style.transition = '';
      };
      testsBody.addEventListener('transitionend', onExpandEnd);
    }

    scheduleAlbumCardEqualization();
    if (animate) {
      setTimeout(scheduleAlbumCardEqualization, duration + 20);
    }
  }

  function toggleTestsPanel(forceState, { animate = true } = {}) {
    const desiredState = typeof forceState === 'boolean' ? forceState : !testsPanelCollapsed;
    try {
      window.localStorage?.setItem(TESTS_PANEL_STORAGE_KEY, String(desiredState));
    } catch (_) {
      // ignore storage issues
    }
    applyTestsPanelState(desiredState, { animate });
  }

  let storedTestsPanelState = null;
  try {
    storedTestsPanelState = window.localStorage?.getItem(TESTS_PANEL_STORAGE_KEY);
  } catch (_) {
    storedTestsPanelState = null;
  }
  applyTestsPanelState(storedTestsPanelState === 'true', { animate: false });

  async function runPhpUnitSuite() {
    if (!testRunButton) return;
    const originalLabel = testRunButton.textContent;
    testRunButton.disabled = true;
    testRunButton.textContent = '⏳ Ejecutando...';
    renderTestMessage('Ejecutando suite de tests, esto puede tardar unos segundos…', 'running');
    resetTestResults();
    if (testsPanelCollapsed) {
      toggleTestsPanel(false, { animate: true });
    }
    setTestStatusChip('running');

    try {
      const response = await fetch('/dev/tests/run', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
      });

      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload) {
        const message = payload?.message ?? 'No se pudo ejecutar la suite de tests.';
        renderTestMessage(message, 'error');
        resetTestResults();
        return;
      }

      if (payload.estado !== 'éxito') {
        renderTestMessage(payload.message ?? 'La suite devolvió un estado inesperado.', 'error');
        resetTestResults();
        return;
      }

      const result = payload.datos ?? {};
      const status = result.status ?? 'info';
      renderTestMessage(result.message ?? '', status);
      setTestStatusChip(status, Number(result.summary?.tests ?? 0));
      renderTestSummarySection(result);
    } catch (error) {
      renderTestMessage(error.message ?? 'Error desconocido al ejecutar los tests.', 'error');
      resetTestResults();
    } finally {
      testRunButton.disabled = false;
      testRunButton.textContent = originalLabel;
    }
  }

  function getAlbumId(raw){
    if (!raw || typeof raw !== 'object') return null;
    return raw.albumId ?? raw.album_id ?? raw.id ?? raw.uuid ?? raw.identifier ?? null;
  }
  function withAlbumId(raw){
    if (!raw || typeof raw !== 'object') return raw;
    const id = getAlbumId(raw);
    if (id == null || raw.albumId === id) return raw;
    return { ...raw, albumId: id };
  }

  // ====== Activity: persistencia en servidor y render de 1 sola entrada ======
  function normalizeActivityEntry(entry) {
    if (!entry || typeof entry !== 'object') {
      return null;
    }

    const action = typeof entry.action === 'string' && entry.action.trim() !== ''
      ? entry.action.trim().toUpperCase()
      : 'ACTIVIDAD';
    const title = typeof entry.title === 'string' && entry.title.trim() !== ''
      ? entry.title.trim()
      : 'Actividad registrada';
    const timestamp = entry.timestamp ? String(entry.timestamp) : new Date().toISOString();

    return { action, title, timestamp };
  }

  async function loadActivityFromServer() {
    try {
      const response = await fetch(ACTIVITY_ENDPOINT);
      const payload = await response.json().catch(() => null);

      if (!response.ok || payload?.estado !== 'éxito') {
        const message = payload?.message ?? 'No se pudo cargar la actividad.';
        throw new Error(message);
      }

      const entries = Array.isArray(payload?.datos) ? payload.datos : [];
      activityLog = entries
        .map(normalizeActivityEntry)
        .filter((entry) => entry !== null);

      activityIndex = 0;
    } catch (error) {
      activityLog = [];
      if (error?.message) {
        showMessage(albumMessage, error.message, true);
      }
    } finally {
      renderActivityView();
    }
  }

  function extractAlbumTitle(detail) {
    if (!detail) return 'Álbum sin título';
    if (typeof detail === 'string') return detail || 'Álbum sin título';
    if (detail.album && typeof detail === 'object') {
      return extractAlbumTitle(detail.album);
    }
    return detail.nombre || detail.name || detail.title || detail.albumName || 'Álbum sin título';
  }

  async function recordActivity(action, detail) {
    const entryPayload = {
      action,
      title: extractAlbumTitle(detail)
    };

    try {
      const response = await fetch(ACTIVITY_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(entryPayload)
      });

      const payload = await response.json().catch(() => null);

      if (!response.ok || payload?.estado !== 'éxito') {
        const message = payload?.message ?? 'No se pudo registrar la actividad.';
        throw new Error(message);
      }

      const entry = normalizeActivityEntry(payload?.datos ?? null);
      if (!entry) {
        return;
      }

      activityLog = [entry, ...activityLog].slice(0, 100);
      activityIndex = 0; // siempre mostrar la más reciente
      renderActivityView();
    } catch (error) {
      showMessage(albumMessage, error?.message ?? 'No se pudo registrar la actividad.', true);
    }
  }

  function renderActivityView() {
    const total = activityLog.length;
    if (total === 0) {
      activityEmpty.classList.remove('hidden');
      activityView.classList.add('hidden');
      activityPrevBtn.disabled = true;
      activityNextBtn.disabled = true;
      return;
    }
    if (activityIndex < 0) activityIndex = 0;
    if (activityIndex > total - 1) activityIndex = total - 1;

    const entry = activityLog[activityIndex];

    // tag + color
    tagEl.textContent = entry.action;
    tagEl.className = [
      'inline-flex items-center px-2 py-1 rounded-md text-[0.65rem] font-black uppercase tracking-[0.18em] border',
      ACTIVITY_COLORS[entry.action] || 'text-gray-200 border-slate-600'
    ].join(' ');

    dateEl.textContent = formatDateTime(entry.timestamp);
    titleEl.textContent = entry.title;
    countEl.textContent = `${activityIndex + 1}/${total}`;

    activityEmpty.classList.add('hidden');
    activityView.classList.remove('hidden');

    activityPrevBtn.disabled = (activityIndex === 0);
    activityNextBtn.disabled = (activityIndex >= total - 1);
  }

  // Controles Anterior / Siguiente
  activityPrevBtn.addEventListener('click', () => {
    activityIndex = Math.max(0, activityIndex - 1);
    renderActivityView();
  });
  activityNextBtn.addEventListener('click', () => {
    activityIndex = Math.min(activityLog.length - 1, activityIndex + 1);
    renderActivityView();
  });

  // Carga inicial de actividad
  loadActivityFromServer();

  // ====== Subir portada (archivo)
  async function uploadAlbumCover(albumId, file) {
    const formData = new FormData();
    formData.append('file', file);
    try {
      const response = await fetch(`/albums/${albumId}/cover`, { method: 'POST', body: formData });
      const payload = await response.json();
      if (!response.ok || payload.estado !== 'éxito') {
        return { ok: false, message: payload.message || 'No se pudo subir la portada.' };
      }
      return { ok: true, datos: payload.datos };
    } catch (error) {
      return { ok: false, message: error.message };
    }
  }

  // ====== Carga de álbumes
  async function fetchAlbums() {
    if (isFocusEditMode) {
      exitGlobalFocusMode();
    }
    try {
      const response = await fetch('/albums');
      if (!response.ok) throw new Error('Error en la red al cargar álbumes.');
      const payload = await response.json();
      if (payload.estado !== 'éxito') throw new Error(payload.message || 'No se pudieron cargar los álbumes.');
      const albums = Array.isArray(payload.datos) ? payload.datos.map(withAlbumId) : [];
      albumsAll = albums;
      applyFilters();
    } catch (error) {
      albumsAll = [];
      applyFilters();
      showMessage(albumMessage, error.message, true);
    }
  }

  // ====== Filtro + orden + render
  function applyFilters(){
    if (isFocusEditMode) {
      exitGlobalFocusMode();
    }
    const q = normalize(filterInput?.value || '');

    albumsFiltered = albumsAll.filter(a => {
      if (!q) return true;
      return normalize(a.nombre).includes(q);
    });

    const ord = orderSelect?.value || 'recent';
    albumsFiltered.sort((a,b)=>{
      if (ord === 'recent') {
        const ka = a.updatedAt ?? a.createdAt ?? a.albumId ?? 0;
        const kb = b.updatedAt ?? b.createdAt ?? b.albumId ?? 0;
        return (kb > ka) ? 1 : (kb < ka) ? -1 : 0;
      }
      const an = (a.nombre||'').toLowerCase();
      const bn = (b.nombre||'').toLowerCase();
      if (ord === 'za') return an < bn ? 1 : an > bn ? -1 : 0;
      return an > bn ? 1 : an < bn ? -1 : 0;
    });

    renderAlbums(albumsFiltered);
    const total = albumsAll.length;
    const visib = albumsFiltered.length;
    if (albumsCounter){
      albumsCounter.textContent = total === visib ? `${visib} álbumes` : `${visib}/${total} álbumes`;
    }
  }

  function renderAlbums(albums) {
    if (!albums.length) {
      albumsGrid.innerHTML = '<p class="text-gray-400 col-span-full text-center py-10 italic">No hay álbumes. ¡Crea el primero!</p>';
      scheduleAlbumCardEqualization();
      return;
    }
    albumsGrid.innerHTML = '';
    albums.forEach((album) => {
      albumsGrid.appendChild(buildAlbumCard(album));
    });
    scheduleAlbumCardEqualization();
  }

  // ====== Tarjeta de álbum (sin botón "Ver Héroes"; la tarjeta navega)
  function buildAlbumCard(album) {
    const normalizedAlbum = withAlbumId(album);
    const albumId = getAlbumId(normalizedAlbum);

    function goToAlbum() {
      if (!albumId) {
        showMessage(albumMessage, 'No se encontró el identificador del álbum.', true);
        return;
      }
      const params = new URLSearchParams({ albumId, albumName: normalizedAlbum.nombre });
      window.location.href = `/heroes?${params.toString()}`;
    }

    const card = document.createElement('article');
    card.className = 'album-card bg-slate-800 rounded-2xl overflow-hidden border border-slate-700 hover:border-[var(--marvel)] transition-transform duration-300 flex flex-col transform hover:-translate-y-1 shadow-lg cursor-pointer';
    card.setAttribute('role', 'button');
    card.setAttribute('tabindex', '0');
    card.setAttribute('aria-label', `Abrir álbum ${normalizedAlbum.nombre}`);

    // Navegación: click / Enter / Space
    card.addEventListener('click', (e) => {
      if (card.classList.contains('is-editing')) return;
      if (e.target.closest('button')) return; // no navegar si se clickea un botón
      goToAlbum();
    });
    card.addEventListener('keydown', (e) => {
      if (card.classList.contains('is-editing')) return;
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        goToAlbum();
      }
    });

    // Portada
    const coverWrapper = document.createElement('div');
    if (normalizedAlbum.coverImage) {
      const coverImg = document.createElement('img');
      coverImg.src = normalizedAlbum.coverImage;
      coverImg.alt = `Portada del álbum ${normalizedAlbum.nombre}`;
      coverImg.className = 'album-cover';
      coverImg.addEventListener('load', () => scheduleAlbumCardEqualization());
      coverImg.addEventListener('error', () => scheduleAlbumCardEqualization());
      coverWrapper.appendChild(coverImg);
    } else {
      const fallback = document.createElement('div');
      fallback.className = `${ALBUM_PLACEHOLDER_CLASSES} album-cover-placeholder`;
      fallback.textContent = normalizedAlbum.nombre;
      coverWrapper.appendChild(fallback);
    }
    card.appendChild(coverWrapper);

    const cardBody = document.createElement('div');
    cardBody.className = 'flex-1 flex flex-col';
    card.appendChild(cardBody);

    // Contenido
    const content = document.createElement('div');
    content.className = 'p-4 space-y-2 flex flex-col';
    const title = document.createElement('h3');
    title.className = 'album-card-title text-2xl text-white';
    title.textContent = normalizedAlbum.nombre;

    const meta = document.createElement('p');
    meta.className = 'album-card-meta text-xs text-gray-400';
    const createdAt = normalizedAlbum.createdAt ? new Date(normalizedAlbum.createdAt).toLocaleDateString() : '—';
    const updatedAt = normalizedAlbum.updatedAt ? new Date(normalizedAlbum.updatedAt).toLocaleDateString() : createdAt;
    meta.textContent = `Creado: ${createdAt} · Actualizado: ${updatedAt}`;

    content.append(title, meta);
    cardBody.appendChild(content);

    // Acciones (solo Editar / Eliminar)
    const actions = document.createElement('div');
    actions.className = 'flex flex-col space-y-3 p-4 pt-2 w-full';
    actions.dataset.albumActions = 'true';

    const editButton = document.createElement('button');
    editButton.type = 'button';
    editButton.className = 'btn btn-secondary w-full h-11 text-sm font-semibold';
    editButton.textContent = 'Editar';
    editButton.addEventListener('click', (e) => e.stopPropagation());

    const deleteButton = document.createElement('button');
    deleteButton.type = 'button';
    deleteButton.className = 'btn btn-danger w-full h-11 text-sm font-semibold';
    deleteButton.textContent = 'Eliminar';
    deleteButton.dataset.albumId = albumId ?? '';
    deleteButton.dataset.albumName = normalizedAlbum.nombre;
    deleteButton.addEventListener('click', (e) => e.stopPropagation());

    actions.append(editButton, deleteButton);
    cardBody.appendChild(actions);

    let editSection;
    let previousFocusElement = null;
    let focusRestoreScroll = 0;

    function handleFocusEscape(event) {
      if (!isFocusEditMode) {
        return;
      }
      if (event.key === 'Escape') {
        event.preventDefault();
        exitEditMode();
        exitEditFocusMode();
      }
    }

    function enterEditMode() {
      card.classList.add('is-editing');
      actions.classList.add('hidden');
      if (editSection) {
        editSection.section.classList.remove('hidden');
      }
      scheduleAlbumCardEqualization();
    }

    function exitEditMode() {
      if (editSection) {
        editSection.section.classList.add('hidden');
      }
      actions.classList.remove('hidden');
      card.classList.remove('is-editing');
      scheduleAlbumCardEqualization();
    }

    editSection = buildEditSection(normalizedAlbum, () => {
      exitEditMode();
      exitEditFocusMode();
    });
    editSection.section.addEventListener('click', (e) => e.stopPropagation());
    cardBody.appendChild(editSection.section);

    function enterEditFocusMode() {
      if (isFocusEditMode) {
        if (focusEditCurrentCard === card) {
          return;
        }
        exitGlobalFocusMode();
      }
      isFocusEditMode = true;
      focusEditCurrentCard = card;
      previousFocusElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      focusRestoreScroll = window.scrollY || document.documentElement.scrollTop || 0;
      document.body.setAttribute('data-edit-focus', 'true');
      focusBackdrop?.classList.remove('hidden');
      testsBox?.classList.add('hidden');

      const cards = albumsGrid.querySelectorAll('.album-card');
      cards.forEach((node) => {
        if (node === card) {
          node.classList.add('focus-edit-panel');
          node.classList.remove('hidden');
          node.setAttribute('aria-hidden', 'false');
          node.classList.remove('cursor-pointer');
          node.classList.add('cursor-default');
        } else {
          node.classList.add('hidden');
          node.setAttribute('aria-hidden', 'true');
        }
      });

      requestAnimationFrame(() => {
        const rect = card.getBoundingClientRect();
        const offset = window.scrollY + rect.top - 80;
        window.scrollTo({ top: offset, behavior: 'smooth' });
        scheduleAlbumCardEqualization();
      });

      window.addEventListener('keydown', handleFocusEscape);
    }

    function exitEditFocusMode() {
      if (!isFocusEditMode) {
        return;
      }
      isFocusEditMode = false;
      focusEditCurrentCard = null;
      document.body.setAttribute('data-edit-focus', 'false');
      focusBackdrop?.classList.add('hidden');
      testsBox?.classList.remove('hidden');

      const cards = albumsGrid.querySelectorAll('.album-card');
      cards.forEach((node) => {
        node.classList.remove('focus-edit-panel');
        node.classList.remove('hidden');
        node.setAttribute('aria-hidden', 'false');
        node.classList.remove('cursor-default');
        node.classList.add('cursor-pointer');
      });

      window.scrollTo({ top: focusRestoreScroll, behavior: 'smooth' });
      scheduleAlbumCardEqualization();

      if (previousFocusElement) {
        previousFocusElement.focus({ preventScroll: true });
        previousFocusElement = null;
      }

      window.removeEventListener('keydown', handleFocusEscape);
    }

    // Listeners del editor
    editButton.addEventListener('click', () => {
      const hidden = editSection.section.classList.contains('hidden');
      if (hidden) {
        editSection.populate(album);
        enterEditFocusMode();
        enterEditMode();
        return;
      }
      exitEditMode();
      exitEditFocusMode();
    });

    // Eliminar
    deleteButton.addEventListener('click', async () => {
      const albumName = deleteButton.dataset.albumName || normalizedAlbum.nombre;
      if (!confirm(`¿Seguro que quieres eliminar el álbum "${albumName}" y todos sus héroes?`)) return;
      const albumIdForDelete = deleteButton.dataset.albumId || getAlbumId(normalizedAlbum);
      if (!albumIdForDelete) {
        showMessage(albumMessage, 'No se pudo determinar el ID del álbum.', true);
        return;
      }
      try {
        const response = await fetch(`/albums/${albumIdForDelete}`, { method: 'DELETE' });
        const payload = await response.json();
        if (!response.ok || payload.estado !== 'éxito') {
          throw new Error(payload.message || payload.datos?.message || 'No se pudo eliminar el álbum.');
        }
        const successMessage = payload.datos?.message || 'Álbum eliminado.';
        showMessage(albumMessage, successMessage);
        albumEventBus.dispatchEvent(new CustomEvent('album:deleted', {
          detail: { nombre: albumName, albumId: albumIdForDelete }
        }));
        fetchAlbums();
      } catch (error) {
        showMessage(albumMessage, error.message, true);
      }
    });

    return card;
  }

  // ====== Editor de álbum
  function buildEditSection(initialAlbum, onExitEditMode) {
    let currentAlbum = withAlbumId(initialAlbum);

    const section = document.createElement('div');
    section.className = 'hidden border border-slate-700 bg-slate-900/70 rounded-xl p-4 mt-4 flex flex-col flex-1';
    section.dataset.albumEditPanel = 'true';

    const form = document.createElement('form');
    form.className = 'flex flex-col gap-5 min-h-full';

    const contentGroup = document.createElement('div');
    contentGroup.className = 'space-y-4';

    const nameLabel = document.createElement('label');
    nameLabel.className = 'space-y-2 text-sm font-semibold text-gray-300';
    const nameSpan = document.createElement('span');
    nameSpan.textContent = 'Nombre del álbum';
    const nameInput = document.createElement('input');
    nameInput.className = 'w-full px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 focus:border-[var(--marvel)] focus:ring-0 focus:outline-none text-white';
    nameInput.required = true;
    nameLabel.append(nameSpan, nameInput);

    const coverUrlLabel = document.createElement('label');
    coverUrlLabel.className = 'space-y-2 text-sm font-semibold text-gray-300';
    const coverUrlSpan = document.createElement('span');
    coverUrlSpan.textContent = 'Portada (URL)';
    const coverUrlInput = document.createElement('input');
    coverUrlInput.className = 'w-full px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 focus:border-[var(--marvel)] focus:ring-0 focus:outline-none text-white';
    coverUrlInput.type = 'url';
    coverUrlLabel.append(coverUrlSpan, coverUrlInput);

    const message = document.createElement('p');
    message.className = 'text-sm hidden';

    contentGroup.append(nameLabel, coverUrlLabel, message);

    coverUploadIdCounter += 1;
    const uploadInputId = `album-cover-upload-${coverUploadIdCounter}`;
    const coverFileInput = document.createElement('input');
    coverFileInput.type = 'file';
    coverFileInput.accept = 'image/png,image/jpeg,image/webp';
    coverFileInput.id = uploadInputId;
    coverFileInput.className = 'hidden';

    const buttonsRow = document.createElement('div');
    buttonsRow.className = 'flex flex-col space-y-3 mt-auto';
    const coverButtonWrapper = document.createElement('div');
    coverButtonWrapper.className = 'flex flex-col gap-1 w-full';
    const coverButtonLabel = document.createElement('span');
    coverButtonLabel.className = 'text-xs font-semibold text-gray-300';
    coverButtonLabel.textContent = 'Subir portada';
    const coverFileButton = document.createElement('label');
    coverFileButton.setAttribute('for', uploadInputId);
    coverFileButton.className = 'btn btn-secondary w-full h-11 text-sm font-semibold';
    coverFileButton.textContent = 'Seleccionar archivo';
    coverFileButton.setAttribute('role', 'button');
    coverFileButton.tabIndex = 0;
    coverFileButton.addEventListener('click', (event) => event.stopPropagation());
    coverFileButton.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        event.stopPropagation();
        coverFileInput.click();
      }
    });
    coverFileInput.addEventListener('click', (event) => event.stopPropagation());
    coverButtonWrapper.append(coverButtonLabel, coverFileButton);
    const saveButton = document.createElement('button');
    saveButton.type = 'submit';
    saveButton.className = 'btn btn-primary w-full h-11 text-sm font-semibold';
    saveButton.textContent = 'Guardar cambios';
    saveButton.addEventListener('click', (event) => event.stopPropagation());

    const cancelButton = document.createElement('button');
    cancelButton.type = 'button';
    cancelButton.className = 'btn btn-secondary w-full h-11 text-sm font-semibold';
    cancelButton.textContent = 'Cancelar';

    cancelButton.addEventListener('click', (event) => {
      event.stopPropagation();
      coverUrlInput.value = currentAlbum.coverImage ?? '';
      clearFileInput(coverFileInput);
      message.classList.add('hidden');
      if (typeof onExitEditMode === 'function') {
        onExitEditMode();
      }
    });

    buttonsRow.append(coverButtonWrapper, saveButton, cancelButton);

    form.append(contentGroup, coverFileInput, buttonsRow);
    section.appendChild(form);

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const nuevoNombre = nameInput.value.trim();
      if (!nuevoNombre) {
        showMessage(message, 'El nombre no puede estar vacío.', true);
        return;
      }

      const rawCoverUrl = coverUrlInput.value;
      const trimmedCoverUrl = rawCoverUrl.trim();
      const currentCover = currentAlbum.coverImage ?? '';
      const coverChanged = trimmedCoverUrl !== currentCover;
      const payload = { nombre: nuevoNombre };
      if (coverChanged || rawCoverUrl === '') {
        payload.coverImage = rawCoverUrl;
      }

      const file = coverFileInput.files[0] ?? null;

      try {
        const response = await fetch(`/albums/${currentAlbum.albumId}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!response.ok || result.estado !== 'éxito') {
          throw new Error(result.message || 'No se pudo actualizar el álbum.');
        }

        const updatedAlbum = withAlbumId({
          albumId: currentAlbum.albumId,
          nombre: result.datos.nombre ?? nuevoNombre,
          coverImage: result.datos.coverImage ?? (payload.coverImage !== undefined ? (trimmedCoverUrl || null) : (currentAlbum.coverImage ?? null)),
          createdAt: result.datos.createdAt ?? currentAlbum.createdAt,
          updatedAt: result.datos.updatedAt ?? currentAlbum.updatedAt,
        });
        currentAlbum = updatedAlbum;

        if (file) {
          const uploadOutcome = await uploadAlbumCover(currentAlbum.albumId, file);
          if (!uploadOutcome.ok) throw new Error(uploadOutcome.message || 'No se pudo subir la portada.');
          currentAlbum.coverImage = uploadOutcome.datos?.coverImage ?? currentAlbum.coverImage;
        }

        albumEventBus.dispatchEvent(new CustomEvent('album:updated', {
          detail: { album: currentAlbum, nombre: currentAlbum.nombre }
        }));

        showMessage(albumMessage, 'Álbum actualizado correctamente.');
        coverUrlInput.value = currentAlbum.coverImage ?? '';
        clearFileInput(coverFileInput);
        message.classList.add('hidden');
        if (typeof onExitEditMode === 'function') {
          onExitEditMode();
        }
        fetchAlbums();
      } catch (error) {
        showMessage(message, error.message, true);
      }
    });

    const populate = (album) => {
      currentAlbum = withAlbumId(album);
      nameInput.value = currentAlbum.nombre;
      coverUrlInput.value = currentAlbum.coverImage ?? '';
      clearFileInput(coverFileInput);
      message.classList.add('hidden');
    };

    populate(initialAlbum);
    return { section, populate };
  }

  // ====== Crear álbum
  albumForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const nombre = albumNameInput.value.trim();
    if (!nombre) {
      showMessage(albumMessage, 'El nombre del álbum no puede estar vacío.', true);
      return;
    }

    const file = albumCoverFileInput.files[0] ?? null;
    const coverUrl = albumCoverUrlInput.value.trim();

    const payload = { nombre };
    if (!file && coverUrl) payload.coverImage = coverUrl;

    let albumCreated = false;

    try {
      const response = await fetch('/albums', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const result = await response.json();
      if (!response.ok || result.estado !== 'éxito') {
        throw new Error(result.message || 'No se pudo crear el álbum.');
      }

      let responseData = result?.datos && typeof result.datos === 'object' ? { ...result.datos } : {};
      responseData = withAlbumId(responseData);
      albumCreated = true;
      const albumId = getAlbumId(responseData);
      if (!albumId) {
        throw new Error('No se recibió el identificador del álbum creado.');
      }

      if (file) {
        const uploadOutcome = await uploadAlbumCover(albumId, file);
        if (!uploadOutcome.ok) throw new Error(uploadOutcome.message || 'No se pudo subir la portada.');
        responseData.coverImage = uploadOutcome.datos?.coverImage ?? responseData.coverImage;
      }

      const createdName = responseData.nombre ?  ` "${responseData.nombre}"` : '';
      showMessage(albumMessage, `Álbum${createdName} creado.`);
      albumForm.reset();
      clearFileInput(albumCoverFileInput);

      albumEventBus.dispatchEvent(new CustomEvent('album:created', {
        detail: { album: responseData, nombre: responseData.nombre ?? nombre }
      }));

      fetchAlbums();
    } catch (error) {
      showMessage(albumMessage, error.message, true);
      if (albumCreated) fetchAlbums();
    }
  });

  // ====== Hooks actividad
  albumEventBus.addEventListener('album:created', (event) => recordActivity('CREADO', event?.detail));
  albumEventBus.addEventListener('album:updated', (event) => recordActivity('EDITADO', event?.detail));
  albumEventBus.addEventListener('album:deleted', (event) => recordActivity('ELIMINADO', event?.detail));

  clearActivityButton?.addEventListener('click', async () => {
    if (!activityLog.length) return;
    try {
      const response = await fetch(ACTIVITY_ENDPOINT, { method: 'DELETE' });
      const payload = await response.json().catch(() => null);

      if (!response.ok || payload?.estado !== 'éxito') {
        const message = payload?.message ?? 'No se pudo limpiar la actividad.';
        throw new Error(message);
      }

      activityLog = [];
      activityIndex = 0;
      renderActivityView();
      showMessage(albumMessage, 'Registro de actividad vacío.');
    } catch (error) {
      showMessage(albumMessage, error?.message ?? 'No se pudo limpiar la actividad.', true);
    }
  });

  // ====== Filtros/acciones
  filterInput?.addEventListener('input', debounce(applyFilters, 200));
  orderSelect?.addEventListener('change', applyFilters);
  refreshButton?.addEventListener('click', fetchAlbums);
  testRunButton?.addEventListener('click', runPhpUnitSuite);
  testsToggle?.addEventListener('click', () => toggleTestsPanel());
  window.addEventListener('resize', debounce(scheduleAlbumCardEqualization, 150));

  let isFocusEditMode = false;
  let focusEditCurrentCard = null;
  const focusBackdrop = document.getElementById('focus-edit-backdrop');

  function exitGlobalFocusMode() {
    if (!isFocusEditMode) {
      return;
    }
    const activeCard = focusEditCurrentCard;
    if (activeCard) {
      const backButton = activeCard.querySelector('.focus-edit-header button');
      if (backButton) {
        backButton.click();
        return;
      }
    }
    document.body.setAttribute('data-edit-focus', 'false');
    focusBackdrop?.classList.add('hidden');
    testsBox?.classList.remove('hidden');
    isFocusEditMode = false;
    focusEditCurrentCard = null;
    const cards = albumsGrid.querySelectorAll('.album-card');
    cards.forEach((node) => {
      node.classList.remove('focus-edit-panel');
      node.classList.remove('hidden');
      node.setAttribute('aria-hidden', 'false');
      node.classList.remove('is-editing');
      node.classList.remove('cursor-default');
      node.classList.add('cursor-pointer');
      const actionsNode = node.querySelector('[data-album-actions="true"]');
      actionsNode?.classList.remove('hidden');
      const editPanel = node.querySelector('[data-album-edit-panel="true"]');
      editPanel?.classList.add('hidden');
      node.querySelector('.focus-edit-header')?.remove();
    });
    scheduleAlbumCardEqualization();
  }

  // Init
  fetchAlbums();
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAlbumsPage);
} else {
  initAlbumsPage();
}
