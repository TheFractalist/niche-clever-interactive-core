import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../assets/js/public.js', import.meta.url), 'utf8');
const failures = [];
const assert = (condition, message) => {
  if (!condition) failures.push(message);
};

assert(source.includes("window.fetch(route"), 'The public panel must call only the configured Core route.');
assert(source.includes("credentials: 'same-origin'"), 'The public panel must keep requests same-origin.');
assert(source.includes('textContent'), 'The transcript must use text nodes for public values.');
assert(!source.includes('innerHTML'), 'The transcript must not insert public values as HTML.');
assert(source.includes('function renderAnswerMarkdown'), 'The public panel must provide its bounded answer formatter.');
assert(source.includes('answerElement.appendChild(renderAnswerMarkdown(answer))'), 'Answers must be rendered through the bounded formatter.');
assert(source.includes("document.createElement('strong')") && source.includes("document.createElement('em')"), 'The formatter must support basic emphasis without parsing HTML.');
assert(source.includes("document.createElement(ordered ? 'ol' : 'ul')") && source.includes('function parseList'), 'The formatter must support ordered, unordered and nested Markdown lists.');
assert((source.match(/document\.createElement\('a'\)/g) || []).length === 1 && source.includes('function renderApprovedLinks'), 'The only anchor the panel creates is an owner-approved link, built in one function.');
assert(source.includes('renderApprovedLinks(links)') && source.includes('payload.links') && !source.includes('payload.citations'), 'Only the links array of the response is rendered, never citations.');
assert(source.includes("/^https:\\/\\/[^\\s\"<>]+$/i") && source.includes("anchor.rel = 'noopener noreferrer'") && source.includes('anchor.textContent = link.title'), 'Links must be https only, open with noopener, and carry their title as text.');
assert(!/localStorage|sessionStorage|document\.cookie/.test(source), 'The public panel must not persist a transcript or identifier.');
assert(source.includes("textarea.focus()"), 'The public panel must restore keyboard focus after a request.');
assert(source.includes('clearTranscript'), 'The public panel must provide a session-only transcript reset.');
assert(!source.includes('payload.evidence'), 'The public panel must not render evidence-map metadata or citation links.');
assert(source.includes("panel.setAttribute('aria-busy', 'true')"), 'The public panel must expose loading state to assistive technology.');
assert(source.includes("panel.removeAttribute('aria-busy')"), 'The public panel must clear loading state after a request.');

assert(source.includes('function addPendingTurn') && source.includes('function completeTurn') && source.includes('function discardTurn'), 'Each question must appear at once and its answer must fill a separate block.');
assert(source.indexOf('addPendingTurn(panel, question)') < source.indexOf('window.fetch(route'), 'The question must be shown before the request is sent.');
assert(source.includes("ncli-panel__answer ncli-panel__answer--pending"), 'A waiting answer must be marked as pending.');
assert(source.includes('function processingMessage') && source.includes("data-ncli-processing-message") && source.includes("text('findingAnswer', 'Receiving response…')"), 'A panel-specific processing message must fall back to the safe Core wording.');
assert(source.includes('textarea.value = question') && source.includes('failTurn'), 'A failed question must leave no empty entry and return its text to the input.');
assert(source.includes('prefers-reduced-motion') && source.includes('scrollIntoView'), 'The newest turn must be brought into view, respecting reduced motion.');
assert(source.includes("hiddenLabel(text('questionLabel'") && source.includes("hiddenLabel(text('answerLabel'"), 'Question and answer blocks must carry hidden labels for assistive technology.');
assert(source.includes('isConnected'), 'A reset during a request must not resurrect a cleared turn.');


// Behavioural check of the link renderer: extract the function and run it against a minimal DOM.
{
  const start = source.indexOf('function renderApprovedLinks');
  const end = source.indexOf('function completeTurn');
  const makeElement = (tag) => ({ tag, children: [], className: '', textContent: '', appendChild(child) { this.children.push(child); this.firstChild = this.children[0]; return child; }, firstChild: null });
  const factory = new Function('document', 'text', source.slice(start, end) + '; return renderApprovedLinks;');
  const render = factory({ createElement: makeElement }, (key, fallback) => fallback);
  const anchors = (node) => (node ? node.children[1].children.map((item) => item.children[0]) : []);
  const good = render([{ title: 'Annual report', url: 'https://example.com/report?a=1' }]);
  assert(good && anchors(good).length === 1 && anchors(good)[0].href === 'https://example.com/report?a=1' && anchors(good)[0].textContent === 'Annual report' && anchors(good)[0].rel === 'noopener noreferrer' && anchors(good)[0].target === '_blank', 'A valid approved link is rendered as a safe anchor with its title as text.');
  assert(render(undefined) === null && render([]) === null && render('x') === null && render({ title: 'a', url: 'https://example.com' }) === null, 'Missing or malformed link lists render nothing.');
  const bad = [
    { title: 'js', url: 'javascript:alert(1)' }, { title: 'data', url: 'data:text/html,hi' }, { title: 'http', url: 'http://example.com/' },
    { title: 'space', url: 'https://example.com/a b' }, { title: 'quote', url: 'https://example.com/"x' }, { title: '', url: 'https://example.com/' },
    { title: 'x'.repeat(161), url: 'https://example.com/' }, { title: 5, url: 'https://example.com/' }, { title: 'long', url: 'https://example.com/' + 'a'.repeat(2000) }, null,
  ];
  assert(render(bad) === null, 'Unsafe schemes, spaces, quotes, empty or oversized values and non-objects are all skipped.');
  const many = render(Array.from({ length: 9 }, (_, i) => ({ title: 'Link ' + i, url: 'https://example.com/' + i })));
  assert(anchors(many).length === 5, 'At most five links are shown.');
  const mixed = render([{ title: '<img src=x onerror=1>', url: 'https://example.com/ok' }, { title: 'bad', url: 'ftp://example.com' }]);
  assert(anchors(mixed).length === 1 && anchors(mixed)[0].textContent === '<img src=x onerror=1>', 'A markup-looking title is kept as inert text and the unsafe sibling is dropped.');
}


