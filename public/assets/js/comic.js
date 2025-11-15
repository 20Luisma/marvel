import { formatDateTime, showMessage } from './main.js';

const getWindowObject = () => (typeof globalThis !== 'undefined' ? globalThis.window ?? undefined : undefined);

const runtimeWindow = getWindowObject();

const heroGrid = document.getElementById('comic-heroes-grid');
const heroSearchInput = document.getElementById('comic-hero-search');
const heroCountLabel = document.getElementById('comic-hero-count');
const heroEmptyState = document.getElementById('comic-heroes-empty');

const selectedHeroesList = document.getElementById('selected-heroes-list');
const selectedHeroesEmpty = document.getElementById('selected-heroes-empty');
const selectedHeroesCount = document.getElementById('selected-heroes-count');
const selectedHeroesInput = document.getElementById('selected-heroes-input');

const comicForm = document.getElementById('comic-form');
const comicCancelButton = document.getElementById('comic-cancel');
const comicGenerateButton = document.getElementById('comic-generate');
const comicMessage = document.getElementById('comic-message');
const ragCompareButton = document.getElementById('comic-compare-rag');
const ragResultBox = document.getElementById('rag-result-box');
const heroWarning = document.getElementById('hero-warning');
const ragResultSection = document.getElementById('rag-result-section');
const ragHeroesPreview = document.getElementById('rag-result-heroes');
const ragResultText = document.getElementById('rag-result-text');
const ragAudioButton = document.getElementById('btn-audio-rag');
const ragAudioPlayer = document.getElementById('audio-player-rag');
const closeRagResultButton = document.getElementById('close-rag-result');

const heroSelectionSection = document.getElementById('hero-selection-section');
const comicSlideshowSection = document.getElementById('comic-slideshow-section');
const comicStorySection = document.getElementById('comic-story-section');
const generatedComicTitle = document.getElementById('generated-comic-title');
const slideshowContainer = document.getElementById('slideshow-container');
const slideshowPrev = document.getElementById('slideshow-prev');
const slideshowNext = document.getElementById('slideshow-next');
const closeComicResultButton = document.getElementById('close-comic-result');

const comicOutputStorySummary = document.getElementById('comic-output-story-summary');
const comicAudioButton = document.getElementById('btn-audio-comic');
const comicAudioPlayer = document.getElementById('audio-player-comic');
const comicOutputPanels = document.getElementById('comic-output-panels');
const comicOutputPanelsEmpty = document.getElementById('comic-output-panels-empty');

const activityEmpty = document.getElementById('comic-activity-empty');
const activityView = document.getElementById('comic-activity-view');
const activityTag = document.getElementById('comic-activity-tag');
const activityDate = document.getElementById('comic-activity-date');
const activityCounter = document.getElementById('comic-activity-counter');
const activityTitle = document.getElementById('comic-activity-title');
const activityPrevButton = document.getElementById('comic-activity-prev');
const activityNextButton = document.getElementById('comic-activity-next');
const activityClearButton = document.getElementById('comic-activity-clear');

const heroState = {
  all: [],
  filtered: [],
  selected: new Map()
};

const ACTIVITY_LABELS = {
  SELECCION: 'Selección',
  DESELECCION: 'Quitar',
  COMIC: 'Cómic',
  CANCELADO: 'Cancelado'
};

const ACTIVITY_ENDPOINT = '/activity/comic';
const SERVICE_CONFIG_ENDPOINT = '/config/services';
const ELEVENLABS_TTS_ENDPOINT = '/api/tts-elevenlabs.php';
const MAX_TTS_CHARACTERS = 4800;

const LOCAL_PANEL_LABELS = Object.freeze({
  app: '8080',
  openai: '8081',
  rag: '8082'
});


const FALLBACK_SERVICE_CONFIG = Object.freeze({
  environment: { mode: 'local', host: 'localhost:8080' },
  services: {
    app: { host: 'localhost:8080', baseUrl: 'http://localhost:8080' },
    openai: { host: 'localhost:8081', baseUrl: 'http://localhost:8081', chatUrl: 'http://localhost:8081/v1/chat' },
    rag: { host: 'localhost:8082', baseUrl: 'http://localhost:8082', heroesUrl: 'http://localhost:8082/rag/heroes' }
  },
  availableEnvironments: {
    local: {
      app: { host: 'localhost:8080', base_url: 'http://localhost:8080' },
      openai: { host: 'localhost:8081', base_url: 'http://localhost:8081', chat_url: 'http://localhost:8081/v1/chat' },
      rag: { host: 'localhost:8082', base_url: 'http://localhost:8082', heroes_url: 'http://localhost:8082/rag/heroes' }
    },
    hosting: {
      app: { host: 'iamasterbigschool.contenido.creawebes.com', base_url: 'https://iamasterbigschool.contenido.creawebes.com' },
      openai: { host: 'openai-service.contenido.creawebes.com', base_url: 'https://openai-service.contenido.creawebes.com', chat_url: 'https://openai-service.contenido.creawebes.com/v1/chat' },
      rag: { host: 'rag-service.contenido.creawebes.com', base_url: 'https://rag-service.contenido.creawebes.com', heroes_url: 'https://rag-service.contenido.creawebes.com/rag/heroes' }
    }
  }
});

const initialServiceConfig = createFallbackConfig();
let serviceConfig = initialServiceConfig;
let serviceLabels = computeUiLabels(initialServiceConfig);
let serviceConfigPromise = null;

if (runtimeWindow) {
  runtimeWindow.__CLEAN_MARVEL_SERVICES__ = {
    config: serviceConfig,
    labels: serviceLabels
  };
}

const activityState = {
  entries: [],
  index: -1
};

let slideshowInterval = null;
let latestComicNarrationText = '';
let latestRagNarrationText = '';

