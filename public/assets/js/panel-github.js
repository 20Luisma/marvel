/**
 * Panel GitHub helpers.
 * Añade pequeñas mejoras UX sin alterar el flujo actual del formulario.
 */

// Helpers
const enableGithubButton = (button) => {
  if (!button) return;
  button.disabled = false;
  button.classList.remove('is-disabled');
  const fallbackLabel = button.dataset.originalLabel || 'Ver PRs';
  button.textContent = fallbackLabel;
};

const disableGithubButton = (button) => {
  if (!button) return;
  if (!button.dataset.originalLabel) {
    button.dataset.originalLabel = (button.textContent || 'Ver PRs').trim();
  }
  button.disabled = true;
  button.classList.add('is-disabled');
  button.textContent = 'Cargando…';
};

document.addEventListener('DOMContentLoaded', () => {
  console.log('🔥 JS inline del panel GitHub cargado');

  const filterForm = document.querySelector('.panel-github__filters');
  if (!filterForm) {
    console.warn('No se encontró .panel-github__filters');
    return;
  }

  const submitButton = filterForm.querySelector('button[type="submit"]');
  const dateInputs = filterForm.querySelectorAll('input[type="date"]');
  const loader = document.getElementById('panel-github-loader');
  const loaderText = loader ? loader.querySelector('.panel-loader__text') : null;

  filterForm.addEventListener('submit', (event) => {
    event.preventDefault();

    disableGithubButton(submitButton);

    if (loader) {
      if (loaderText) {
        loaderText.textContent = 'Consultando GitHub…';
      }
      loader.classList.remove('hidden');
      loader.style.display = 'inline-flex';
      loader.scrollIntoView({
        behavior: 'smooth',
        block: 'end'
      });
    }

    window.requestAnimationFrame(() => {
      setTimeout(() => {
        filterForm.submit();
      }, 80);
    });
  });

  dateInputs.forEach((input) => {
    input.addEventListener('input', () => {
      if (submitButton && submitButton.disabled) {
        enableGithubButton(submitButton);
      }
    });
  });
});