// Behavioural check of the answer formatter against a minimal DOM that serialises to HTML.
{
  const esc = (v) => String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  const make = (tag) => ({ tag, children: [], attrs: {}, className: '', appendChild(c) { this.children.push(c); return c; }, setAttribute(k, v) { this.attrs[k] = v; },
    set textContent(v) { this.children = [{ text: String(v) }]; },
    html() { const a = (this.className ? ' class="' + this.className + '"' : '') + Object.entries(this.attrs).map(([k, v]) => ' ' + k + '="' + v + '"').join(''); return '<' + this.tag + a + '>' + this.children.map((c) => (c.text !== undefined ? esc(c.text) : c.html())).join('') + '</' + this.tag + '>'; } });
  const doc = { createElement: make, createTextNode: (t) => ({ text: t }), createDocumentFragment: () => make('#') };
  const start = source.indexOf('function appendInlineText');
  const end = source.indexOf('function hiddenLabel');
  const render = new Function('document', source.slice(start, end) + '; return renderAnswerMarkdown;')(doc);
  const html = (md) => render(md).children.map((c) => c.html()).join('');

  assert(html('1. One\n2. Two\n3. Three') === '<ol><li>One</li><li>Two</li><li>Three</li></ol>', 'A plain numbered list stays one ordered list.');
  assert(html('1. One\n\n2. Two\n\n3. Three') === '<ol><li>One</li><li>Two</li><li>Three</li></ol>', 'Blank lines between numbered items must not restart the numbering.');
  assert(html('1. One\n   - a\n   - b\n2. Two\n   - c\n3. Three') === '<ol><li>One<ul><li>a</li><li>b</li></ul></li><li>Two<ul><li>c</li></ul></li><li>Three</li></ol>', 'Bullets nested under numbered items stay inside one ordered list.');
  assert(html('- a\n  1. x\n  2. y\n- b') === '<ul><li>a<ol><li>x</li><li>y</li></ol></li><li>b</li></ul>', 'Numbered items nested under a bullet render as a nested ordered list.');
  assert(html('3. Three\n4. Four') === '<ol start="3"><li>Three</li><li>Four</li></ol>', 'A list that starts above 1 keeps its first number.');
  assert(html('- a\n- b\n\n1. x') === '<ul><li>a</li><li>b</li></ul><ol><li>x</li></ol>', 'A bulleted list followed by a numbered list stays two lists.');
  assert(html('1) One\n2) Two') === '<ol><li>One</li><li>Two</li></ol>', 'Parenthesis-numbered lists are ordered lists.');
  assert(html('- a\n  continued text\n- b') === '<ul><li>a continued text</li><li>b</li></ul>', 'An indented continuation line joins its item.');
  assert(html('Intro\n\n- a\n\nOutro') === '<p>Intro</p><ul><li>a</li></ul><p>Outro</p>', 'A list ends at the next paragraph.');
  assert(html('**Early life**') === '<p class="ncli-panel__answer-label"><strong>Early life</strong></p>', 'A line that is only a bold phrase is marked as a section label.');
  assert(html('**Bold** then text') === '<p><strong>Bold</strong> then text</p>', 'Inline bold in a sentence is not a label.');
  assert(html('<script>alert(1)</script>') === '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', 'Markup in an answer stays inert text.');
  const deep = html('- 1\n  - 2\n    - 3\n      - 4\n        - 5\n          - 6');
  assert((deep.match(/<ul>/g) || []).length <= 5 && deep.includes('<li>6</li>'), 'Very deep nesting is capped and loses no text.');
}