const ACTIVITY_STYLES = {
  SELECCION: 'text-emerald-400 border-emerald-500/40',
  DESELECCION: 'text-slate-300 border-slate-500/40',
  COMIC: 'text-sky-300 border-sky-500/40',
  CANCELADO: 'text-rose-300 border-rose-500/40'
};

function sanitizeTtsText(rawText) {
  if (typeof rawText !== 'string') {
    return '';
  }
  return rawText
    .replaceAll(/\r?\n+/g, '. ')
    .replaceAll(/\s+/g, ' ')
    .trim();
}

function syncTtsButtonState(button, hasTextAvailable) {
  if (!button) return;
  const ready = Boolean(hasTextAvailable);
  button.dataset.ttsReady = ready ? 'true' : 'false';
  button.disabled = !ready;
  button.classList.toggle('opacity-60', !ready);
}

function resetAudioPlayer(audioElement) {
  if (!audioElement) return;
  if (audioElement.dataset?.objectUrl && typeof URL !== 'undefined' && typeof URL.revokeObjectURL === 'function') {
    URL.revokeObjectURL(audioElement.dataset.objectUrl);
    delete audioElement.dataset.objectUrl;
  }
  try {
    audioElement.pause();
  } catch (_) {
    // ignore
  }
  audioElement.removeAttribute('src');
  if (typeof audioElement.load === 'function') {
    audioElement.load();
  }
  audioElement.style.display = 'none';
}

async function requestTtsPlayback({ getText, button, audioElement, emptyMessage }) {
  if (!button || !audioElement) return;
  const textSource = typeof getText === 'function' ? getText() : getText;
  const normalizedText = sanitizeTtsText(textSource).slice(0, MAX_TTS_CHARACTERS);
  if (normalizedText === '') {
    showMessage(comicMessage, emptyMessage ?? 'No hay texto disponible para convertir a audio.', true);
    syncTtsButtonState(button, false);
    resetAudioPlayer(audioElement);
    return;
  }

  if (!button.dataset.originalLabel) {
    button.dataset.originalLabel = button.innerHTML;
  }

  button.disabled = true;
  button.innerHTML = 'Generando audio…';
  button.classList.add('opacity-70');

  resetAudioPlayer(audioElement);

  try {
    const response = await fetch(ELEVENLABS_TTS_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text: normalizedText }),
      cache: 'no-store'
    });

    const contentType = response.headers.get('content-type') || '';
    if (!response.ok) {
      const payload = contentType.includes('application/json')
        ? await response.json().catch(() => null)
        : null;
      const message = payload?.message || 'No se pudo generar el audio solicitando ElevenLabs.';
      throw new Error(message);
    }

    if (!contentType.toLowerCase().startsWith('audio/')) {
      const payload = await response.json().catch(() => null);
      const fallbackMessage = payload?.message || 'El servicio de audio no devolvió un stream válido.';
      throw new Error(fallbackMessage);
    }

    const blob = await response.blob();
    if (typeof URL === 'undefined' || typeof URL.createObjectURL !== 'function') {
      throw new Error('Tu navegador no soporta reproducir audio generado dinámicamente.');
    }
    const objectUrl = URL.createObjectURL(blob);
    audioElement.dataset.objectUrl = objectUrl;
    audioElement.src = objectUrl;
    audioElement.style.display = 'block';
    audioElement.load();
    await audioElement.play().catch(() => {
      // el usuario pudo bloquear autoplay; mostramos controles
    });
  } catch (error) {
    const message = error instanceof Error ? error.message : 'No se pudo generar el audio.';
    showMessage(comicMessage, message, true);
    resetAudioPlayer(audioElement);
  } finally {
    const originalLabel = button.dataset.originalLabel;
    button.innerHTML = originalLabel || '🔊 Escuchar audio';
    button.classList.remove('opacity-70');
    const hasText = sanitizeTtsText(typeof getText === 'function' ? getText() : '').length > 0;
    syncTtsButtonState(button, hasText);
  }
}

syncTtsButtonState(comicAudioButton, false);
syncTtsButtonState(ragAudioButton, false);

function createFallbackConfig() {
  return {
    environment: { ...FALLBACK_SERVICE_CONFIG.environment },
    services: {
      app: { ...FALLBACK_SERVICE_CONFIG.services.app },
      openai: { ...FALLBACK_SERVICE_CONFIG.services.openai },
      rag: { ...FALLBACK_SERVICE_CONFIG.services.rag }
    },
    availableEnvironments: cloneDeep(FALLBACK_SERVICE_CONFIG.availableEnvironments)
  };
}

function cloneDeep(value) {
  try {
    return JSON.parse(JSON.stringify(value));
  } catch (_) {
    return {};
  }
}

function pickString(value, fallback = '') {
  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (trimmed !== '') {
      return trimmed;
    }
  }
  return fallback;
}

function hostsMatch(hostA, hostB) {
  if (!hostA || !hostB) return false;
  const normalize = (value) => pickString(value).toLowerCase().split(':')[0];
  return normalize(hostA) === normalize(hostB);
}

function normalizeAppService(section, fallback) {
  const base = { ...fallback };
  if (!section || typeof section !== 'object') return base;
  base.host = pickString(section.host, base.host);
  base.baseUrl = pickString(section.baseUrl ?? section.base_url, base.baseUrl);
  return base;
}

function normalizeOpenAiService(section, fallback) {
  const base = { ...fallback };
  if (!section || typeof section !== 'object') return base;
  base.host = pickString(section.host, base.host);
  base.baseUrl = pickString(section.baseUrl ?? section.base_url, base.baseUrl);
  base.chatUrl = pickString(section.chatUrl ?? section.chat_url, base.chatUrl);
  return base;
}

