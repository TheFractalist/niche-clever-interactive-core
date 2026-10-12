(function () {
  'use strict';

  function setStatus(panel, message) {
    var status = panel.querySelector('.ncli-panel__status');
    if (status) {
      status.textContent = message;
    }
  }

  function addTurn(panel, question, answer) {
    var transcript = panel.querySelector('.ncli-panel__transcript');
    if (!transcript) {
      return;
    }
    var turn = document.createElement('article');
    turn.className = 'ncli-panel__turn';
    var questionElement = document.createElement('p');
    questionElement.className = 'ncli-panel__question';
    questionElement.textContent = question;
    var answerElement = document.createElement('p');
    answerElement.className = 'ncli-panel__answer';
    answerElement.textContent = answer;
    turn.appendChild(questionElement);
    turn.appendChild(answerElement);
    transcript.appendChild(turn);
    var reset = panel.querySelector('.ncli-panel__reset');
    if (reset) {
      reset.hidden = false;
    }
  }

  function clearTranscript(panel) {
    var transcript = panel.querySelector('.ncli-panel__transcript');
    var reset = panel.querySelector('.ncli-panel__reset');
    if (transcript) {
      transcript.textContent = '';
    }
    if (reset) {
      reset.hidden = true;
    }
  }

  document.addEventListener('click', function (event) {
    var reset = event.target;
    if (!reset || !reset.matches('.ncli-panel__reset')) {
      return;
    }
    var panel = reset.closest('.ncli-panel');
    if (!panel) {
      return;
    }
    clearTranscript(panel);
    setStatus(panel, 'Conversation reset for this browser session.');
    var textarea = panel.querySelector('textarea[name="question"]');
    if (textarea) {
      textarea.focus();
    }
  });

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || !form.matches('[data-ncli-interactive]')) {
      return;
    }
    event.preventDefault();
    var panel = form.closest('.ncli-panel');
    var textarea = form.querySelector('textarea[name="question"]');
    var button = form.querySelector('button[type="submit"]');
    var route = window.NCLI_CORE_CHAT && window.NCLI_CORE_CHAT.route;
    if (!panel || !textarea || !route || !window.fetch) {
      if (panel) {
        setStatus(panel, 'This interactive service is unavailable.');
      }
      return;
    }
    var question = textarea.value.trim();
    if (!question) {
      setStatus(panel, 'Please enter a valid question.');
      textarea.focus();
      return;
    }
    textarea.disabled = true;
    panel.setAttribute('aria-busy', 'true');
    if (button) {
      button.disabled = true;
    }
    setStatus(panel, 'Finding a verified answer…');
    window.fetch(route, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ question: question })
    }).then(function (response) {
      return response.json();
    }).then(function (payload) {
      if (!payload || payload.ok !== true || typeof payload.answer !== 'string') {
        var message = payload && payload.error && typeof payload.error.message === 'string'
          ? payload.error.message
          : 'This interactive service is temporarily unavailable.';
        setStatus(panel, message);
        return;
      }
      addTurn(panel, question, payload.answer);
      textarea.value = '';
      setStatus(panel, 'Verified answer added to this session.');
    }).catch(function () {
      setStatus(panel, 'This interactive service is temporarily unavailable.');
    }).finally(function () {
      textarea.disabled = false;
      panel.removeAttribute('aria-busy');
      if (button) {
        button.disabled = false;
      }
      textarea.focus();
    });
  });
}());
