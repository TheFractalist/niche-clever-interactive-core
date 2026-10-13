(function () {
  'use strict';

  function setStatus(panel, message) {
    var status = panel.querySelector('.ncli-panel__status');
    if (status) {
      status.textContent = message;
    }
  }

  function appendInlineText(container, value) {
    var text = value.replace(/\[([^\]]+)\]\([^\s)]+(?:\s+[^)]*)?\)/g, '$1');
    var token = /(\*\*[^*\n]+\*\*|__[^_\n]+__|`[^`\n]+`|\*[^*\n]+\*|_[^_\n]+_)/g;
    var cursor = 0;
    var match;

    while ((match = token.exec(text)) !== null) {
      if (match.index > cursor) {
        container.appendChild(document.createTextNode(text.slice(cursor, match.index)));
      }
      var valueToken = match[0];
      var element;
      var content;
      if (valueToken.slice(0, 2) === '**' || valueToken.slice(0, 2) === '__') {
        element = document.createElement('strong');
        content = valueToken.slice(2, -2);
      } else if (valueToken.slice(0, 1) === '`') {
        element = document.createElement('code');
        content = valueToken.slice(1, -1);
      } else {
        element = document.createElement('em');
        content = valueToken.slice(1, -1);
      }
      element.textContent = content;
      container.appendChild(element);
      cursor = match.index + valueToken.length;
    }

    if (cursor < text.length) {
      container.appendChild(document.createTextNode(text.slice(cursor)));
    }
  }

  function isBlockStart(line) {
    return /^#{1,6}\s+/.test(line)
      || /^```/.test(line)
      || /^\s*[-*+]\s+/.test(line)
      || /^\s*\d+\.\s+/.test(line)
      || /^>\s?/.test(line)
      || /^\s{0,3}([-*_])(?:\s*\1){2,}\s*$/.test(line);
  }

  function renderAnswerMarkdown(answer) {
    var fragment = document.createDocumentFragment();
    var lines = answer.replace(/\r\n?/g, '\n').split('\n');
    var index = 0;

    while (index < lines.length) {
      var line = lines[index];
      if (/^\s*$/.test(line)) {
        index += 1;
        continue;
      }

      if (/^```/.test(line)) {
        var codeLines = [];
        index += 1;
        while (index < lines.length && !/^```/.test(lines[index])) {
          codeLines.push(lines[index]);
          index += 1;
        }
        if (index < lines.length) {
          index += 1;
        }
        var pre = document.createElement('pre');
        var code = document.createElement('code');
        code.textContent = codeLines.join('\n');
        pre.appendChild(code);
        fragment.appendChild(pre);
        continue;
      }

      var heading = /^(#{1,6})\s+(.+?)\s*#*\s*$/.exec(line);
      if (heading) {
        var headingLevel = Math.min(6, Math.max(3, heading[1].length + 2));
        var headingElement = document.createElement('h' + headingLevel);
        appendInlineText(headingElement, heading[2]);
        fragment.appendChild(headingElement);
        index += 1;
        continue;
      }

      if (/^\s{0,3}([-*_])(?:\s*\1){2,}\s*$/.test(line)) {
        fragment.appendChild(document.createElement('hr'));
        index += 1;
        continue;
      }

      var unordered = /^\s*[-*+]\s+(.+)$/.exec(line);
      var ordered = /^\s*\d+\.\s+(.+)$/.exec(line);
      if (unordered || ordered) {
        var list = document.createElement(unordered ? 'ul' : 'ol');
        var expression = unordered ? /^\s*[-*+]\s+(.+)$/ : /^\s*\d+\.\s+(.+)$/;
        while (index < lines.length) {
          var item = expression.exec(lines[index]);
          if (!item) {
            break;
          }
          var listItem = document.createElement('li');
          appendInlineText(listItem, item[1]);
          list.appendChild(listItem);
          index += 1;
        }
        fragment.appendChild(list);
        continue;
      }

      if (/^>\s?/.test(line)) {
        var quoteLines = [];
        while (index < lines.length && /^>\s?/.test(lines[index])) {
          quoteLines.push(lines[index].replace(/^>\s?/, ''));
          index += 1;
        }
        var quote = document.createElement('blockquote');
        var quoteParagraph = document.createElement('p');
        appendInlineText(quoteParagraph, quoteLines.join(' '));
        quote.appendChild(quoteParagraph);
        fragment.appendChild(quote);
        continue;
      }

      var paragraphLines = [line];
      index += 1;
      while (index < lines.length && !/^\s*$/.test(lines[index]) && !isBlockStart(lines[index])) {
        paragraphLines.push(lines[index]);
        index += 1;
      }
      var paragraph = document.createElement('p');
      appendInlineText(paragraph, paragraphLines.join(' '));
      fragment.appendChild(paragraph);
    }

    return fragment;
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
    var answerElement = document.createElement('div');
    answerElement.className = 'ncli-panel__answer';
    answerElement.appendChild(renderAnswerMarkdown(answer));
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