function normalizeRagService(section, fallback) {
  const base = { ...fallback };
  if (!section || typeof section !== 'object') return base;
  base.host = pickString(section.host, base.host);
  base.baseUrl = pickString(section.baseUrl ?? section.base_url, base.baseUrl);
  base.heroesUrl = pickString(section.heroesUrl ?? section.heroes_url, base.heroesUrl);
  return base;
}

function mergeServiceConfig(rawPayload) {
  const payload = rawPayload && typeof rawPayload === 'object' ? rawPayload : {};
  const fallback = createFallbackConfig();
  const incomingServices = payload.services && typeof payload.services === 'object' ? payload.services : {};

  const mergedServices = {
    app: normalizeAppService(incomingServices.app, fallback.services.app),
    openai: normalizeOpenAiService(incomingServices.openai, fallback.services.openai),
    rag: normalizeRagService(incomingServices.rag, fallback.services.rag)
  };

  serviceConfig = {
    ...fallback,
    ...payload,
    environment: {
      ...fallback.environment,
      ...(payload.environment && typeof payload.environment === 'object' ? payload.environment : {})
    },
    services: mergedServices,
    availableEnvironments: {
      ...fallback.availableEnvironments,
      ...(payload.availableEnvironments && typeof payload.availableEnvironments === 'object'
        ? payload.availableEnvironments
        : {})
    }
  };

  serviceLabels = computeUiLabels(serviceConfig);

  if (runtimeWindow) {
    runtimeWindow.__CLEAN_MARVEL_SERVICES__ = {
      config: serviceConfig,
      labels: serviceLabels
    };
  }

  return serviceConfig;
}

async function ensureServiceConfig() {
  if (!serviceConfigPromise) {
    serviceConfigPromise = (async () => {
      try {
        const response = await fetch(SERVICE_CONFIG_ENDPOINT, { cache: 'no-store' });
        if (response.ok) {
          const payload = await response.json().catch(() => null);
          if (payload?.estado === 'éxito' && payload?.datos) {
            mergeServiceConfig(payload.datos);
          }
        }
      } catch (error) {
        console.warn('No se pudo cargar la configuración de microservicios.', error);
      }
      return serviceConfig;
    })();
  }

  return serviceConfigPromise;
}

// devuelve etiquetas genéricas para la UI, sin puertos
function computeUiLabels(config) {
  return {
    app: 'Web principal',
    openai: 'Modelo IA',
    rag: 'Microservicio RAG',
    mode: 'generic'
  };
}

function getRagEndpoint() {
  return pickString(
    serviceConfig?.services?.rag?.heroesUrl,
    FALLBACK_SERVICE_CONFIG.services.rag.heroesUrl
  );
}

// misma idea, por si otras partes del código usan esta función
function toDisplayLabels() {
  return {
    app: 'Web principal',
    openai: 'Modelo IA',
    rag: 'Microservicio RAG',
    mode: 'generic'
  };
}

// aplica los textos al HUD de microservicios y al HUD de RAG
function applyMicroserviceLabels(labels) {
  const display = toDisplayLabels(labels);
  const titleText = 'Canal Microservicios';
  const ragTitleText = 'Sistema RAG';
  const subtitleText = 'Web principal ↔ Microservicios Open IA';
  const ragSubtitleText = 'Web principal ↔ Microservicios RAG';

  const update = (selector, text) => {
    const element = document.querySelector(selector);
    if (element && typeof text === 'string') {
      element.textContent = text;
    }
  };

  // Panel general (crear cómic)
  update('#microservice-comm-panel .msc-title', titleText);
  update('#microservice-comm-panel .msc-subtitle', subtitleText);
  update('#microservice-comm-panel .msc-step[data-step=\"process\"]', '⚙ Procesando en Microservicios Open IA…');
  update('#microservice-comm-panel .msc-step[data-step=\"return\"]', '⬅ Devolviendo a la Web principal…');

  // Panel RAG (botón "Comparar héroes (RAG)")
  update('#rag-comm-panel .msc-title', ragTitleText);
  update('#rag-comm-panel .msc-subtitle', ragSubtitleText);
  update('#rag-comm-panel .msc-step[data-step=\"send\"]', '▶ Enviando héroes seleccionados…');
  update('#rag-comm-panel .msc-step[data-step=\"process\"]', '🤖 Consultando la base RAG…');
  update('#rag-comm-panel .msc-step[data-step=\"relay\"]', '📡 Comunicando con modelo IA…');
  update('#rag-comm-panel .msc-step[data-step=\"return\"]', '⬅ Respondiendo a la Web principal…');
}

function escapeSelector(value) {
  if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
    return CSS.escape(value);
  }
  return String(value).replaceAll(/([^\w-])/g, '\\$1');
}

let isGeneratingComic = false;
let isComparingRag = false;
let heroSelectionWarningTimer = null;
const comicGenerateLabelText = comicGenerateButton ? (comicGenerateButton.querySelector('span')?.textContent ?? 'Generar cómic') : 'Generar cómic';

function setGeneratingState(isGenerating) {
  isGeneratingComic = isGenerating;
  if (comicGenerateButton) {
    comicGenerateButton.disabled = isGenerating;
    comicGenerateButton.classList.toggle('opacity-70', isGenerating);
    const label = comicGenerateButton.querySelector('span');
    if (label) {
      label.textContent = isGenerating ? 'Generando...' : comicGenerateLabelText;
    }
  }
  if (comicCancelButton) {
    comicCancelButton.disabled = isGenerating;
    comicCancelButton.classList.toggle('opacity-70', isGenerating);
  }
}

function setComparingRagState(isComparing) {
  isComparingRag = isComparing;
  if (ragCompareButton) {
    ragCompareButton.disabled = isComparing;
    ragCompareButton.classList.toggle('opacity-70', isComparing);
  }
}

