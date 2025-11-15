/**
 * Panel GitHub helpers.
 * Añade pequeñas mejoras UX sin alterar el flujo actual del formulario.
 */
const enableButton = (button) => {
  if (!button) {
    return;
  }
  button.disabled = false;
  button.classList.remove('is-disabled');
  const fallbackLabel = button.dataset.originalLabel || 'Actualizar';
  button.textContent = fallbackLabel;
};

const disableButton = (button) => {
  if (!button) {
    return;
  }
  if (!button.dataset.originalLabel) {
    button.dataset.originalLabel = button.textContent?.trim() ?? 'Actualizar';
  }
  button.disabled = true;
  button.classList.add('is-disabled');
  button.textContent = 'Actualizando…';
};

document.addEventListener('DOMContentLoaded', () => {
  const filterForm = document.querySelector('.panel-github__filters');
  if (!filterForm) {
    return;
  }

  const submitButton = filterForm.querySelector('button[type="submit"]');
  const dateInputs = filterForm.querySelectorAll('input[type="date"]');

  filterForm.addEventListener('submit', () => {
    disableButton(submitButton);
  });

  dateInputs.forEach((input) => {
    input.addEventListener('input', () => {
      if (submitButton?.disabled) {
        enableButton(submitButton);
      }
    });
  });
});
