import {
  debounce,
  formatDateTime,
  normalize,
  showMessage
} from '/assets/js/main.js';

// ====== Parámetros e init ======
const params = new URLSearchParams(window.location.search);
const albumId = params.get('albumId');
const albumNameParam = params.get('albumName');
if (!albumId || !albumNameParam) window.location.href = '/albums';

const albumNombre = albumNameParam || '';
document.getElementById('album-meta').textContent = `Álbum: ${albumNombre}`;

// ====== Refs UI ======
const heroForm = document.getElementById('hero-form');
const heroMessage = document.getElementById('hero-message');
const heroesGrid = document.getElementById('heroes-grid');
const heroNameInput = document.getElementById('hero-name');
const heroImageInput = document.getElementById('hero-image');
const heroContentInput = document.getElementById('hero-content');

// Filtros
const filterInput = document.getElementById('filter-q');
const orderSelect = document.getElementById('filter-order');
const heroesCounter = document.getElementById('heroes-counter');
const heroFocusBackdrop = document.getElementById('hero-focus-backdrop');
const heroesControlsContainer = document.getElementById('heroes-controls');

let isHeroFocusMode = false;
let heroFocusCurrentCard = null;
let heroFocusPreviousScroll = 0;
let heroFocusPreviousFocus = null;

// ====== Actividad (una sola visible con navegación) ======
const heroActivityEmpty = document.getElementById('hero-activity-empty');
const heroActivityView  = document.getElementById('hero-activity-view');
const clearHeroActivityButton = document.getElementById('clear-hero-activity');
const activityPrevBtn = document.getElementById('activity-prev');
const activityNextBtn = document.getElementById('activity-next');

const tagEl   = document.getElementById('hero-activity-tag');
const dateEl  = document.getElementById('hero-activity-date');
const countEl = document.getElementById('hero-activity-counter');
const titleEl = document.getElementById('hero-activity-title');

// Estado
let heroesAll = [];
let heroesFiltered = [];

// ====== Auto-resize textarea ======
attachAutoResize(heroContentInput);
function autoResizeTextarea(textarea) {
  textarea.style.height = 'auto';
  textarea.style.height = `${textarea.scrollHeight}px`;
}
function attachAutoResize(textarea) {
  if (!textarea) return;
  textarea.addEventListener('input', () => autoResizeTextarea(textarea));
  autoResizeTextarea(textarea);
}

// ====== Utils ======

const COLLAPSED_CONTENT_MAX_HEIGHT = '6rem';

// ====== Event Bus + Activity (persistente por álbum) ======
const heroesEventBus = new EventTarget();
const ACTIVITY_COLORS = {
  CREADO: 'text-emerald-400 border-emerald-500/40',
  EDITADO: 'text-sky-400 border-sky-500/40',
  ELIMINADO: 'text-rose-400 border-rose-500/40'
};

const HERO_ACTIVITY_ENDPOINT = `/activity/heroes/${encodeURIComponent(albumId)}`;
let heroActivityLog = [];
let activityIndex = 0; // 0 = más reciente

function normalizeHeroActivityEntry(entry) {
  if (!entry || typeof entry !== 'object') {
    return null;
  }

  const action = typeof entry.action === 'string' && entry.action.trim() !== ''
    ? entry.action.trim().toUpperCase()
    : 'ACTIVIDAD';
  const title = typeof entry.title === 'string' && entry.title.trim() !== ''
    ? entry.title.trim()
    : 'Acción registrada';
  const timestamp = entry.timestamp ? String(entry.timestamp) : new Date().toISOString();

  return { action, title, timestamp };
}