function showHeroSelectionWarning(message = '') {
  if (!heroWarning) {
    return;
  }

  if (heroSelectionWarningTimer) {
    clearTimeout(heroSelectionWarningTimer);
    heroSelectionWarningTimer = null;
  }

  if (message === '') {
    heroWarning.textContent = '';
    heroWarning.classList.add('hidden');
    return;
  }

  heroWarning.textContent = message;
  heroWarning.classList.remove('hidden');

  if (ragResultBox) {
    ragResultBox.classList.add('hidden');
  }
}

function updateRagResult(message) {
  showHeroSelectionWarning('');
  if (!ragResultBox) return;
  if (!message) {
    ragResultBox.textContent = '';
    ragResultBox.classList.add('hidden');
    return;
  }
  ragResultBox.textContent = message;
  ragResultBox.classList.remove('hidden');
}

function hideRagResultSection() {
  if (!ragResultSection) return;
  ragResultSection.classList.add('hidden');
  if (ragHeroesPreview) {
    ragHeroesPreview.innerHTML = '';
  }
  if (ragResultText) {
    ragResultText.textContent = '';
    ragResultText.classList.remove('is-loading');
  }
  latestRagNarrationText = '';
  resetAudioPlayer(ragAudioPlayer);
  syncTtsButtonState(ragAudioButton, false);
  if (heroSelectionSection) {
    heroSelectionSection.classList.remove('hidden');
  }
  if (runtimeWindow?.RAGMSC) {
    runtimeWindow.RAGMSC.hidePanel();
  }
}

function showRagStatus(message) {
  if (!ragResultText) return;
  if (ragHeroesPreview) {
    ragHeroesPreview.innerHTML = '';
  }
  ragResultText.textContent = message;
  ragResultText.classList.add('is-loading');
  latestRagNarrationText = '';
  resetAudioPlayer(ragAudioPlayer);
  syncTtsButtonState(ragAudioButton, false);
}

function renderRagResult(answer, contexts, heroIds) {
  if (!ragResultSection || !ragResultText) return;

  if (heroSelectionSection) {
    heroSelectionSection.classList.add('hidden');
  }
  if (comicSlideshowSection) {
    comicSlideshowSection.classList.add('hidden');
  }
  if (comicStorySection) {
    comicStorySection.classList.add('hidden');
  }

  ragResultSection.classList.remove('hidden');
  ragResultText.textContent = answer;
  ragResultText.classList.remove('is-loading');
  latestRagNarrationText = typeof answer === 'string' ? answer.trim() : '';
  syncTtsButtonState(ragAudioButton, latestRagNarrationText !== '');

  if (!ragHeroesPreview) {
    return;
  }

  ragHeroesPreview.innerHTML = '';

  const heroEntries = [];

  if (Array.isArray(contexts) && contexts.length > 0) {
    contexts.forEach((context) => {
      const contextHeroId = typeof context.heroId === 'string' ? context.heroId : null;
      if (!contextHeroId) return;
      const hero = heroState.selected.get(contextHeroId) || heroState.all.find(item => (item.heroId || item.id) === contextHeroId);
      if (!hero) return;
      heroEntries.push(hero);
    });
  }

  if (heroEntries.length === 0) {
    heroIds.forEach((heroId) => {
      const hero = heroState.selected.get(heroId);
      if (hero) {
        heroEntries.push(hero);
      }
    });
  }

  if (heroEntries.length === 0) {
    heroEntries.push(...Array.from(heroState.selected.values()));
  }

  heroEntries.slice(0, 4).forEach((hero) => {
    const card = document.createElement('article');
    card.className = 'rag-hero-card';

    const image = document.createElement('img');
    image.src = hero.imagen || 'https://dummyimage.com/600x360/111827/94a3b8&text=Hero';
    image.alt = hero.nombre ? `Retrato de ${hero.nombre}` : 'Héroe seleccionado';
    card.appendChild(image);

    const body = document.createElement('div');
    body.className = 'rag-hero-card__body';

    const name = document.createElement('h4');
    name.className = 'rag-hero-card__name';
    name.textContent = hero.nombre || 'Héroe sin nombre';
    body.appendChild(name);

    const excerpt = document.createElement('p');
    excerpt.className = 'rag-hero-card__excerpt';
    const rawContent = typeof hero.contenido === 'string' ? hero.contenido : '';
    const normalizedContent = rawContent !== '' ? rawContent.replaceAll(/\s+/g, ' ').trim() : '';
    excerpt.textContent = normalizedContent !== '' ? normalizedContent.slice(0, 220) + (rawContent.length > 220 ? '…' : '') : 'Sin descripción disponible.';
    body.appendChild(excerpt);

    card.appendChild(body);
    ragHeroesPreview.appendChild(card);
  });
}

function hideCommunicationPanel() {
  if (runtimeWindow?.MSC && typeof runtimeWindow.MSC.hidePanel === 'function') {
    runtimeWindow.MSC.hidePanel();
  }
}

function clearGeneratedComic() {
  if (slideshowInterval) clearInterval(slideshowInterval);

  if (comicSlideshowSection) comicSlideshowSection.classList.add('hidden');
  if (comicStorySection) comicStorySection.classList.add('hidden');
  if (heroSelectionSection) heroSelectionSection.classList.remove('hidden');

  if (generatedComicTitle) generatedComicTitle.textContent = '';
  if (slideshowContainer) slideshowContainer.innerHTML = '';

  if (comicOutputStorySummary) {
    comicOutputStorySummary.textContent = 'Cuando generes un cómic con IA, la sinopsis y los paneles aparecerán aquí.';
  }
  if (comicOutputPanels) {
    comicOutputPanels.innerHTML = '';
  }
  if (comicOutputPanelsEmpty) {
    comicOutputPanelsEmpty.textContent = 'Las viñetas generadas se mostrarán en este espacio.';
    comicOutputPanelsEmpty.classList.remove('hidden');
  }

  latestComicNarrationText = '';
  resetAudioPlayer(comicAudioPlayer);
  syncTtsButtonState(comicAudioButton, false);

  hideCommunicationPanel();
}

