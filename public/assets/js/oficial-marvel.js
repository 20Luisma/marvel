(() => {
  const section = document.getElementById('marvel-dynamic-section');
  if (!section) {
    return;
  }

  const renderMessage = (text) => {
    section.innerHTML = '';
    const message = document.createElement('p');
    message.className = 'oficial-marvel-empty';
    message.textContent = text;
    section.appendChild(message);
  };

  const renderVideo = (data) => {
    if (!data || typeof data !== 'object' || !data.videoId) {
      renderMessage('No hay video disponible en este momento.');
      return;
    }

    section.innerHTML = '';

    const titleEl = document.createElement('h2');
    titleEl.className = 'oficial-marvel-video-title';
    titleEl.textContent = data.title || 'Video destacado';

    const metaEl = document.createElement('p');
    metaEl.className = 'oficial-marvel-meta';
    const published = data.publishedAt ? new Date(data.publishedAt) : null;
    const dateText = published && !Number.isNaN(published.getTime())
      ? published.toLocaleString('es-ES', { dateStyle: 'medium', timeStyle: 'short' })
      : 'Fecha no disponible';
    const channel = data.channelTitle || 'Marvel Entertainment';
    metaEl.textContent = `${channel} · ${dateText}`;

    const frameWrapper = document.createElement('div');
    frameWrapper.className = 'oficial-marvel-frame';

    const iframe = document.createElement('iframe');
    iframe.src = `https://www.youtube.com/embed/${encodeURIComponent(data.videoId)}`;
    iframe.width = '100%';
    iframe.height = '400';
    iframe.title = data.title || 'Reproductor YouTube';
    iframe.frameBorder = '0';
    iframe.allow =
      'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
    iframe.referrerPolicy = 'strict-origin-when-cross-origin';
    iframe.allowFullscreen = true;

    frameWrapper.appendChild(iframe);

    const descriptionEl = document.createElement('div');
    descriptionEl.className = 'oficial-marvel-description movie-description';
    descriptionEl.textContent = data.description || 'Sin descripción disponible.';

    section.append(titleEl, metaEl, frameWrapper, descriptionEl);
  };

  const loadVideo = async () => {
    try {
      const response = await fetch(`/api/ultimo-video-marvel.php?${Date.now()}`, {
        headers: { Accept: 'application/json' },
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      renderVideo(data);
    } catch (error) {
      console.error('Error al sincronizar con Marvel:', error);
      renderMessage('No hay video disponible en este momento.');
    }
  };

  loadVideo();
})();