async function loadHeroActivityFromServer() {
  try {
    const response = await fetch(HERO_ACTIVITY_ENDPOINT);
    const payload = await response.json().catch(() => null);

    if (!response.ok || payload?.estado !== 'éxito') {
      const message = payload?.message ?? 'No se pudo cargar la actividad de héroes.';
      throw new Error(message);
    }

    const entries = Array.isArray(payload?.datos) ? payload.datos : [];
    heroActivityLog = entries
      .map(normalizeHeroActivityEntry)
      .filter((entry) => entry !== null);

    activityIndex = 0;
  } catch (error) {
    heroActivityLog = [];
    if (error?.message) {
      showMessage(heroMessage, error.message, true);
    }
  } finally {
    renderHeroActivityView();
  }
}
function extractHeroTitle(detail) {
  if (!detail) return 'Héroe';
  if (typeof detail === 'string') return detail || 'Héroe';
  if (detail.hero && typeof detail === 'object') return extractHeroTitle(detail.hero);
  return detail.nombre || detail.name || 'Héroe';
}
async function recordHeroActivity(action, detail) {
  const entryPayload = {
    action,
    title: extractHeroTitle(detail)
  };

  try {
    const response = await fetch(HERO_ACTIVITY_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(entryPayload)
    });

    const payload = await response.json().catch(() => null);

    if (!response.ok || payload?.estado !== 'éxito') {
      const message = payload?.message ?? 'No se pudo registrar la actividad de héroes.';
      throw new Error(message);
    }

    const entry = normalizeHeroActivityEntry(payload?.datos ?? null);
    if (!entry) return;

    heroActivityLog = [entry, ...heroActivityLog].slice(0, 100);
    activityIndex = 0;
    renderHeroActivityView();
  } catch (error) {
    showMessage(heroMessage, error?.message ?? 'No se pudo registrar la actividad.', true);
  }
}

function renderHeroActivityView() {
  const total = heroActivityLog.length;
  if (total === 0) {
    heroActivityEmpty.classList.remove('hidden');
    heroActivityView.classList.add('hidden');
    activityPrevBtn.disabled = true;
    activityNextBtn.disabled = true;
    return;
  }
  if (activityIndex < 0) activityIndex = 0;
  if (activityIndex > total - 1) activityIndex = total - 1;

  const entry = heroActivityLog[activityIndex];

  // Tag de acción con color
  tagEl.textContent = entry.action;
  tagEl.className = [
    'inline-flex items-center px-2 py-1 rounded-md text-[0.65rem] font-black uppercase tracking-[0.18em] border',
    ACTIVITY_COLORS[entry.action] || 'text-gray-200 border-slate-600'
  ].join(' ');

  dateEl.textContent = formatDateTime(entry.timestamp);
  countEl.textContent = `${activityIndex + 1}/${total}`;
  titleEl.textContent = entry.title;

  heroActivityEmpty.classList.add('hidden');
  heroActivityView.classList.remove('hidden');

  activityPrevBtn.disabled = (activityIndex === 0);           // no hay más recientes
  activityNextBtn.disabled = (activityIndex >= total - 1);    // no hay más antiguas
}

// Controles Anterior / Siguiente
activityPrevBtn.addEventListener('click', () => {
  // Ir hacia más reciente -> índice -1 (siempre top clamp a 0)
  activityIndex = Math.max(0, activityIndex - 1);
  renderHeroActivityView();
});
activityNextBtn.addEventListener('click', () => {
  // Ir hacia más antigua -> índice +1
  activityIndex = Math.min(heroActivityLog.length - 1, activityIndex + 1);
  renderHeroActivityView();
});

clearHeroActivityButton?.addEventListener('click', async () => {
  if (!heroActivityLog.length) return;
  try {
    const response = await fetch(HERO_ACTIVITY_ENDPOINT, { method: 'DELETE' });
    const payload = await response.json().catch(() => null);

    if (!response.ok || payload?.estado !== 'éxito') {
      const message = payload?.message ?? 'No se pudo limpiar la actividad de héroes.';
      throw new Error(message);
    }

    heroActivityLog = [];
    activityIndex = 0;
    renderHeroActivityView();
    showMessage(heroMessage, 'Registro de actividad vacío.');
  } catch (error) {
    showMessage(heroMessage, error?.message ?? 'No se pudo limpiar la actividad.', true);
  }
});

// Disparadores desde acciones
heroesEventBus.addEventListener('hero:created', (ev)=>recordHeroActivity('CREADO', ev?.detail));
heroesEventBus.addEventListener('hero:updated', (ev)=>recordHeroActivity('EDITADO', ev?.detail));
heroesEventBus.addEventListener('hero:deleted', (ev)=>recordHeroActivity('ELIMINADO', ev?.detail));

