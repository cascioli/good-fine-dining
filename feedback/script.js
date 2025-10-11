/**
 * Gestione interattiva della pagina feedback:
 * - mostra il modulo al click sul bottone dedicato;
 * - valida i dati lato client;
 * - invia la richiesta al backend via fetch e gestisce le risposte.
 */
document.documentElement.classList.remove('no-js');

(function () {
  const toggleButton = document.querySelector('[data-action="open-form"]');
  const formSection = document.querySelector('[data-feedback-form]');
  const form = formSection ? formSection.querySelector('form') : null;
  const messageNode = document.querySelector('[data-feedback-message]');
  const submitButton = form ? form.querySelector('.feedback-form__submit') : null;
  const nameInput = form ? form.querySelector('#feedback-name') : null;
  const emailInput = form ? form.querySelector('#feedback-email') : null;
  const messageInput = form ? form.querySelector('#feedback-message') : null;
  const honeypotInput = form ? form.querySelector('#feedback-website') : null;

  if (!toggleButton || !formSection || !form || !messageNode || !submitButton || !messageInput || !honeypotInput) {
    return;
  }

  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i;

  const resetMessage = () => {
    messageNode.textContent = '';
    messageNode.setAttribute('hidden', 'hidden');
    messageNode.removeAttribute('data-state');
  };

  const showMessage = (type, text) => {
    messageNode.textContent = text;
    messageNode.dataset.state = type;
    messageNode.removeAttribute('hidden');
  };

  const setLoading = (isLoading) => {
    if (isLoading) {
      submitButton.dataset.loading = 'true';
      submitButton.disabled = true;
    } else {
      submitButton.disabled = false;
      delete submitButton.dataset.loading;
    }
  };

  const openForm = () => {
    if (formSection.getAttribute('aria-hidden') === 'false') {
      return;
    }

    formSection.setAttribute('aria-hidden', 'false');
    formSection.classList.add('feedback-form--visible');
    toggleButton.setAttribute('aria-expanded', 'true');
    resetMessage();

    window.requestAnimationFrame(() => {
      if (nameInput && nameInput.value.trim() === '') {
        nameInput.focus();
      } else {
        messageInput.focus();
      }
    });
  };

  toggleButton.addEventListener('click', openForm);

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    resetMessage();

    const nameValue = nameInput ? nameInput.value.trim() : '';
    const emailValue = emailInput ? emailInput.value.trim() : '';
    const messageValue = messageInput.value.trim();
    const honeypotValue = honeypotInput.value.trim();

    if (honeypotValue !== '') {
      showMessage('error', 'Si è verificato un errore di convalida. Riprova più tardi.');
      return;
    }

    if (messageValue.length === 0) {
      showMessage('error', 'Il messaggio è obbligatorio.');
      messageInput.focus();
      return;
    }

    if (messageValue.length > 1000) {
      showMessage('error', 'Il messaggio non può superare 1000 caratteri.');
      messageInput.focus();
      return;
    }

    if (nameValue.length > 120) {
      showMessage('error', 'Il nome può contenere al massimo 120 caratteri.');
      nameInput.focus();
      return;
    }

    if (emailValue.length > 160) {
      showMessage('error', "L'email può contenere al massimo 160 caratteri.");
      emailInput.focus();
      return;
    }

    if (emailValue !== '' && !emailPattern.test(emailValue)) {
      showMessage('error', 'Inserisci un indirizzo email valido oppure lascia il campo vuoto.');
      emailInput.focus();
      return;
    }

    try {
      setLoading(true);

      const formData = new FormData(form);
      const response = await fetch('send_feedback.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });

      let payload = null;
      try {
        payload = await response.json();
      } catch (error) {
        // Risposta non JSON: verrà gestita come errore generico.
      }

      if (!response.ok || !payload || payload.success !== true) {
        const errorMessage =
          payload && typeof payload.error === 'string'
            ? payload.error
            : 'Impossibile inviare il feedback in questo momento. Riprova più tardi.';
        showMessage('error', errorMessage);
        return;
      }

      form.reset();
      showMessage('success', 'Grazie! Il tuo feedback è stato inviato con successo.');
    } catch (error) {
      showMessage('error', 'Si è verificato un errore inatteso. Riprova tra qualche minuto.');
    } finally {
      setLoading(false);
    }
  });
})();
