(function () {
  'use strict';

  function setStatus(panel, message) {
    var status = panel.querySelector('.ncli-panel__status');
    if (status) {
      status.textContent = message;
    }
  }

  function addTurn(panel, question, answer, evidence) {
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
    if (Array.isArray(evidence) && evidence.length) {
      var list = document.createElement('ul');
      list.className = 'ncli-panel__evidence';
      evidence.forEach(function (item) {
        if (!item || typeof item.title !== 'string') {
          return;
        }
        var listItem = document.createElement('li');
        if (typeof item.url === 'string') {
          var link = document.createElement('a');
          link.href = item.url;
          link.textContent = item.title;
          link.rel = 'noopener noreferrer';
          listItem.appendChild(link);
        } else {
          listItem.textContent = item.title;
        }
        list.appendChild(listItem);
      });
      turn.appendChild(list);
    }
    transcript.appendChild(turn);
  }

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
      addTurn(panel, question, payload.answer, payload.evidence);
      textarea.value = '';
      setStatus(panel, 'Verified answer added to this session.');
    }).catch(function () {
      setStatus(panel, 'This interactive service is temporarily unavailable.');
    }).finally(function () {
      textarea.disabled = false;
      if (button) {
        button.disabled = false;
      }
      textarea.focus();
    });
  });
}());