renderHeroActivityView();
loadHeroActivityFromServer();

function handleHeroFocusEscape(event) {
  if (!isHeroFocusMode) return;
  if (event.key === 'Escape') {
    event.preventDefault();
    exitHeroFocus();
  }
}

function enterHeroFocus(card) {
  if (isHeroFocusMode) {
    if (heroFocusCurrentCard === card) {
      return;
    }
    exitHeroFocus();
  }

  isHeroFocusMode = true;
  heroFocusCurrentCard = card;
  heroFocusPreviousScroll = window.scrollY || document.documentElement.scrollTop || 0;
  heroFocusPreviousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
  document.body.setAttribute('data-hero-focus', 'true');
  heroFocusBackdrop?.classList.remove('hidden');
  heroesControlsContainer?.classList.add('hidden');

  const cards = heroesGrid.querySelectorAll('.hero-card');
  cards.forEach((node) => {
    if (node === card) {
      node.classList.add('hero-focus-panel');
      node.classList.remove('cursor-pointer');
      node.classList.add('cursor-default');
      node.classList.remove('hidden');
      node.setAttribute('aria-hidden', 'false');
    } else {
      node.classList.add('hidden');
      node.setAttribute('aria-hidden', 'true');
    }
  });

  requestAnimationFrame(() => {
    const rect = card.getBoundingClientRect();
    const offset = Math.max(0, window.scrollY + rect.top - 80);
    window.scrollTo({ top: offset, behavior: 'smooth' });
  });

  window.addEventListener('keydown', handleHeroFocusEscape);
}

function exitHeroFocus() {
  if (!isHeroFocusMode) {
    return;
  }

  isHeroFocusMode = false;
  heroFocusCurrentCard = null;
  document.body.removeAttribute('data-hero-focus');
  heroFocusBackdrop?.classList.add('hidden');
  heroesControlsContainer?.classList.remove('hidden');

  const cards = heroesGrid.querySelectorAll('.hero-card');
  cards.forEach((node) => {
    node.classList.remove('hero-focus-panel', 'is-editing');
    node.classList.remove('hidden');
    node.setAttribute('aria-hidden', 'false');
    const actions = node.querySelector('[data-hero-actions="true"]');
    actions?.classList.remove('hidden');
    const editForm = node.querySelector('.hero-edit-form');
    editForm?.classList.add('hidden');
  });

  window.scrollTo({ top: heroFocusPreviousScroll, behavior: 'smooth' });
  heroFocusPreviousScroll = 0;
  if (heroFocusPreviousFocus) {
    heroFocusPreviousFocus.focus({ preventScroll: true });
    heroFocusPreviousFocus = null;
  }

  window.removeEventListener('keydown', handleHeroFocusEscape);
}

heroFocusBackdrop?.addEventListener('click', () => {
  if (!isHeroFocusMode) return;
  const activeCard = heroFocusCurrentCard;
  const cancelBtn = activeCard?.querySelector('.cancel-hero-edit-btn');
  if (cancelBtn) {
    cancelBtn.click();
  } else {
    exitHeroFocus();
  }
});

