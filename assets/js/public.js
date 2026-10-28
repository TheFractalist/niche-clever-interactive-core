(function () {
  'use strict';

  function text(key, fallback) {
    var strings = window.NCLI_CORE_CHAT && window.NCLI_CORE_CHAT.strings;
    return strings && typeof strings[key] === 'string' ? strings[key] : fallback;
  }

  function processingMessage(panel) {
    var message = panel && panel.getAttribute ? panel.getAttribute('data-ncli-processing-message') : '';
    return typeof message === 'string' && message.trim() ? message : text('findingAnswer', 'Receiving response…');
  }

  // A quiet message is still announced to assistive technology but is not shown on screen,
  // so a normal answer does not leave a line of status text under the thread.
  function setStatus(panel, message, quiet) {
    var status = panel.querySelector('.ncli-panel__status');
    if (status) {
      status.textContent = message;
      if (status.classList) {
        status.classList.toggle('screen-reader-text', quiet === true);
      }
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

  // One list item: indentation, a bullet or a number with "." or ")", then the item text.
  var LIST_ITEM = /^(\s*)([-*+]|\d{1,9}[.)])\s+(.*)$/;
  var MAX_LIST_DEPTH = 4;

  function indentWidth(value) {
    return value.replace(/\t/g, '    ').length;
  }

  // Builds one list, with any more deeply indented items nested inside the item they follow.
  // Items of one list share a kind; blank lines between items do not start a new list, so a
  // numbered list keeps counting. Anything not an item (or a different kind at the same level)
  // ends the list. Nesting stops at MAX_LIST_DEPTH, where deeper items stay in the current list.
  function parseList(lines, start, depth) {
    var first = LIST_ITEM.exec(lines[start]);
    var base = indentWidth(first[1]);
    var ordered = /\d/.test(first[2].charAt(0));
    var list = document.createElement(ordered ? 'ol' : 'ul');
    if (ordered) {
      var startNumber = parseInt(first[2], 10);
      if (startNumber > 1 && startNumber < 1000000) {
        list.setAttribute('start', String(startNumber));
      }
    }
    var index = start;
    var item = null;

    while (index < lines.length) {
      var line = lines[index];
      if (/^\s*$/.test(line)) {
        var ahead = index;
        while (ahead < lines.length && /^\s*$/.test(lines[ahead])) {
          ahead += 1;
        }
        var upcoming = ahead < lines.length ? LIST_ITEM.exec(lines[ahead]) : null;
        if (!upcoming || indentWidth(upcoming[1]) < base) {
          break;
        }
        index = ahead;
        continue;
      }

      var match = LIST_ITEM.exec(line);
      if (!match) {
        // An indented continuation line belongs to the current item; anything else ends the list.
        if (item && indentWidth(/^\s*/.exec(line)[0]) > base && !isBlockStart(line)) {
          item.appendChild(document.createTextNode(' '));
          appendInlineText(item, line.trim());
          index += 1;
          continue;
        }
        break;
      }

      var width = indentWidth(match[1]);
      if (width < base) {
        break;
      }
      if (width >= base + 2 && item && depth < MAX_LIST_DEPTH) {
        var nested = parseList(lines, index, depth + 1);
        item.appendChild(nested.list);
        index = nested.next;
        continue;
      }
      if (/\d/.test(match[2].charAt(0)) !== ordered) {
        break;
      }
      item = document.createElement('li');
      appendInlineText(item, match[3]);
      list.appendChild(item);
      index += 1;
    }

    return { list: list, next: index };
  }

  // A line that is only a bold phrase is a section label the author meant as a heading.
  function isLabelLine(value) {
    return /^\s*(\*\*|__)[^*_\n]{1,120}\1\s*:?\s*$/.test(value);
  }

  function isBlockStart(line) {
    return /^#{1,6}\s+/.test(line)
      || /^```/.test(line)
      || LIST_ITEM.test(line)
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

      if (LIST_ITEM.test(line)) {
        var parsed = parseList(lines, index, 0);
        fragment.appendChild(parsed.list);
        index = parsed.next;
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
      if (paragraphLines.length === 1 && isLabelLine(paragraphLines[0])) {
        paragraph.className = 'ncli-panel__answer-label';
      }
      appendInlineText(paragraph, paragraphLines.join(' '));
      fragment.appendChild(paragraph);
    }

    return fragment;
  }

  function hiddenLabel(value) {
    var label = document.createElement('span');
    label.className = 'screen-reader-text';
    label.textContent = value + ' ';
    return label;
  }

  function scrollIntoViewGently(element) {
    if (!element || typeof element.scrollIntoView !== 'function') {
      return;
    }
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    element.scrollIntoView({ block: 'nearest', behavior: reduced ? 'auto' : 'smooth' });
  }

  // The thread reads top to bottom: each question appears at once in its own shaded block and
  // its answer arrives in a separate bordered block beneath it. The input stays below the thread.
  function addPendingTurn(panel, question) {
    var transcript = panel.querySelector('.ncli-panel__transcript');
    if (!transcript) {
      return null;
    }
    var turn = document.createElement('article');
    turn.className = 'ncli-panel__turn';
    var questionElement = document.createElement('p');
    questionElement.className = 'ncli-panel__question';
    questionElement.appendChild(hiddenLabel(text('questionLabel', 'Question:')));
    questionElement.appendChild(document.createTextNode(question));
    var answerElement = document.createElement('div');
    answerElement.className = 'ncli-panel__answer ncli-panel__answer--pending';
    answerElement.setAttribute('role', 'status');
    var working = document.createElement('span');
    working.className = 'ncli-panel__working';
    var dots = document.createElement('span');
    dots.className = 'ncli-panel__working-dots';
    dots.setAttribute('aria-hidden', 'true');
    for (var dot = 0; dot < 3; dot += 1) {
      dots.appendChild(document.createElement('span'));
    }
    var workingLabel = document.createElement('span');
    workingLabel.textContent = processingMessage(panel);
    working.appendChild(dots);
    working.appendChild(workingLabel);
    answerElement.appendChild(working);
    turn.appendChild(questionElement);
    turn.appendChild(answerElement);
    transcript.appendChild(turn);
    var reset = panel.querySelector('.ncli-panel__reset');
    if (reset) {
      reset.hidden = false;
    }
    scrollIntoViewGently(turn);
    return { turn: turn, answer: answerElement };
  }

  // Owner-approved links only. Each one must be a plain https address and a short title; anything
  // else is skipped. Titles are set as text, never parsed, and links open without referrer access.
  function renderApprovedLinks(links) {
    if (!Array.isArray(links) || links.length === 0) {
      return null;
    }
    var list = document.createElement('ul');
    list.className = 'ncli-panel__sources-list';
    links.slice(0, 5).forEach(function (link) {
      if (!link || typeof link.title !== 'string' || typeof link.url !== 'string') {
        return;
      }
      if (link.title.length === 0 || link.title.length > 160 || link.url.length > 2000 || !/^https:\/\/[^\s"<>]+$/i.test(link.url)) {
        return;
      }
      var item = document.createElement('li');
      var anchor = document.createElement('a');
      anchor.href = link.url;
      anchor.target = '_blank';
      anchor.rel = 'noopener noreferrer';
      anchor.textContent = link.title;
      item.appendChild(anchor);
      list.appendChild(item);
    });
    if (!list.firstChild) {
      return null;
    }
    var wrapper = document.createElement('div');
    wrapper.className = 'ncli-panel__sources';
    var heading = document.createElement('p');
    heading.className = 'ncli-panel__sources-title';
    heading.textContent = text('sourcesLabel', 'Sources');
    wrapper.appendChild(heading);
    wrapper.appendChild(list);
    return wrapper;
  }

  // Reused answers carry a quiet note only when an extension supplied its wording.
  function renderCachedNote(cached) {
    var note = text('cachedNote', '');
    if (cached !== true || !note) {
      return null;
    }
    var element = document.createElement('p');
    element.className = 'ncli-panel__cached-note';
    element.textContent = note;
    return element;
  }

  // Reads the route's server-sent events. `delta` events carry display text; the single final `done`
  // or `error` event carries the complete validated answer or the public error and is the only thing
  // that is ever kept. Resolves with that final event, or null if the stream ended without one.
  function readEventStream(response, onDelta) {
    var reader = response.body.getReader();
    var decoder = new window.TextDecoder('utf-8');
    var buffer = '';
    var finalEvent = null;

    function handleBlock(block) {
      var data = '';
      block.split('\n').forEach(function (line) {
        if (line.indexOf('data:') === 0) {
          data += line.slice(5).replace(/^ /, '');
        }
      });
      if (!data) {
        return;
      }
      var event;
      try {
        event = JSON.parse(data);
      } catch (error) {
        return;
      }
      if (event && event.type === 'delta' && typeof event.text === 'string') {
        onDelta(event.text, reader);
      } else if (event && (event.type === 'done' || event.type === 'error')) {
        finalEvent = event;
      }
    }

    function pump() {
      return reader.read().then(function (result) {
        if (result.done) {
          buffer += decoder.decode();
          if (buffer.trim()) {
            handleBlock(buffer);
          }
          return finalEvent;
        }
        buffer += decoder.decode(result.value, { stream: true }).replace(/\r\n/g, '\n');
        var end;
        while ((end = buffer.indexOf('\n\n')) !== -1) {
          handleBlock(buffer.slice(0, end));
          buffer = buffer.slice(end + 2);
        }
        return pump();
      });
    }

    return pump();
  }

  // Shows the text received so far. It is re-rendered through the same safe formatter as the final
  // answer, on a short timer (not animation frames, which a background tab can suspend). Nothing is
  // announced to assistive technology until the answer is complete.
  function showStreamedText(pending, received) {
    var answerElement = pending.answer;
    if (pending.streaming !== true) {
      pending.streaming = true;
      answerElement.className = 'ncli-panel__answer ncli-panel__answer--streaming';
      answerElement.removeAttribute('role');
      scrollIntoViewGently(pending.turn);
    }
    answerElement.textContent = '';
    answerElement.appendChild(hiddenLabel(text('answerLabel', 'Answer:')));
    answerElement.appendChild(renderAnswerMarkdown(received.trim()));
    var caret = document.createElement('span');
    caret.className = 'ncli-panel__caret';
    caret.setAttribute('aria-hidden', 'true');
    answerElement.appendChild(caret);
  }

  function queueStreamedText(pending, received) {
    pending.received = received;
    if (pending.renderTimer) {
      return;
    }
    pending.renderTimer = window.setTimeout(function () {
      pending.renderTimer = 0;
      if (pending.turn.isConnected) {
        showStreamedText(pending, pending.received);
      }
    }, 70);
  }

  function completeTurn(pending, answer, links, cached) {
    var answerElement = pending.answer;
    if (pending.renderTimer) {
      window.clearTimeout(pending.renderTimer);
      pending.renderTimer = 0;
    }
    answerElement.className = 'ncli-panel__answer';
    answerElement.removeAttribute('role');
    answerElement.textContent = '';
    answerElement.appendChild(hiddenLabel(text('answerLabel', 'Answer:')));
    answerElement.appendChild(renderAnswerMarkdown(answer));
    var sources = renderApprovedLinks(links);
    if (sources) {
      answerElement.appendChild(sources);
    }
    var cachedNote = renderCachedNote(cached);
    if (cachedNote) {
      answerElement.appendChild(cachedNote);
    }
    scrollIntoViewGently(pending.turn);
  }

  function discardTurn(pending) {
    if (pending && pending.turn && pending.turn.parentNode) {
      pending.turn.parentNode.removeChild(pending.turn);
    }
  }

  // An opaque, in-memory identifier that lets the optional server-side history group
  // the turns of one page session. It is never written to browser storage.
  function conversationId(panel) {
    var existing = panel.getAttribute('data-ncli-conversation');
    if (existing) {
      return existing;
    }
    if (!window.crypto || !window.crypto.getRandomValues) {
      return '';
    }
    var bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    var id = Array.prototype.map.call(bytes, function (byte) {
      return ('0' + byte.toString(16)).slice(-2);
    }).join('');
    panel.setAttribute('data-ncli-conversation', id);
    return id;
  }

  // Core never reads or writes a cookie or browser storage. An installed extension may set
  // `window.NCLI_CORE_CHAT.visitorId` to a function returning its own 32-character hexadecimal
  // identifier; anything else is ignored and no identifier is sent.
  function visitorId() {
    var provider = window.NCLI_CORE_CHAT && window.NCLI_CORE_CHAT.visitorId;
    if (typeof provider !== 'function') {
      return '';
    }
    try {
      var value = provider();
      return typeof value === 'string' && /^[a-f0-9]{32}$/.test(value) ? value : '';
    } catch (error) {
      return '';
    }
  }

  function conversationPayload(question, id, profile) {
    var body = id ? { question: question, conversation_id: id } : { question: question };
    var visitor = visitorId();
    if (visitor) {
      body.visitor_id = visitor;
    }
    if (profile && profile !== 'default') {
      body.profile_id = profile;
    }
    return body;
  }

  function clearTranscript(panel) {
    panel.removeAttribute('data-ncli-conversation');
    var transcript = panel.querySelector('.ncli-panel__transcript');
    var reset = panel.querySelector('.ncli-panel__reset');
    if (transcript) {
      transcript.textContent = '';
    }
    if (reset) {
      reset.hidden = true;
    }
  }

  function hasTurns(panel) {
    return !!panel.querySelector('.ncli-panel__turn');
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
    setStatus(panel, text('conversationReset', 'Conversation reset for this browser session.'));
    var textarea = panel.querySelector('textarea[name="question"]');
    if (textarea) {
      textarea.focus();
    }
  });

  // Documented page events (docs/ARCHITECTURE.md, "Panel events"). Another script may dispatch
  // `ncli:ask` (fill and send) or `ncli:prefill` (fill and focus only) on an element inside a panel
  // with detail.question. Both do only what a visitor could do by typing: the text still goes through
  // the panel's own form, route, limits and validation, and nothing happens while the panel is busy,
  // disabled or unavailable.
  function externalQuestion(event, send) {
    var target = event.target;
    var panel = target && target.closest ? target.closest('.ncli-panel') : null;
    var detail = event.detail;
    if (!panel || !detail || typeof detail.question !== 'string') {
      return;
    }
    var question = detail.question.trim();
    if (!question || question.length > 2000) {
      return;
    }
    var form = panel.querySelector('form[data-ncli-interactive]');
    var textarea = panel.querySelector('textarea[name="question"]');
    if (!form || !textarea || textarea.disabled || panel.getAttribute('aria-busy') === 'true') {
      return;
    }
    textarea.value = question;
    if (!send) {
      textarea.focus();
      return;
    }
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit();
    } else {
      form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
    }
  }

  document.addEventListener('ncli:ask', function (event) {
    externalQuestion(event, true);
  });
  document.addEventListener('ncli:prefill', function (event) {
    externalQuestion(event, false);
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
        setStatus(panel, text('unavailable', 'This interactive service is unavailable.'));
      }
      return;
    }
    var question = textarea.value.trim();
    if (!question) {
      setStatus(panel, text('invalidQuestion', 'Please enter a valid question.'));
      textarea.focus();
      return;
    }
    textarea.disabled = true;
    panel.setAttribute('aria-busy', 'true');
    if (button) {
      button.disabled = true;
    }
    setStatus(panel, '');
    var pending = addPendingTurn(panel, question);
    textarea.value = '';
    var profile = form.getAttribute('data-ncli-profile') || '';
    // A browser requests a stream only when the site owner has kept progressive answers enabled.
    // The route still falls back to JSON for errors, reused answers and non-streaming transports.
    var progressiveStreaming = !window.NCLI_CORE_CHAT || window.NCLI_CORE_CHAT.progressiveStreaming !== false;
    var canStream = progressiveStreaming && typeof window.TextDecoder === 'function' && typeof window.ReadableStream === 'function';
    var headers = { 'Content-Type': 'application/json', 'Accept': canStream ? 'text/event-stream, application/json' : 'application/json' };
    if (profile && window.NCLI_CORE_CHAT.nonce) {
      // Only named profiles can depend on who is signed in; the default panel never sends it.
      headers['X-WP-Nonce'] = window.NCLI_CORE_CHAT.nonce;
    }
    window.fetch(route, {
      method: 'POST',
      credentials: 'same-origin',
      headers: headers,
      body: JSON.stringify(conversationPayload(question, conversationId(panel), profile))
    }).then(function (response) {
      var type = response.headers && response.headers.get ? response.headers.get('Content-Type') || '' : '';
      if (canStream && response.body && type.indexOf('text/event-stream') !== -1) {
        var received = '';
        return readEventStream(response, function (piece, reader) {
          if (!pending || !pending.turn.isConnected) {
            // The conversation was reset while the answer was being written.
            reader.cancel();
            return;
          }
          received += piece;
          queueStreamedText(pending, received);
        });
      }
      return response.json();
    }).then(function (payload) {
      var detached = pending && !pending.turn.isConnected;
      if (!payload || payload.ok !== true || typeof payload.answer !== 'string') {
        var message = payload && payload.error && typeof payload.error.message === 'string'
          ? payload.error.message
          : text('temporaryFailure', 'This interactive service is temporarily unavailable.');
        failTurn(message);
        return;
      }
      if (pending && !detached) {
        completeTurn(pending, payload.answer, payload.links, payload.cached);
        setStatus(panel, text('answerAdded', 'Verified answer added to this session.'), true);
      }
    }).catch(function () {
      failTurn(text('temporaryFailure', 'This interactive service is temporarily unavailable.'));
    }).finally(function () {
      textarea.disabled = false;
      panel.removeAttribute('aria-busy');
      if (button) {
        button.disabled = false;
      }
      if (textarea.focus) {
        textarea.focus({ preventScroll: true });
      }
    });

    // A failed question leaves no empty thread entry and returns the text for another try.
    function failTurn(message) {
      var stillThere = pending && pending.turn.isConnected;
      discardTurn(pending);
      if (stillThere) {
        textarea.value = question;
        if (!hasTurns(panel)) {
          var resetButton = panel.querySelector('.ncli-panel__reset');
          if (resetButton) {
            resetButton.hidden = true;
          }
        }
      }
      setStatus(panel, message);
    }
  });
}());
