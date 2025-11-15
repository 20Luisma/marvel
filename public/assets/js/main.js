// Shared utilities for Clean Marvel pages
export const showMessage = (element, message, isError = false, timeoutMs = 4000) => {
  if (!element) return;
  element.textContent = message;
  element.classList.remove('hidden');
  element.classList.toggle('text-green-400', !isError);
  element.classList.toggle('text-red-500', isError);
  if (timeoutMs > 0) {
    const timeout = globalThis?.setTimeout ?? setTimeout;
    timeout(() => element.classList.add('hidden'), timeoutMs);
  }
};

export const debounce = (fn, delay = 200) => {
  if (typeof fn !== 'function') {
    throw new TypeError('Expected function for debounce');
  }
  let timeoutId;
  const clear = globalThis?.clearTimeout ?? clearTimeout;
  const schedule = globalThis?.setTimeout ?? setTimeout;
  return (...args) => {
    if (timeoutId) clear(timeoutId);
    timeoutId = schedule(() => fn(...args), delay);
  };
};

export const normalize = (value) => {
  return (value ?? '')
    .toString()
    .toLowerCase()
    .normalize('NFD')
    .replaceAll(/\p{Diacritic}/gu, '');
};

export const persistToStorage = (key, value) => {
  if (!key) return false;
  try {
    const storage = globalThis?.localStorage;
    if (!storage) return false;
    storage.setItem(key, JSON.stringify(value));
    return true;
  } catch (_) {
    return false;
  }
};

export const readFromStorage = (key, fallback = null) => {
  if (!key) return fallback;
  try {
    const storage = globalThis?.localStorage;
    if (!storage) return fallback;
    const raw = storage.getItem(key);
    if (!raw) return fallback;
    return JSON.parse(raw);
  } catch (_) {
    return fallback;
  }
};

export const formatDateTime = (value, locale = 'es-ES') => {
  try {
    const formatter = typeof Intl !== 'undefined'
      ? new Intl.DateTimeFormat(locale, { dateStyle: 'short', timeStyle: 'short' })
      : null;
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return formatter ? formatter.format(date) : date.toLocaleString();
  } catch (_) {
    return '—';
  }
};
