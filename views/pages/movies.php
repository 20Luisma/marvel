<?php

declare(strict_types=1);

$pageTitle = 'Clean Marvel Album — Marvel Movies';
$additionalStyles = ['/assets/css/albums.css'];
$activeTopAction = 'movies';

require_once __DIR__ . '/../layouts/header.php';
?>

<header class="app-hero">
  <div class="app-hero__inner">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="space-y-3 max-w-3xl">
        <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
        <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
          Explora el universo cinematográfico de Marvel.
        </p>
        <p class="app-hero__meta text-base text-slate-300">
          Espacio reservado para ampliar la experiencia con listados, filtros y trailers oficiales.
        </p>
      </div>
    </div>
    <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
      <?php require_once __DIR__ . '/../partials/top-actions.php'; ?>
    </div>
  </div>
</header>

<main class="site-main">
  <div class="max-w-6xl mx-auto py-10 px-4">
    <section class="card section-lined rounded-2xl p-6 shadow-xl space-y-6">
      <header class="space-y-3">
        <h2 class="text-3xl text-white">Marvel Movies</h2>
      </header>

      <div id="movies-panel" class="rounded-2xl border border-slate-700/80 bg-slate-900/40 p-6 min-h-[240px]">
        <div class="flex flex-wrap items-center gap-3">
          <div
            id="movies-status"
            class="inline-flex items-center rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-2 text-sm font-semibold text-gray-200 shadow-sm animate-pulse"
          >
            Cargando películas Marvel…
          </div>
          <div class="relative flex-1 min-w-[220px] max-w-3xl">
            <span class="pointer-events-none absolute left-3 top-1/2 flex -translate-y-1/2 text-gray-500">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M16 11a5 5 0 11-10 0 5 5 0 0110 0z" />
              </svg>
            </span>
            <input
              id="movies-search-input"
              type="search"
              aria-label="Buscar película"
              placeholder="Buscar película..."
              class="w-full rounded-2xl border border-slate-800 bg-slate-900/80 px-10 py-3 text-sm text-white placeholder:text-slate-500 focus:border-slate-800 focus:outline-none focus:ring-0"
            />
          </div>
        </div>
        <div id="movies-grid" class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3"></div>
      </div>
      <section
        id="movie-detail"
        class="hidden relative rounded-2xl border border-rose-600/70 bg-slate-900/70 p-6 shadow-2xl space-y-6"
      >
        <div class="flex flex-col gap-6 md:flex-row">
          <figure class="w-full md:w-72 flex-shrink-0 overflow-hidden rounded-2xl border border-slate-800/70 bg-slate-950">
            <img
              id="movie-detail-poster"
              class="h-[360px] w-full object-cover"
              src="https://via.placeholder.com/500x750?text=Sin+poster"
              alt="Poster de la película"
              loading="lazy"
            />
          </figure>
          <div class="flex flex-1 flex-col gap-5">
            <div>
              <h3 id="movie-detail-title" class="text-3xl font-bold text-white">Título de la película</h3>
              <p id="movie-detail-meta" class="mt-2 text-sm uppercase tracking-wide text-gray-400">
                Año · ⭐ rating
              </p>
            </div>
            <p id="movie-detail-overview" class="flex-1 text-sm leading-relaxed text-gray-200">
              Sinopsis completa de la película.
            </p>
          </div>
        </div>
        <div class="flex justify-end">
          <button id="movie-detail-back" type="button" class="btn btn-primary">
            Volver a la cartelera
          </button>
        </div>
      </section>
    </section>
  </div>
</main>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script type="module">
  const mockMarvelMovies = {
    ok: true,
    results: [
      {
        id: 299536,
        title: "Avengers: Infinity War",
        poster_path: "/dummy-infinity-war.jpg",
        release_date: "2018-04-25",
        vote_average: 8.3,
        overview:
          "Thanos pone en marcha su plan para reunir las Gemas del Infinito y cambiar el universo para siempre.",
      },
      {
        id: 299534,
        title: "Avengers: Endgame",
        poster_path: "/dummy-endgame.jpg",
        release_date: "2019-04-24",
        vote_average: 8.5,
        overview:
          "Los Vengadores restantes intentan revertir el chasquido y restaurar el equilibrio del universo.",
      },
      {
        id: 497698,
        title: "Black Widow",
        poster_path: "/dummy-black-widow.jpg",
        release_date: "2021-07-07",
        vote_average: 7.1,
        overview:
          "Natasha Romanoff se enfrenta a capítulos oscuros de su pasado y a una vieja amenaza.",
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
      // No data loaded yet, skip filtering.
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
      // TODO: eliminar mock una vez estable la API
      renderFallbackMovies();
    }
  };

  loadMarvelMovies();
</script>