// ====== Galería (cards) ======
function buildHeroCard(hero) {
  const card = document.createElement('article');
  card.className = 'hero-card bg-slate-800 rounded-2xl overflow-hidden border border-slate-700 hover:border-[var(--marvel)] transition-transform duration-300 flex flex-col shadow-lg cursor-pointer';
  card.dataset.heroId = hero.id ?? hero.heroId ?? hero.uuid ?? '';

  const image = document.createElement('img');
  image.className = 'hero-card-image';
  image.src = hero.imagen;
  image.alt = hero.nombre;

  const body = document.createElement('div');
  body.className = 'hero-card-body p-4 flex-1 flex flex-col space-y-3';

  const heading = document.createElement('h3');
  heading.className = 'hero-card-title text-2xl text-white';
  heading.textContent = hero.nombre;

  const infoContainer = document.createElement('div');
  infoContainer.className = 'hero-extra-info space-y-3';

  const content = document.createElement('div');
  content.className = 'hero-content text-sm text-gray-300 whitespace-pre-wrap break-words';
  content.textContent = hero.contenido && hero.contenido.trim() !== '' ? hero.contenido : 'Sin descripción.';

  infoContainer.append(content);

  const actionsRow = document.createElement('div');
  actionsRow.className = 'hero-card-actions flex flex-col space-y-3 p-4 pt-0 mt-auto w-full';
  actionsRow.dataset.heroActions = 'true';

  const editButton = document.createElement('button');
  editButton.type = 'button';
  editButton.className = 'edit-hero-btn btn btn-secondary w-full h-11 text-sm font-semibold';
  editButton.textContent = 'Editar';

  const deleteButton = document.createElement('button');
  deleteButton.type = 'button';
  deleteButton.className = 'delete-hero-btn btn btn-danger w-full h-11 text-sm font-semibold';
  deleteButton.dataset.heroId = hero.id ?? hero.heroId ?? hero.uuid ?? '';
  deleteButton.dataset.heroName = hero.nombre;
  deleteButton.textContent = 'Eliminar';

  actionsRow.append(editButton, deleteButton);

  const editForm = document.createElement('form');
  editForm.className = 'hero-edit-form hidden mt-3 flex flex-col gap-5 border border-slate-700 bg-slate-900/70 rounded-xl p-4';
  editForm.dataset.heroEditPanel = 'true';

  const formFields = document.createElement('div');
  formFields.className = 'space-y-4 flex-1 flex flex-col';

  const editNameInput = document.createElement('input');
  editNameInput.className = 'w-full px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 focus:border-[var(--marvel)] focus:ring-0 focus:outline-none text-white';
  editNameInput.setAttribute('data-edit-field', 'nombre');
  editNameInput.required = true;

  const editImageInput = document.createElement('input');
  editImageInput.className = 'w-full px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 focus:border-[var(--marvel)] focus:ring-0 focus:outline-none text-white';
  editImageInput.type = 'url';
  editImageInput.setAttribute('data-edit-field', 'imagen');
  editImageInput.required = true;

  const editContentTextarea = document.createElement('textarea');
  editContentTextarea.className = 'w-full px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 focus:border-[var(--marvel)] focus:ring-0 focus:outline-none text-white resize-none min-h-[200px]';
  editContentTextarea.rows = 6;
  editContentTextarea.setAttribute('data-edit-field', 'contenido');

  formFields.append(editNameInput, editImageInput, editContentTextarea);

  const buttonsRow = document.createElement('div');
  buttonsRow.className = 'flex flex-col space-y-3';

  const saveEditButton = document.createElement('button');
  saveEditButton.type = 'submit';
  saveEditButton.className = 'btn btn-primary w-full h-11 text-sm font-semibold';
  saveEditButton.textContent = 'Guardar cambios';

  const cancelEditButton = document.createElement('button');
  cancelEditButton.type = 'button';
  cancelEditButton.className = 'cancel-hero-edit-btn btn btn-secondary w-full h-11 text-sm font-semibold';
  cancelEditButton.textContent = 'Cancelar';

  buttonsRow.append(saveEditButton, cancelEditButton);

  const editMessage = document.createElement('p');
  editMessage.className = 'hero-edit-message text-sm hidden';

  editForm.append(formFields, buttonsRow, editMessage);

  body.append(heading, infoContainer, actionsRow, editForm);
  card.appendChild(image);
  card.appendChild(body);

  return card;
}

function setupHeroCardCollapsible() {
  // Comportamiento eliminadon: contenido siempre visible
}

