"use strict";

(function () {
  const endpoint = '/api/github-repo-browser.php';
  const resultContainer = document.getElementById('repo-browser-result');
  const breadcrumbContainer = document.getElementById('repo-browser-breadcrumb');
  const stateContainer = document.getElementById('repo-browser-state');

  if (!resultContainer || !breadcrumbContainer || !stateContainer) {
    return;
  }

  let currentPath = '';

  const escapeHtml = (value) => {
    const str = String(value ?? '');
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  const formatSize = (bytes) => {
    if (!bytes || typeof bytes !== 'number' || Number.isNaN(bytes) || bytes <= 0) {
      return '—';
    }

    const thresholds = [
      { label: 'GB', value: 1024 ** 3 },
      { label: 'MB', value: 1024 ** 2 },
      { label: 'KB', value: 1024 },
    ];

    for (const threshold of thresholds) {
      if (bytes >= threshold.value) {
        return `${(bytes / threshold.value).toFixed(1)} ${threshold.label}`;
      }
    }

    return `${bytes} B`;
  };

  const renderBreadcrumb = (path) => {
    const segments = path === '' ? [] : path.split('/');
    const crumbs = [
      '<button type="button" class="text-xs uppercase tracking-[0.3em] text-slate-300 hover:text-white transition-colors" data-crumb-path="">Raíz</button>',
    ];

    let partial = '';
    segments.forEach((segment) => {
      if (segment === '') {
        return;
      }
      partial = partial === '' ? segment : `${partial}/${segment}`;
      crumbs.push(
        `<button type="button" class="text-xs uppercase tracking-[0.3em] text-slate-300 hover:text-white transition-colors" data-crumb-path="${escapeHtml(partial)}">${escapeHtml(segment)}</button>`
      );
    });

    breadcrumbContainer.innerHTML = crumbs.join('<span class="mx-1 text-slate-700">/</span>');
  };

  const renderRows = (items) => {
    if (!Array.isArray(items) || items.length === 0) {
      return `
        <div class="sonar-alert" role="status">
          <strong>La carpeta está vacía.</strong>
        </div>
      `;
    }

    const rows = items
      .map((item) => {
        const icon = item.type === 'dir' ? '📁' : '📄';
        const displaySize = item.type === 'dir' ? 'Carpeta' : `Archivo · ${formatSize(item.size)}`;
        const nameContent =
          item.type === 'dir'
            ? `<button type="button" class="text-left text-slate-100 font-semibold inline-flex items-center gap-2 hover:text-white transition-colors" data-navigate-path="${escapeHtml(item.path)}">
                 <span class="mr-2">${icon}</span>${escapeHtml(item.name)}
               </button>`
            : `<a class="text-left text-slate-100 font-semibold inline-flex items-center gap-2 hover:text-white transition-colors" href="${escapeHtml(item.html_url)}" target="_blank" rel="noreferrer">
                 <span class="mr-2">${icon}</span>${escapeHtml(item.name)}
               </a>`;

        return `
          <tr class="border-b border-slate-800 last:border-none hover:bg-slate-800/40">
            <td class="px-3 py-3 text-sm text-slate-100">
              ${nameContent}
            </td>
            <td class="px-3 py-3 text-right text-xs uppercase tracking-[0.2em] text-slate-400">
              ${displaySize}
            </td>
          </tr>
        `;
      })
      .join('');

    return `
      <div class="rounded-2xl border border-slate-700/80 bg-slate-900/60 p-2 overflow-x-auto">
        <table class="min-w-full table-fixed text-left">
          <thead>
            <tr class="text-xs uppercase tracking-[0.3em] text-slate-400">
              <th class="px-3 py-3">Carpeta / Archivo</th>
              <th class="px-3 py-3 text-right">Detalles</th>
            </tr>
          </thead>
          <tbody>
            ${rows}
          </tbody>
        </table>
      </div>
    `;
  };

  const renderResult = (items) => {
    resultContainer.innerHTML = renderRows(items);
  };

  const setState = (message) => {
    stateContainer.textContent = message;
  };

  const loadPath = async (path) => {
    currentPath = path;
    renderBreadcrumb(path);
    setState('Cargando contenido del repo…');
    resultContainer.innerHTML = '';

    try {
      const suffix = path !== '' ? `?path=${encodeURIComponent(path)}` : '';
      const response = await fetch(endpoint + suffix, {
        headers: {
          Accept: 'application/json',
        },
        credentials: 'same-origin',
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const payload = await response.json();
      if (payload.estado !== 'exito') {
        throw new Error(payload.mensaje ?? 'No se pudo cargar la carpeta.');
      }

      setState('');
      renderResult(payload.items ?? []);
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Error inesperado.';
      setState(message);
    }
  };

  breadcrumbContainer.addEventListener('click', (event) => {
    const target = event.target.closest('[data-crumb-path]');
    if (target) {
      event.preventDefault();
      const targetPath = target.getAttribute('data-crumb-path') ?? '';
      loadPath(targetPath);
    }
  });

  resultContainer.addEventListener('click', (event) => {
    const target = event.target.closest('[data-navigate-path]');
    if (target) {
      event.preventDefault();
      const nextPath = target.getAttribute('data-navigate-path') ?? '';
      if (nextPath !== currentPath) {
        loadPath(nextPath);
      }
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      loadPath('');
    });
  } else {
    loadPath('');
  }
})();