function buildComicNarrationText(story, panels) {
  const sections = [];
  const safeStory = story && typeof story === 'object' ? story : {};
  const safePanels = Array.isArray(panels) ? panels : [];

  const summary = typeof safeStory.summary === 'string' ? safeStory.summary.trim() : '';
  const title = typeof safeStory.title === 'string' ? safeStory.title.trim() : '';
  if (title !== '') sections.push(title);
  if (summary !== '') sections.push(summary);

  safePanels.forEach((panel, index) => {
    if (!panel || typeof panel !== 'object') return;
    const parts = [];
    const panelTitle = typeof panel.title === 'string' ? panel.title.trim() : '';
    const panelDescription = typeof panel.description === 'string' ? panel.description.trim() : '';
    const panelCaption = typeof panel.caption === 'string' ? panel.caption.trim() : '';
    if (panelTitle !== '') parts.push(panelTitle);
    if (panelDescription !== '') parts.push(panelDescription);
    if (panelCaption !== '') parts.push(panelCaption);
    if (parts.length > 0) {
      sections.push(`Viñeta ${index + 1}: ${parts.join(' ')}`);
    }
  });

  return sections.join('\n\n').trim();
}

let currentSlide = 0;
function showSlide(index) {
  const slides = slideshowContainer.children;
  if (!slides || slides.length === 0) return;
  Array.from(slides).forEach((slide, i) => {
    slide.classList.toggle('hidden', i !== index);
  });
}

function nextSlide() {
  const slides = slideshowContainer.children;
  if (!slides || slides.length <= 1) return;
  currentSlide = (currentSlide + 1) % slides.length;
  showSlide(currentSlide);
}

slideshowPrev.addEventListener('click', () => {
  const slides = slideshowContainer.children;
  if (!slides || slides.length <= 1) return;
  currentSlide = (currentSlide - 1 + slides.length) % slides.length;
  showSlide(currentSlide);
  if (slideshowInterval) {
    clearInterval(slideshowInterval);
    slideshowInterval = setInterval(nextSlide, 3000);
  }
});

slideshowNext.addEventListener('click', () => {
  nextSlide();
  if (slideshowInterval) {
    clearInterval(slideshowInterval);
    slideshowInterval = setInterval(nextSlide, 3000);
  }
});

closeComicResultButton.addEventListener('click', () => {
  resetSelections();
  clearGeneratedComic();
});

if (comicAudioButton && comicAudioPlayer) {
  comicAudioButton.addEventListener('click', () => requestTtsPlayback({
    getText: () => latestComicNarrationText,
    button: comicAudioButton,
    audioElement: comicAudioPlayer,
    emptyMessage: 'Genera un cómic para poder escucharlo.'
  }));
}

if (ragAudioButton && ragAudioPlayer) {
  ragAudioButton.addEventListener('click', () => requestTtsPlayback({
    getText: () => latestRagNarrationText,
    button: ragAudioButton,
    audioElement: ragAudioPlayer,
    emptyMessage: 'Todavía no hay una comparación para reproducir.'
  }));
}

function renderGeneratedComic(data) {
  if (!data) {
    clearGeneratedComic();
    return;
  }

  if (heroSelectionSection) heroSelectionSection.classList.add('hidden');
  if (comicSlideshowSection) comicSlideshowSection.classList.remove('hidden');
  if (comicStorySection) comicStorySection.classList.remove('hidden');

  const story = data.story || {};
  const panels = Array.isArray(story.panels) ? story.panels : [];

  if (generatedComicTitle) {
    generatedComicTitle.textContent = story.title || 'Cómic generado con IA';
  }

  const selectedHeroes = Array.from(heroState.selected.values());
  if (slideshowContainer) {
    slideshowContainer.innerHTML = '';
    if (selectedHeroes.length > 0) {
      selectedHeroes.forEach((hero, index) => {
        const slide = document.createElement('div');
        slide.className = 'transition-opacity duration-700 ease-in-out';
        if (index !== 0) slide.classList.add('hidden');
        slide.innerHTML = `<img src="${hero.imagen}" class="absolute block w-full h-full object-cover" alt="${hero.nombre}">`;
        slideshowContainer.appendChild(slide);
      });
    }

    const slides = slideshowContainer.children;
    if (slides.length > 1) {
      slideshowPrev.classList.remove('hidden');
      slideshowNext.classList.remove('hidden');
      if (slideshowInterval) clearInterval(slideshowInterval);
      slideshowInterval = setInterval(nextSlide, 3000);
    } else {
      slideshowPrev.classList.add('hidden');
      slideshowNext.classList.add('hidden');
      if (slideshowInterval) clearInterval(slideshowInterval);
    }
    currentSlide = 0;
    showSlide(0);
  }

  if (comicOutputStorySummary) {
    comicOutputStorySummary.textContent = story.summary || 'La IA generó este cómic en función de tu selección de héroes.';
  }

  if (comicOutputPanels) {
    comicOutputPanels.innerHTML = '';
    panels.forEach((panel, index) => {
      const panelCard = document.createElement('article');
      panelCard.className = 'rounded-xl border border-slate-700/60 bg-slate-900/50 p-4 space-y-3';

      if (panel.image) {
        const image = document.createElement('img');
        image.src = panel.image;
        image.alt = panel.title ? `Viñeta ${index + 1}: ${panel.title}` : `Viñeta ${index + 1}`;
        image.className = 'w-full rounded-lg border border-slate-700/50 object-cover';
        panelCard.appendChild(image);
      }

      const title = document.createElement('h4');
      title.className = 'text-lg font-semibold text-white';
      title.textContent = panel.title || `Viñeta ${index + 1}`;
      panelCard.appendChild(title);

      if (panel.description) {
        const description = document.createElement('p');
        description.className = 'text-sm text-gray-300 leading-relaxed';
        description.textContent = panel.description;
        panelCard.appendChild(description);
      }

      if (panel.caption) {
        const caption = document.createElement('p');
        caption.className = 'text-xs text-gray-400 italic';
        caption.textContent = panel.caption;
        panelCard.appendChild(caption);
      }

      comicOutputPanels.appendChild(panelCard);
    });
  }

  if (comicOutputPanelsEmpty) {
    comicOutputPanelsEmpty.classList.toggle('hidden', panels.length > 0);
    if (panels.length === 0) {
      comicOutputPanelsEmpty.textContent = 'La IA no devolvió viñetas. Intenta generar nuevamente.';
    }
  }

  latestComicNarrationText = buildComicNarrationText(story, panels);
  syncTtsButtonState(comicAudioButton, latestComicNarrationText !== '');
}

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
      const message = payload?.message ?? 'No se pudo cargar la actividad del cómic.';
      throw new Error(message);
    }

    const entries = Array.isArray(payload?.datos) ? payload.datos : [];
    activityState.entries = entries
      .map(normalizeActivityEntry)
      .filter((entry) => entry !== null);

    activityState.index = activityState.entries.length > 0 ? 0 : -1;
  } catch (error) {
    activityState.entries = [];
    activityState.index = -1;
    if (error?.message) {
      showMessage(comicMessage, error.message, true);
    }
  } finally {
    updateActivityView();
  }
}

