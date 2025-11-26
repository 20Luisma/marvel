(() => {
  const form = document.getElementById('agent-chat-form');
  const input = document.getElementById('agentia-input');
  const messages = document.getElementById('agent-chat-messages');

  if (!form || !input || !messages) {
    return;
  }

  const appendUserMessage = (text) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'agent-message agent-message--user';
    wrapper.innerHTML = `<p>${text}</p>`;
    messages.appendChild(wrapper);
    messages.scrollTop = messages.scrollHeight;
  };

  const appendAgentThinking = () => {
    const wrapper = document.createElement('div');
    wrapper.className = 'agent-message agent-message--bot';
    wrapper.innerHTML = '<p>Pensando...</p>';
    messages.appendChild(wrapper);
    messages.scrollTop = messages.scrollHeight;
    return wrapper;
  };

  const updateAgentMessage = (wrapper, text) => {
    wrapper.innerHTML = `<p>${text}</p>`;
    messages.scrollTop = messages.scrollHeight;
  };

  async function sendMessage() {
    const text = input.value.trim();
    if (!text) return;

    appendUserMessage(text);
    input.value = "";

    const responseBox = appendAgentThinking();

    try {
      const res = await fetch('/api/marvel-agent.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `question=${encodeURIComponent(text)}`
      });

      const data = await res.json();

      if (data.answer) {
        updateAgentMessage(responseBox, data.answer);
      } else if (data.error) {
        updateAgentMessage(responseBox, "Error: " + data.error);
      } else {
        updateAgentMessage(responseBox, "Respuesta inesperada del agente.");
      }
    } catch (err) {
      updateAgentMessage(responseBox, "Error de red: " + err);
    }
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    sendMessage();
  });
})();
