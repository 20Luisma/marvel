const btnReadme = document.getElementById('btn-readme');
const modalReadme = document.getElementById('readme-modal');
const modalContent = document.getElementById('readme-content');
const btnClose = document.getElementById('readme-close');
const READ_ME_ENDPOINT = '/readme/raw';

const showModal = () => {
  if (modalReadme) {
    modalReadme.style.display = 'block';
  }
};

const hideModal = () => {
  if (modalReadme) {
    modalReadme.style.display = 'none';
  }
};

if (btnReadme && btnReadme.tagName !== 'A') {
  btnReadme.addEventListener('click', async () => {
    if (!modalContent) {
      return;
    }

    try {
      const res = await fetch(READ_ME_ENDPOINT, { headers: { Accept: 'text/plain' } });

      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }

      const html = await res.text();
      modalContent.innerHTML = html;
      showModal();
    } catch (error) {
      modalContent.innerHTML = '<p style="color:red;">No se pudo cargar el README.</p>';
      showModal();
    }
  });
}

btnClose?.addEventListener('click', () => {
  hideModal();
});

modalReadme?.addEventListener('click', (event) => {
  if (event.target === modalReadme) {
    hideModal();
  }
});