async function recordActivity(action, title, targetMessage = comicMessage) {
  const payload = { action, title };

  try {
    const response = await fetch(ACTIVITY_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const data = await response.json().catch(() => null);

    if (!response.ok || data?.estado !== 'éxito') {
      const message = data?.message ?? 'No se pudo registrar la actividad del cómic.';
      throw new Error(message);
    }

    const entry = normalizeActivityEntry(data?.datos ?? null);
    if (!entry) return;

    activityState.entries = [entry, ...activityState.entries].slice(0, 100);
    activityState.index = 0;
    updateActivityView();
  } catch (error) {
    if (targetMessage) {
      showMessage(targetMessage, error?.message ?? 'No se pudo registrar la actividad.', true);
    }
  }
}

function updateActivityView() {
  const total = activityState.entries.length;
  if (total === 0 || activityState.index < 0) {
    activityEmpty.classList.remove('hidden');
    activityView.classList.add('hidden');
    return;
  }

  const entry = activityState.entries[activityState.index];
  activityEmpty.classList.add('hidden');
  activityView.classList.remove('hidden');

  const baseTagClasses = 'inline-flex items-center px-2 py-1 rounded-md text-[0.65rem] font-black uppercase tracking-[0.18em] border';
  activityTag.className = `${baseTagClasses} ${ACTIVITY_STYLES[entry.action] || 'text-gray-300 border-slate-500/40'}`;
  activityTag.textContent = ACTIVITY_LABELS[entry.action] || entry.action;
  activityDate.textContent = formatDateTime(entry.timestamp);
  activityCounter.textContent = `${activityState.index + 1}/${total}`;
  activityTitle.textContent = entry.title;
}

function handleActivityNavigation(direction) {
  const total = activityState.entries.length;
  if (total === 0) return;
  activityState.index = (activityState.index + direction + total) % total;
  updateActivityView();
}

activityPrevButton.addEventListener('click', () => handleActivityNavigation(-1));
activityNextButton.addEventListener('click', () => handleActivityNavigation(1));
activityClearButton.addEventListener('click', async () => {
  if (activityState.entries.length === 0) return;
  try {
    const response = await fetch(ACTIVITY_ENDPOINT, { method: 'DELETE' });
    const payload = await response.json().catch(() => null);

    if (!response.ok || payload?.estado !== 'éxito') {
      const message = payload?.message ?? 'No se pudo limpiar la actividad del cómic.';
      throw new Error(message);
    }

    activityState.entries = [];
    activityState.index = -1;
    updateActivityView();
    showMessage(comicMessage, 'Registro de actividad vacío.');
  } catch (error) {
    showMessage(comicMessage, error?.message ?? 'No se pudo limpiar la actividad.', true);
  }
});

function updateSelectedHeroesUI() {
  const entries = Array.from(heroState.selected.entries());
  selectedHeroesList.innerHTML = '';

  if (entries.length === 0) {
    selectedHeroesEmpty.classList.remove('hidden');
  } else {
    selectedHeroesEmpty.classList.add('hidden');
    entries.forEach(([heroId, hero]) => {
      const badge = document.createElement('span');
      badge.className = 'selected-hero-badge';
      badge.innerHTML = `
        <span>${hero.nombre}</span>
        <button type="button" class="selected-hero-remove" aria-label="Quitar ${hero.nombre}" data-hero-id="${heroId}">✕</button>
      `;
      selectedHeroesList.appendChild(badge);
    });
  }

  selectedHeroesCount.textContent = entries.length.toString();
  const heroIds = entries.map(([heroId]) => heroId);
  selectedHeroesInput.value = JSON.stringify(heroIds);
  if (runtimeWindow) {
    runtimeWindow.selectedHeroes = heroIds;
  }
}

selectedHeroesList.addEventListener('click', (event) => {
  const button = event.target.closest('.selected-hero-remove');
  if (!button) return;
  const heroId = button.dataset.heroId;
  if (!heroId) return;
  const card = heroGrid.querySelector(`[data-hero-id="${escapeSelector(heroId)}"]`);
  const checkbox = card?.querySelector('input[type="checkbox"]');
  if (checkbox) {
    checkbox.checked = false;
    toggleHeroSelection(heroId, false);
  }
});

function toggleHeroSelection(heroId, shouldSelect) {
  const hero = heroState.all.find(item => (item.heroId || item.id || item.uuid) === heroId);
  if (!hero) return;

  const card = heroGrid.querySelector(`[data-hero-id="${escapeSelector(heroId)}"]`);
  if (shouldSelect) {
    heroState.selected.set(heroId, hero);
    card?.classList.add('is-selected');
    recordActivity('SELECCION', `Héroe añadido al cómic: ${hero.nombre}`);
  } else {
    heroState.selected.delete(heroId);
    card?.classList.remove('is-selected');
    recordActivity('DESELECCION', `Héroe retirado del cómic: ${hero.nombre}`);
  }

  updateSelectedHeroesUI();
  if (heroState.selected.size > 0) {
    showHeroSelectionWarning('');
  }
}

function buildHeroCard(hero) {
  const heroId = hero.heroId || hero.id || crypto.randomUUID();
  const isSelected = heroState.selected.has(heroId);
  const card = document.createElement('label');
  card.className = `hero-card cursor-pointer ${isSelected ? 'is-selected' : ''}`;
  card.dataset.heroId = heroId;
  card.innerHTML = `
    <input type="checkbox" class="hero-card-checkbox" data-hero-id="${heroId}" ${isSelected ? 'checked' : ''} aria-label="Seleccionar ${hero.nombre}">
    <img src="${hero.imagen || ''}" alt="${hero.nombre || 'Héroe Marvel'}" class="hero-card-image">
    <div class="flex flex-col gap-2 p-5">
      <h3 class="hero-card-title">${hero.nombre || 'Héroe sin nombre'}</h3>
      <p class="hero-card-meta">${hero.contenido ? hero.contenido.replace(/\n/g, ' ') : 'Sin descripción disponible.'}</p>
    </div>
  `;

  const checkbox = card.querySelector('input[type="checkbox"]');
  checkbox.addEventListener('change', (event) => {
    toggleHeroSelection(heroId, event.target.checked);
  });

  return card;
}

function renderHeroes() {
  const heroes = heroState.filtered;
  heroGrid.innerHTML = '';

  if (heroes.length === 0) {
    heroEmptyState.classList.remove('hidden');
    heroCountLabel.textContent = '0 héroes';
    return;
  }

  heroEmptyState.classList.add('hidden');
  heroCountLabel.textContent = `${heroes.length} ${heroes.length === 1 ? 'héroe' : 'héroes'}`;

  const fragment = document.createDocumentFragment();
  heroes.forEach(hero => fragment.appendChild(buildHeroCard(hero)));
  heroGrid.appendChild(fragment);
}

function applyHeroFilter() {
  const query = (heroSearchInput.value || '').trim().toLowerCase();
  if (!query) {
    heroState.filtered = [...heroState.all];
  } else {
    heroState.filtered = heroState.all.filter(hero => {
      const name = (hero.nombre || '').toLowerCase();
      const content = (hero.contenido || '').toLowerCase();
      return name.includes(query) || content.includes(query);
    });
  }
  renderHeroes();
}

heroSearchInput.addEventListener('input', () => {
  if (runtimeWindow?.requestAnimationFrame) {
    runtimeWindow.requestAnimationFrame(applyHeroFilter);
  } else {
    applyHeroFilter();
  }
});

function resetSelections(options = {}) {
  const { suppressActivity = false } = options;
  heroState.selected.clear();
  heroGrid.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
    checkbox.checked = false;
    checkbox.closest('.hero-card')?.classList.remove('is-selected');
  });
  updateSelectedHeroesUI();
  showHeroSelectionWarning('');
  if (!suppressActivity) {
    recordActivity('CANCELADO', 'Has descartado los cambios del cómic.');
  }
}