function setupHeroEditForm(card, hero) {
  let currentHero = { ...hero };
  const editButton = card.querySelector('.edit-hero-btn');
  const editForm = card.querySelector('.hero-edit-form');
  const actionsRow = card.querySelector('[data-hero-actions="true"]');
  if (!editButton || !editForm) return;

  const nameInput = editForm.querySelector('[data-edit-field="nombre"]');
  const imageInput = editForm.querySelector('[data-edit-field="imagen"]');
  const contentTextarea = editForm.querySelector('[data-edit-field="contenido"]');
  const cancelButton = editForm.querySelector('.cancel-hero-edit-btn');
  const message = editForm.querySelector('.hero-edit-message');
  const extraInfoWrapper = card.querySelector('.hero-extra-info');

  attachAutoResize(contentTextarea);

  const populateForm = (heroData) => {
    if (!heroData) return;
    nameInput.value = heroData.nombre;
    imageInput.value = heroData.imagen;
    contentTextarea.value = heroData.contenido ?? '';
    autoResizeTextarea(contentTextarea);
    message.classList.add('hidden');
  };

  populateForm(currentHero);

  const enterEdit = () => {
    populateForm(currentHero);
    message.classList.add('hidden');
    actionsRow?.classList.add('hidden');
    extraInfoWrapper?.classList.add('hidden');
    editForm.classList.remove('hidden');
    card.classList.add('is-editing');
    enterHeroFocus(card);
    nameInput.focus({ preventScroll: true });
  };

  const exitEdit = () => {
    editForm.classList.add('hidden');
    actionsRow?.classList.remove('hidden');
    extraInfoWrapper?.classList.remove('hidden');
    card.classList.remove('is-editing');
    exitHeroFocus();
  };

  editButton.addEventListener('click', () => {
    if (card.classList.contains('is-editing')) {
      exitEdit();
      populateForm(currentHero);
    } else {
      enterEdit();
    }
  });

  cancelButton.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    populateForm(currentHero);
    message.classList.add('hidden');
    exitEdit();
  });

  editForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    event.stopPropagation();

    const nuevoNombre = nameInput.value.trim();
    const nuevaImagen = imageInput.value.trim();
    const nuevoContenido = contentTextarea.value.trim();

    if (!nuevoNombre || !nuevaImagen) {
      showMessage(message, 'Nombre e imagen son obligatorios.', true);
      return;
    }

    const payload = { nombre: nuevoNombre, imagen: nuevaImagen, contenido: nuevoContenido };

    try {
      const response = await fetch(`/heroes/${encodeURIComponent(hero.id ?? hero.heroId ?? hero.uuid ?? '')}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const result = await response.json();
      if (!response.ok || result.estado !== 'éxito') {
        throw new Error(result.message || 'No se pudo actualizar el héroe.');
      }

      currentHero = { ...currentHero, ...payload };
      showMessage(heroMessage, `Héroe "${nuevoNombre}" actualizado.`);

      heroesEventBus.dispatchEvent(new CustomEvent('hero:updated', {
        detail: { hero: currentHero, nombre: currentHero.nombre }
      }));

      exitEdit();
      await fetchHeroes(albumId);
    } catch (error) {
      showMessage(message, error.message, true);
    }
  });
}

// ====== Filtros / Orden ======
function applyFilters(){
  if (isHeroFocusMode) {
    exitHeroFocus();
  }
  const q = normalize(filterInput?.value || '');

  heroesFiltered = heroesAll.filter(h=>{
    if(!q) return true;
    return normalize(h.nombre).includes(q) || normalize(h.contenido).includes(q);
  });

  const ord = orderSelect?.value || 'az';
  heroesFiltered.sort((a,b)=>{
    if(ord==='recent'){
      const ka = a.createdAt ?? a.fecha ?? a.heroId ?? a.id ?? 0;
      const kb = b.createdAt ?? b.fecha ?? b.heroId ?? b.id ?? 0;
      return (kb > ka) ? 1 : (kb < ka) ? -1 : 0;
    }
    const an = (a.nombre || '').toLowerCase();
    const bn = (b.nombre || '').toLowerCase();
    if(ord==='za') return an < bn ? 1 : an > bn ? -1 : 0;
    return an > bn ? 1 : an < bn ? -1 : 0;
  });

  renderHeroes();
}

function renderHeroes(){
  const total = heroesAll.length;
  const visib = heroesFiltered.length;
  if (heroesCounter){
    heroesCounter.textContent = total === visib ? `${visib} héroes` : `${visib}/${total} héroes`;
  }

  if (heroesFiltered.length === 0){
    heroesGrid.innerHTML = '<p class="text-gray-400 col-span-full text-center py-10 italic">No hay héroes que coincidan con la búsqueda.</p>';
    return;
  }

  heroesGrid.innerHTML = '';
  heroesFiltered.forEach(hero=>{
    const card = buildHeroCard(hero);
    heroesGrid.appendChild(card);
    setupHeroEditForm(card, hero);
  });
}

// ====== Borrado robusto ======
async function deleteHeroById(heroId, albumId) {
  const endpoints = [
    `/heroes/${encodeURIComponent(heroId)}`,
    `/albums/${encodeURIComponent(albumId)}/heroes/${encodeURIComponent(heroId)}`
  ];
  let lastError;
  for (const url of endpoints) {
    try {
      const res = await fetch(url, { method: 'DELETE' });
      const json = await res.json().catch(() => ({}));
      if (res.ok && (json.estado === 'éxito' || json.ok === true)) {
        return { ok: true, message: json?.datos?.message || json?.message || 'Héroe eliminado.' };
      }
      lastError = new Error(json?.message || `Fallo en ${url}`);
    } catch (e) {
      lastError = e;
    }
  }
  throw lastError || new Error('No se pudo eliminar el héroe.');
}

// ====== Carga de héroes ======
async function fetchHeroes(albumId) {
  if (isHeroFocusMode) {
    exitHeroFocus();
  }
  try {
    const response = await fetch(`/albums/${encodeURIComponent(albumId)}/heroes`);
    if (!response.ok) throw new Error('Error al cargar héroes.');
    const payload = await response.json();
    if (payload.estado !== 'éxito') throw new Error(payload.message || 'No se pudieron cargar los héroes.');

    const heroes = Array.isArray(payload.datos) ? payload.datos : [];
    heroesAll = heroes;
    applyFilters();
  } catch (error) {
    showMessage(heroMessage, error.message, true);
  }
}

// ====== Eventos ======
heroForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const payload = {
    nombre: heroNameInput.value.trim(),
    contenido: heroContentInput.value.trim(),
    imagen: heroImageInput.value.trim()
  };
  if (!payload.nombre || !payload.imagen) {
    showMessage(heroMessage, 'Nombre e imagen son obligatorios.', true);
    return;
  }
  try {
    const response = await fetch(`/albums/${encodeURIComponent(albumId)}/heroes`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await response.json();
    if (result.estado !== 'éxito') throw new Error(result.message || 'No se pudo crear el héroe.');
    const datos = result.datos && typeof result.datos === 'object' ? result.datos : {};

    showMessage(heroMessage, `Héroe "${datos.nombre ?? payload.nombre}" creado.`);
    heroForm.reset();
    autoResizeTextarea(heroContentInput);

    heroesEventBus.dispatchEvent(new CustomEvent('hero:created', {
      detail: { hero: datos, nombre: datos?.nombre ?? payload.nombre }
    }));

    await fetchHeroes(albumId);
  } catch (error) {
    showMessage(heroMessage, error.message, true);
  }
});

// Eliminar héroe (delegado)
heroesGrid.addEventListener('click', async (event) => {
  const btn = event.target.closest('button.delete-hero-btn');
  if (!btn) return;

  let heroId = btn.dataset.heroId;
  if (!heroId) {
    const card = btn.closest('[data-hero-id]');
    heroId = card?.dataset?.heroId || '';
  }
  const heroName = btn.dataset.heroName || 'el héroe';

  if (!heroId) {
    showMessage(heroMessage, 'No encuentro el ID del héroe.', true);
    return;
  }

  if (!confirm(`¿Seguro que quieres eliminar a ${heroName}?`)) return;

  try {
    const { ok, message } = await deleteHeroById(heroId, albumId);
    if (!ok) throw new Error(message || 'No se pudo eliminar el héroe.');
    showMessage(heroMessage, message || 'Héroe eliminado.');

    heroesEventBus.dispatchEvent(new CustomEvent('hero:deleted', {
      detail: { nombre: heroName, heroId }
    }));

    await fetchHeroes(albumId);
  } catch (error) {
    showMessage(heroMessage, error.message || 'Error al eliminar.', true);
  }
});

// Filtros
filterInput?.addEventListener('input', debounce(applyFilters, 200));
orderSelect?.addEventListener('change', applyFilters);

// ====== Init ======
renderHeroActivityView(); // pintar estado inicial (por si hay log previo)
fetchHeroes(albumId);
