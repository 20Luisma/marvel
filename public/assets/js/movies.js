(() => {
  const mockMarvelMovies = {
    ok: true,
    results: [
      {
        id: 299536,
        title: 'Avengers: Infinity War',
        poster_path: '/dummy-infinity-war.jpg',
        release_date: '2018-04-25',
        vote_average: 8.3,
        overview:
          'Thanos pone en marcha su plan para reunir las Gemas del Infinito y cambiar el universo para siempre.',
      },
      {
        id: 299534,
        title: 'Avengers: Endgame',
        poster_path: '/dummy-endgame.jpg',
        release_date: '2019-04-24',
        vote_average: 8.5,
        overview:
          'Los Vengadores restantes intentan revertir el chasquido y restaurar el equilibrio del universo.',
      },
      {
        id: 497698,
        title: 'Black Widow',
        poster_path: '/dummy-black-widow.jpg',
        release_date: '2021-07-07',
        vote_average: 7.1,
        overview:
          'Natasha Romanoff se enfrenta a capítulos oscuros de su pasado y a una vieja amenaza.',
      },
    ],
  };

  const moviesGrid = document.getElementById('movies-grid');
  const statusMessage = document.getElementById('movies-status');
  const moviesPanel = document.getElementById('movies-panel');
  const movieDetailPanel = document.getElementById('movie-detail');
  const movieDetailPoster = document.getElementById('movie-detail-poster');
  const movieDetailTitle = document.getElementById('movie-detail-title');
  const movieDetailMeta = document.getElementById('movie-detail-meta');
  const movieDetailOverview = document.getElementById('movie-detail-overview');
  const movieDetailBack = document.getElementById('movie-detail-back');
  const moviesSearchInput = document.getElementById('movies-search-input');
  const posterBase = 'https://image.tmdb.org/t/p/w342';
  const detailPosterBase = 'https://image.tmdb.org/t/p/w500';
  const placeholder = 'https://via.placeholder.com/342x513?text=Sin+poster';
  const detailPlaceholder = 'https://via.placeholder.com/500x750?text=Sin+poster';

  let allMarvelMovies = [];
  let currentSearchTerm = '';
  let searchTimeout;
  let statusExtraLabel = '';

  // --- ML Recommendation Panel ---
  const showRecommendations = async (movie) => {
    const panel = document.getElementById('movie-detail');
    if (!panel) return;

    moviesPanel?.classList.add('hidden');
    panel.classList.remove('hidden');

    const posterPath = movie.poster_path
      ? `${detailPosterBase}${movie.poster_path}`
      : detailPlaceholder;

    panel.innerHTML = `
      <button id="movie-detail-back" class="btn btn-secondary text-xs mb-4">&larr; Volver</button>
      <div class="flex flex-col md:flex-row gap-6">
        <img src="${posterPath}" alt="${movie.title}" class="w-48 h-72 object-cover rounded-xl border border-slate-700" />
        <div class="flex-1">
          <h2 class="text-2xl font-bold text-white mb-2">${movie.title}</h2>
          <p class="text-sm text-gray-400 mb-3">${movie.release_date?.slice(0, 4) || '—'} · ⭐ ${movie.vote_average?.toFixed(1) || '—'}</p>
          <p class="text-sm text-gray-300 mb-4">${movie.overview || ''}</p>
          <div class="flex items-center gap-2 mb-4">
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-purple-600/30 text-purple-300 border border-purple-500/30">
              🤖 Recomendaciones ML
            </span>
            <span class="text-xs text-gray-500">KNN + Jaccard · PHP-ML</span>
          </div>
          <div id="ml-recommendations" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <p class="text-sm text-gray-400 animate-pulse col-span-full">Analizando similitudes...</p>
          </div>
        </div>
      </div>
    `;

    document.getElementById('movie-detail-back')?.addEventListener('click', hideMovieDetail);

    try {
      const res = await fetch(`/api/movie-recommend.php?id=${movie.id}&limit=5`, {
        headers: { Accept: 'application/json' },
      });
      const data = await res.json();
      const container = document.getElementById('ml-recommendations');
      if (!container) return;

      if (!data.ok || !data.recommendations?.length) {
        container.innerHTML = '<p class="text-sm text-gray-500">No se encontraron películas similares.</p>';
        return;
      }

      container.innerHTML = data.recommendations.map((rec) => {
        const recPoster = rec.poster_path
          ? `https://image.tmdb.org/t/p/w200${rec.poster_path}`
          : 'https://via.placeholder.com/200x300?text=Sin+poster';
        const score = rec.similarity_score || 0;
        const barColor = score >= 60 ? 'bg-green-500' : score >= 40 ? 'bg-yellow-500' : 'bg-red-500';

        return `
          <div class="rounded-xl border border-slate-700/60 bg-slate-800/50 p-3 flex flex-col gap-2 hover:border-purple-500/40 transition-colors">
            <img src="${recPoster}" alt="${rec.title}" class="w-full h-40 object-cover rounded-lg bg-slate-700" />
            <h4 class="text-sm font-bold text-white leading-tight">${rec.title}</h4>
            <div class="flex items-center justify-between text-xs text-gray-400">
              <span>${rec.release_date?.slice(0, 4) || '—'}</span>
              <span>⭐ ${rec.vote_average?.toFixed(1) || '—'}</span>
            </div>
            <div class="mt-auto">
              <div class="flex items-center justify-between text-xs mb-1">
                <span class="text-purple-300">Similitud</span>
                <span class="text-white font-bold">${score}%</span>
              </div>
              <div class="w-full bg-slate-700 rounded-full h-1.5">
                <div class="${barColor} h-1.5 rounded-full transition-all" style="width: ${score}%"></div>
              </div>
            </div>
          </div>
        `;
      }).join('');

      // Add ML metadata badge
      if (data.ml_metadata) {
        const meta = data.ml_metadata;
        container.insertAdjacentHTML('afterend', `
          <div class="mt-4 p-3 rounded-lg bg-slate-800/30 border border-slate-700/40">
            <p class="text-xs text-gray-500">
              🧠 <strong>Algoritmo:</strong> ${meta.algorithm} ·
              <strong>Features:</strong> ${meta.features?.join(', ')} ·
              <strong>Catálogo:</strong> ${meta.catalog_size} películas ·
              <strong>Lib:</strong> ${meta.library}
            </p>
          </div>
        `);
      }
    } catch (err) {
      console.error('ML recommendation error:', err);
      const container = document.getElementById('ml-recommendations');
      if (container) {
        container.innerHTML = '<p class="text-sm text-red-400">Error al obtener recomendaciones.</p>';
      }
    }
  };

  const hideMovieDetail = () => {
    movieDetailPanel?.classList.add('hidden');
    moviesPanel?.classList.remove('hidden');
  };

  const showMovieDetail = (movie) => {
    if (!movieDetailPanel) {
      return;
    }

    const posterPath = movie.poster_path ? `${detailPosterBase}${movie.poster_path}` : detailPlaceholder;
    if (movieDetailPoster) {
      movieDetailPoster.src = posterPath;
      movieDetailPoster.alt = movie.title;
    }

    if (movieDetailTitle) {
      movieDetailTitle.textContent = movie.title;
    }

    if (movieDetailMeta) {
      const releaseYear = movie.release_date ? movie.release_date.slice(0, 4) : '—';
      const rating = typeof movie.vote_average === 'number' ? movie.vote_average.toFixed(1) : '—';
      movieDetailMeta.textContent = `${releaseYear} · ⭐ ${rating}`;
    }

    if (movieDetailOverview) {
      movieDetailOverview.textContent = movie.overview || 'Sin sinopsis disponible.';
    }

    moviesPanel?.classList.add('hidden');
    movieDetailPanel.classList.remove('hidden');
  };

  movieDetailBack?.addEventListener('click', hideMovieDetail);

  const updateStatusMessage = (visibleCount, totalCount, term = '') => {
    if (!statusMessage) {
      return;
    }

    statusMessage.classList.remove('animate-pulse');

    if (totalCount === 0) {
      statusMessage.textContent = 'No hay películas disponibles.';
      return;
    }

    if (term && visibleCount === 0) {
      statusMessage.textContent = `No se encontraron películas para “${term}”.`;
      return;
    }

    let message = '';
    if (term) {
      message = `Mostrando ${visibleCount} de ${totalCount} películas para “${term}”.`;
    } else {
      message = `Mostrando ${totalCount} películas.`;
    }

    if (statusExtraLabel) {
      message += ` ${statusExtraLabel}`;
    }

    statusMessage.textContent = message;
  };

  const createMovieCard = (movie) => {
    const posterPath = movie.poster_path ? `${posterBase}${movie.poster_path}` : placeholder;
    const releaseDate = movie.release_date ?? '';
    const releaseYear = releaseDate ? releaseDate.slice(0, 4) : '—';
    const rating = typeof movie.vote_average === 'number' ? movie.vote_average.toFixed(1) : '—';
    const summary =
      movie.overview && movie.overview.length > 150
        ? `${movie.overview.slice(0, 147)}...`
        : movie.overview || 'Sin sinopsis disponible.';

    const article = document.createElement('article');
    article.className =
      'rounded-2xl border border-slate-700/70 bg-slate-900/70 p-5 flex flex-col gap-3 shadow-lg transition-transform duration-200 hover:-translate-y-1';

    const figure = document.createElement('figure');
    figure.className = 'relative overflow-hidden rounded-xl border border-slate-800';
    const img = document.createElement('img');
    img.src = posterPath;
    img.alt = movie.title;
    img.className = 'w-full h-56 object-cover bg-slate-800';
    figure.appendChild(img);

    const title = document.createElement('h3');
    title.className = 'text-xl font-bold text-white';
    title.textContent = movie.title;

    const meta = document.createElement('div');
    meta.className = 'flex items-center justify-between text-xs text-gray-400';
    meta.innerHTML = `<span>${releaseYear}</span><span>⭐ ${rating}</span>`;

    const overview = document.createElement('p');
    overview.className = 'text-sm text-gray-300 leading-relaxed';
    overview.textContent = summary;

    const actions = document.createElement('div');
    actions.className = 'flex flex-wrap gap-2';
    const details = document.createElement('button');
    details.type = 'button';
    details.className = 'btn btn-secondary text-xs';
    details.textContent = 'Ver detalles';
    details.addEventListener('click', () => showMovieDetail(movie));
    actions.appendChild(details);

    // ML Recommendation button
    const mlBtn = document.createElement('button');
    mlBtn.type = 'button';
    mlBtn.className = 'btn btn-secondary text-xs';
    mlBtn.style.cssText = 'background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); border-color: #7c3aed; color: white;';
    mlBtn.textContent = '🤖 Similares';
    mlBtn.addEventListener('click', () => showRecommendations(movie));
    actions.appendChild(mlBtn);

    article.append(figure, title, meta, overview, actions);
    return article;
  };

  const filterMoviesByTerm = (movies, term) => {
    if (!term) {
      return movies;
    }

    const formattedTerm = term.toLowerCase();
    return movies.filter((movie) => {
      const haystack = `${movie.title} ${movie.overview ?? ''}`.toLowerCase();
      return haystack.includes(formattedTerm);
    });
  };

  const renderMarvelMovies = (movies, term = '') => {
    if (!moviesGrid) {
      return;
    }

    hideMovieDetail();

    moviesGrid.innerHTML = '';
    if (!movies.length) {
      const empty = document.createElement('p');
      empty.className = 'text-sm text-gray-500';
      empty.textContent = term
        ? `No se encontraron películas para “${term}”.`
        : 'No se encontraron películas.';
      moviesGrid.appendChild(empty);
      updateStatusMessage(0, allMarvelMovies.length || 0, term);
      return;
    }

    movies.forEach((movie) => moviesGrid.appendChild(createMovieCard(movie)));
    updateStatusMessage(movies.length, allMarvelMovies.length || movies.length, term);
  };

  const applySearchFilter = () => {
    const filteredMovies = filterMoviesByTerm(allMarvelMovies, currentSearchTerm);
    renderMarvelMovies(filteredMovies, currentSearchTerm);
  };

  const handleMoviesSearch = (event) => {
    if (!allMarvelMovies.length) {
      return;
    }

    currentSearchTerm = event.target.value.trim();
    if (searchTimeout) {
      clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(applySearchFilter, 200);
  };

  moviesSearchInput?.addEventListener('input', handleMoviesSearch);

  const renderFallbackMovies = () => {
    if (statusMessage) {
      statusMessage.textContent = 'Error cargando películas. Mostrando datos de ejemplo.';
      statusMessage.classList.remove('animate-pulse');
    }
    allMarvelMovies = mockMarvelMovies.results;
    statusExtraLabel = '(datos de ejemplo)';
    currentSearchTerm = '';
    if (moviesSearchInput) {
      moviesSearchInput.value = '';
    }
    renderMarvelMovies(allMarvelMovies, '');
  };

  const loadMarvelMovies = async () => {
    if (statusMessage) {
      statusMessage.textContent = 'Cargando películas Marvel…';
      statusMessage.classList.add('animate-pulse');
    }

    try {
      const response = await fetch('/api/marvel-movies.php', {
        headers: { Accept: 'application/json' },
      });
      const data = await response.json();

      if (!response.ok || !data?.ok || !Array.isArray(data.results)) {
        throw new Error('TMDB error');
      }

      allMarvelMovies = data.results;
      statusExtraLabel = '';
      applySearchFilter();
    } catch (error) {
      console.error(error);
      renderFallbackMovies();
    }
  };

  loadMarvelMovies();
})();