comicCancelButton.addEventListener('click', () => {
  if (isGeneratingComic) return;
  comicForm.reset();
  resetSelections();
  clearGeneratedComic();
  hideCommunicationPanel();
  updateRagResult('');
  hideRagResultSection();
  showMessage(comicMessage, 'Se limpió la selección y el resultado generado.');
});

/* 🔵 2) SUBMIT: forzamos HUD + pasamos a process */
comicForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (isGeneratingComic) return;

  const selectedHeroes = Array.from(heroState.selected.values());
  if (selectedHeroes.length === 0) {
    hideCommunicationPanel();
    showHeroSelectionWarning('Debes seleccionar al menos 1 héroe.');
    return;
  }
  showHeroSelectionWarning('');

  // mostrar HUD desde el inicio
  if (runtimeWindow?.MSC) {
    runtimeWindow.MSC.showPanel();
    runtimeWindow.MSC.setStep('process');
  }

  setGeneratingState(true);
  showMessage(comicMessage, 'Generando cómic con IA, esto puede tardar unos segundos...');

  try {
    const response = await fetch('/comics/generate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ heroIds: selectedHeroes.map(hero => hero.heroId) })
    });

    const payload = await response.json().catch(() => null);
    const storyText = typeof payload?.datos?.story?.summary === 'string' && payload.datos.story.summary.trim() !== ''
      ? payload.datos.story.summary
      : typeof payload?.datos?.story?.title === 'string'
        ? payload.datos.story.title.trim()
        : '';

    if (!response.ok || payload?.estado !== 'éxito' || storyText === '') {
      const errorMessage = payload?.message || 'No se pudo generar el cómic con IA.';
      throw new Error(errorMessage);
    }

    /* 🔵 3) AQUÍ metemos el delay para que se vean los pasos */
    if (runtimeWindow?.MSC) {
      runtimeWindow.MSC.setStep('return');
      setTimeout(() => runtimeWindow?.MSC?.markSuccess?.(), 350);
    }

    renderGeneratedComic(payload.datos);

    const storyTitle = payload?.datos?.story?.title || 'tu cómic';
    recordActivity('COMIC', `Generaste "${storyTitle}" con ${selectedHeroes.length} héroes.`);

    showMessage(comicMessage, '¡Cómic generado con éxito!');
  } catch (error) {
    console.error(error);
    runtimeWindow?.MSC?.markError?.(error instanceof Error ? error.message : undefined);
    showMessage(comicMessage, error instanceof Error ? error.message : 'No se pudo generar el cómic.', true);
  } finally {
    setGeneratingState(false);
  }
});

