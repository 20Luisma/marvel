const MSC = (function() {
  const TITLE_TEXT = 'Canal Microservicios';
  const SUBTITLE_TEXT = 'Web principal ↔ Microservicios Open IA';

  function getPanel() { return document.getElementById('microservice-comm-panel'); }
  function getSteps() {
    const p = getPanel();
    return p ? p.querySelectorAll('.msc-step') : [];
  }
  function getStatus() { return document.getElementById('msc-status-text'); }
  function getRetry() { return document.getElementById('msc-retry'); }
  function getHostCard() { return document.getElementById('comic-creation-card'); }

  function showPanel() {
    const p = getPanel();
    if (!p) return;
    p.classList.remove('msc-hidden');
    const title = p.querySelector('.msc-title');
    if (title) title.textContent = TITLE_TEXT;
    const subtitle = p.querySelector('.msc-subtitle');
    if (subtitle) subtitle.textContent = SUBTITLE_TEXT;
    const host = getHostCard();
    if (host) {
      host.classList.add('msc-active');
      host.classList.remove('rag-active');
    }
    clearStatus();
    setStep('send');
  }

  function hidePanel() {
    const p = getPanel();
    if (!p) return;
    p.classList.add('msc-hidden');
    const host = getHostCard();
    if (host) host.classList.remove('msc-active');
  }

  function setStep(stepName) {
    getSteps().forEach(step => {
      step.classList.toggle('msc-active', step.dataset.step === stepName);
    });
  }

  function markSuccess(msg) {
    setStep('return');
    const s = getStatus();
    if (s) {
      s.className = 'msc-status msc-success';
      s.textContent = msg || '✅ Comunicación correcta. Microservicio funcionando.';
    }
    setTimeout(hidePanel, 1600);
  }

  function markError(msg) {
    const p = getPanel();
    if (p) p.classList.remove('msc-hidden');
    const host = getHostCard();
    if (host) host.classList.add('msc-active');
    const s = getStatus();
    if (s) {
      s.className = 'msc-status msc-error';
      s.textContent = msg || '❌ Error en la comunicación con el microservicio.';
    }
    const r = getRetry();
    if (r) r.classList.remove('msc-hidden');
  }

  function clearStatus() {
    const s = getStatus();
    const r = getRetry();
    if (s) {
      s.className = 'msc-status';
      s.textContent = '';
    }
    if (r) r.classList.add('msc-hidden');
  }

  document.addEventListener('click', (ev) => {
    const r = getRetry();
    if (r && ev.target === r) {
      clearStatus();
      hidePanel();
    }
  });

  return { showPanel, hidePanel, setStep, markSuccess, markError };
})();

const RAGMSC = (function() {
  const TITLE_TEXT = 'Canal Microservicios';
  const SUBTITLE_TEXT = 'Web principal ↔ Microservicios Open IA';
  function getPanel() { return document.getElementById('rag-comm-panel'); }
  function getSteps() {
    const p = getPanel();
    return p ? p.querySelectorAll('.msc-step') : [];
  }
  function getStatus() { return document.getElementById('rag-msc-status-text'); }
  function getRetry() { return document.getElementById('rag-msc-retry'); }
  function getHostCard() { return document.getElementById('comic-creation-card'); }

  function showPanel() {
    const p = getPanel();
    if (!p) return;
    p.classList.remove('msc-hidden');
    const title = p.querySelector('.msc-title');
    if (title) title.textContent = TITLE_TEXT;
    const subtitle = p.querySelector('.msc-subtitle');
    if (subtitle) subtitle.textContent = SUBTITLE_TEXT;
    const host = getHostCard();
    if (host) {
      host.classList.add('rag-active');
      host.classList.remove('msc-active');
    }
    clearStatus();
    setStep('send');
  }

  function hidePanel() {
    const p = getPanel();
    if (!p) return;
    p.classList.add('msc-hidden');
    const host = getHostCard();
    if (host) host.classList.remove('rag-active');
  }

  function setStep(stepName) {
    getSteps().forEach(step => {
      step.classList.toggle('msc-active', step.dataset.step === stepName);
    });
  }

  function markSuccess(msg) {
    setStep('return');
    const s = getStatus();
    if (s) {
      s.className = 'msc-status msc-success';
      s.textContent = msg || '✅ RAG respondió correctamente.';
    }
    setTimeout(hidePanel, 1600);
  }

  function markError(msg) {
    const p = getPanel();
    if (p) p.classList.remove('msc-hidden');
    const host = getHostCard();
    if (host) host.classList.add('rag-active');
    const s = getStatus();
    if (s) {
      s.className = 'msc-status msc-error';
      s.textContent = msg || '❌ Error consultando servicio RAG.';
    }
    const r = getRetry();
    if (r) r.classList.remove('msc-hidden');
  }

  function clearStatus() {
    const s = getStatus();
    const r = getRetry();
    if (s) {
      s.className = 'msc-status';
      s.textContent = '';
    }
    if (r) r.classList.add('msc-hidden');
  }

  document.addEventListener('click', (ev) => {
    const r = getRetry();
    if (r && ev.target === r) {
      clearStatus();
      hidePanel();
    }
  });

  return { showPanel, hidePanel, setStep, markSuccess, markError };
})();

if (typeof window !== 'undefined') {
  window.MSC = MSC;
  window.RAGMSC = RAGMSC;
}

export { MSC, RAGMSC };
