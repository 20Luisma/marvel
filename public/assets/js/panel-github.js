/**
 * Panel GitHub helpers.
 * Añade pequeñas mejoras UX sin alterar el flujo actual del formulario.
 */
const enableButton = (button) => {
  if (!button) return;

  button.disabled = false;
  button.classList.remove('is-disabled');
  const fallbackLabel = button.dataset.originalLabel || 'Ver PRs';
  button.textContent = fallbackLabel;
};

const disableButton = (button) => {
  if (!button) return;

  if (!button.dataset.originalLabel) {
    button.dataset.originalLabel = button.textContent?.trim() ?? 'Ver PRs';
  }
  button.disabled = true;
  button.classList.add('is-disabled');
  button.textContent = 'Cargando…';
};

document.addEventListener('DOMContentLoaded', () => {
  console.log('🔥 panel-github.js cargado');

  const filterForm = document.querySelector('.panel-github__filters');
  if (!filterForm) {
    console.warn('No se encontró .panel-github__filters');
    return;
  }

  const submitButton = filterForm.querySelector('button[type="submit"]');
  const dateInputs = filterForm.querySelectorAll('input[type="date"]');
  const loader = document.getElementById('panel-github-loader');
  const loaderText = loader?.querySelector('.panel-github__loader-text');

  filterForm.addEventListener('submit', (event) => {
    event.preventDefault();

    disableButton(submitButton);

    if (loader) {
      if (loaderText) {
        loaderText.textContent = 'Consultando GitHub…';
      }
      loader.classList.remove('hidden');
      loader.style.display = 'flex';
      loader.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }

    window.requestAnimationFrame(() => {
      setTimeout(() => {
        filterForm.submit();
      }, 80);
    });
  });

  dateInputs.forEach((input) => {
    input.addEventListener('input', () => {
      if (submitButton?.disabled) {
        enableButton(submitButton);
      }
    });
  });
});