async function compareSelectedHeroesRag() {
  if (isComparingRag) return;

  const selectedIds = Array.from(heroState.selected.keys());
  if (!ragResultBox) return;

  if (selectedIds.length !== 2) {
    hideRagResultSection();
    const message = selectedIds.length < 2
      ? 'Debes seleccionar 2 héroes.'
      : 'Solo puedes comparar 2 héroes.';
    showHeroSelectionWarning(message);
    return;
  }

  showHeroSelectionWarning('');

  await ensureServiceConfig();
  const displayLabels = toDisplayLabels(serviceLabels);
  const ragHostLabel = displayLabels.rag;
  const appHostLabel = displayLabels.app;
  const targetEndpoint = getRagEndpoint();

  setComparingRagState(true);
  updateRagResult('⏳ Comparando héroes con RAG...');
  showRagStatus('⏳ Comparando héroes con RAG...');
  if (runtimeWindow?.RAGMSC) {
    runtimeWindow.RAGMSC.showPanel();
    runtimeWindow.RAGMSC.setStep('process');
  }

  try {
    const response = await fetch(targetEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        question: 'Compara sus atributos y resume el resultado',
        heroIds: selectedIds
      })
    });

    if (!response.ok) {
      throw new Error('RAG request failed');
    }

    if (runtimeWindow?.RAGMSC) {
      runtimeWindow.RAGMSC.setStep('relay');
    }

    const payload = await response.json().catch(() => null);
    const answer = typeof payload?.answer === 'string' && payload.answer.trim() !== ''
      ? payload.answer.trim()
      : null;
    const contexts = Array.isArray(payload?.contexts) ? payload.contexts : [];

    if (!answer) {
      updateRagResult('Sin respuesta del modelo.');
      showRagStatus('Sin respuesta del modelo.');
    } else {
      updateRagResult('');
      renderRagResult(cleanRagAnswer(answer), contexts, selectedIds);
      if (runtimeWindow?.RAGMSC) {
        runtimeWindow.RAGMSC.setStep('return');
        setTimeout(() => runtimeWindow?.RAGMSC?.markSuccess?.(`✅ Comparación lista desde ${ragHostLabel}.`), 350);
      }
    }
  } catch (error) {
    console.error(error);
    updateRagResult('❌ Error al consultar el RAG.');
    showRagStatus('❌ Error al consultar el RAG.');
    hideRagResultSection();
    if (runtimeWindow?.RAGMSC) {
      const message = error instanceof Error ? error.message : undefined;
      const defaultMessage = `❌ Error consultando servicio RAG (${ragHostLabel}).`;
      runtimeWindow.RAGMSC.markError?.(message || defaultMessage);
    }
  } finally {
    setComparingRagState(false);
  }
}

async function loadHeroes() {
  try {
    const response = await fetch('/heroes', { cache: 'no-store' });
    if (!response.ok) throw new Error('No se pudieron cargar los héroes.');

    const payload = await response.json();
    if (payload?.estado !== 'éxito') {
      throw new Error(payload?.message || 'No se pudieron cargar los héroes.');
    }

    const heroes = Array.isArray(payload?.datos) ? payload.datos : [];

    heroState.all = heroes;
    heroState.filtered = [...heroState.all];
    renderHeroes();
  } catch (error) {
    heroCountLabel.textContent = '0 héroes';
    heroEmptyState.classList.remove('hidden');
    heroEmptyState.textContent = 'No pudimos cargar héroes. Intenta recargar la página.';
    console.error(error);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  clearGeneratedComic();
  loadHeroes();
  updateActivityView();
  loadActivityFromServer();
  hideRagResultSection();
  applyMicroserviceLabels(serviceLabels);
  ensureServiceConfig()
    .then(() => applyMicroserviceLabels(serviceLabels))
    .catch(() => {});
});

if (runtimeWindow) {
  runtimeWindow.selectedHeroes = runtimeWindow.selectedHeroes ?? [];
}

if (ragCompareButton) {
  ragCompareButton.addEventListener('click', compareSelectedHeroesRag);
}

if (closeRagResultButton) {
  closeRagResultButton.addEventListener('click', () => {
    hideRagResultSection();
    updateRagResult('');
  });
}
function cleanRagAnswer(answer) {
  const normalised = answer.replaceAll(/\r\n/g, '\n');

  const segments = normalised.split(/\n\s*\n/);
  if (segments.length <= 1) {
    return normalised;
  }

  const filtered = segments.filter((segment) => {
    const trimmed = segment.trim();
    if (trimmed === '') return false;
    const isTableLine = /^[\|\-:\s\*☆★]+$/.test(trimmed.replace(/[A-Za-zÁÉÍÓÚÜÑ0-9().,+/]/g, ''));
    const startsWithHeader = /^\|?[\s\-_:|]+\|?$/i.test(trimmed);
    return !(trimmed.includes('|') || isTableLine || startsWithHeader);
  });

  if (filtered.length === 0) {
    return segments[segments.length - 1].trim();
  }

  return filtered.join('\n\n').trim();
}
