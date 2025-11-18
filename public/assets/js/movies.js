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
