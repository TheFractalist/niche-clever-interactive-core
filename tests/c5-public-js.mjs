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
assert(source.includes("document.createElement(unordered ? 'ul' : 'ol')"), 'The formatter must support ordered and unordered Markdown lists.');
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