// Behavioural check of the event-stream reader: events split across chunks, CRLF, and a missing final event.
{
  const start = source.indexOf('function readEventStream');
  const end = source.indexOf('function showStreamedText');
  const read = new Function('window', source.slice(start, end) + '; return readEventStream;')({ TextDecoder });
  const respond = (text, size) => {
    const bytes = new TextEncoder().encode(text);
    let at = 0;
    return { body: { getReader: () => ({ cancel() {}, read: async () => (at >= bytes.length ? { done: true } : { done: false, value: bytes.slice(at, (at += size)) }) }) } };
  };
  const events = 'data: {"type":"delta","text":"Hel"}\n\ndata: {"type":"delta","text":"lo \u00e9"}\n\ndata: {"type":"done","ok":true,"answer":"Hello \u00e9."}\n\n';
  for (const size of [1, 3, 11, 1000]) {
    const seen = [];
    const done = await read(respond(events, size), (t) => seen.push(t));
    assert(seen.join('') === 'Hello \u00e9' && done && done.type === 'done' && done.answer === 'Hello \u00e9.', 'Stream events split at ' + size + ' bytes (including multi-byte text) are reassembled exactly.');
  }
  const crlf = await read(respond(events.replace(/\n/g, '\r\n'), 4), () => {});
  assert(crlf && crlf.type === 'done', 'CRLF-framed events are read.');
  const errored = await read(respond('data: {"type":"delta","text":"a"}\n\ndata: {"type":"error","ok":false,"error":{"code":"provider_failure"}}\n\n', 5), () => {});
  assert(errored && errored.type === 'error' && errored.ok === false, 'A final error event is returned.');
  const none = await read(respond('data: {"type":"delta","text":"cut off"}\n\n', 7), () => {});
  assert(none === null, 'A stream that ends without a final event returns null, which the panel treats as a failure.');
  const unfinished = await read(respond('data: {"type":"done","ok":true,"answer":"No blank line"}', 6), () => {});
  assert(unfinished && unfinished.answer === 'No blank line', 'A final event without a trailing blank line is still read.');
  assert(source.includes("progressiveStreaming !== false") && source.includes("'Accept': canStream ? 'text/event-stream, application/json' : 'application/json'") && source.includes("indexOf('text/event-stream')"), 'The panel asks for a stream only when its owner keeps progressive delivery enabled and falls back to JSON.');
  assert(source.includes('setTimeout(function () {') && !/requestAnimationFrame/.test(source), 'Progressive rendering uses timers, not animation frames that a background tab can suspend.');
}


// Behavioural check of the documented page events, run against a minimal DOM.
{
  const handlers = {};
  const documentStub = { addEventListener(type, fn) { handlers[type] = fn; } };
  const windowStub = { NCLI_CORE_CHAT: {}, Event: class { constructor(type, options) { this.type = type; Object.assign(this, options); } } };
  new Function('window', 'document', source)(windowStub, documentStub);
  assert(typeof handlers['ncli:ask'] === 'function' && typeof handlers['ncli:prefill'] === 'function', 'The panel must listen for ncli:ask and ncli:prefill.');
  const makePanel = (busy = false, disabled = false) => {
    const state = { submitted: 0, focused: 0 };
    const textarea = { value: '', disabled, focus() { state.focused += 1; } };
    const form = { requestSubmit() { state.submitted += 1; } };
    const panel = {
      getAttribute: (name) => (name === 'aria-busy' && busy ? 'true' : null),
      querySelector: (selector) => (selector.includes('textarea') ? textarea : selector.includes('form') ? form : null),
    };
    const target = { closest: (selector) => (selector === '.ncli-panel' ? panel : null) };
    return { state, textarea, target };
  };
  const fire = (type, target, detail) => handlers[type]({ target, detail });

  let p = makePanel();
  fire('ncli:ask', p.target, { question: '  What is NCI?  ' });
  assert(p.textarea.value === 'What is NCI?' && p.state.submitted === 1 && p.state.focused === 0, 'ncli:ask fills the question and submits the panel form.');
  p = makePanel();
  fire('ncli:prefill', p.target, { question: 'Prefilled?' });
  assert(p.textarea.value === 'Prefilled?' && p.state.submitted === 0 && p.state.focused === 1, 'ncli:prefill fills and focuses without sending.');
  for (const [label, panelState, detail] of [
    ['a busy panel', makePanel(true), { question: 'Q?' }],
    ['a disabled panel', makePanel(false, true), { question: 'Q?' }],
    ['an oversized question', makePanel(), { question: 'q'.repeat(2001) }],
    ['a blank question', makePanel(), { question: '   ' }],
    ['a non-string question', makePanel(), { question: 42 }],
    ['missing detail', makePanel(), undefined],
  ]) {
    fire('ncli:ask', panelState.target, detail);
    assert(panelState.state.submitted === 0 && panelState.textarea.value === '', 'ncli:ask does nothing for ' + label + '.');
  }
  const outside = makePanel();
  handlers['ncli:ask']({ target: { closest: () => null }, detail: { question: 'Q?' } });
  handlers['ncli:ask']({ target: null, detail: { question: 'Q?' } });
  assert(outside.state.submitted === 0, 'An event outside any panel is ignored.');
}

if (failures.length) {
  console.error(`FAIL\n- ${failures.join('\n- ')}`);
  process.exit(1);
}

console.log('PASS: C5 public JavaScript boundary checks verified');
