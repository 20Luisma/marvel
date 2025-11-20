/* global window, fetch */
(function () {
    'use strict';

    if (window.__heatmapTrackerInitialized === true) {
        return;
    }
    window.__heatmapTrackerInitialized = true;

    const endpoint = '/api/heatmap/click.php';
    const sendClick = (payload) => {
        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        }).catch(() => {
            // Silently ignore network errors to avoid breaking the UI.
        });
    };

    const normalizeCoordinate = (value) => {
        if (Number.isNaN(value) || value < 0) {
            return 0;
        }
        if (value > 1) {
            return 1;
        }
        return value;
    };

    const handleClick = (event) => {
        try {
            const page = window.location.pathname || '/';
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const scrollY = window.scrollY || window.pageYOffset || 0;
            const body = document.body;
            const html = document.documentElement;
            const docHeight = Math.max(
                body?.scrollHeight ?? 0,
                body?.offsetHeight ?? 0,
                html?.clientHeight ?? 0,
                html?.scrollHeight ?? 0,
                html?.offsetHeight ?? 0
            );

            const absoluteY = scrollY + event.clientY;
            const normalizedX = normalizeCoordinate(event.clientX / viewportWidth);
            const normalizedY = normalizeCoordinate(docHeight > 0 ? absoluteY / docHeight : event.clientY / viewportHeight);

            const payload = {
                page,
                x: normalizedX,
                y: normalizedY,
                viewportW: viewportWidth,
                viewportH: viewportHeight,
                scrollY,
                scrollHeight: docHeight,
                timestamp: Date.now(),
            };

            sendClick(payload);
        } catch (err) {
            // Ignora errores inesperados en el tracker.
            console.debug('Heatmap tracker error', err);
        }
    };

    window.addEventListener('click', handleClick, { capture: true });
})();
