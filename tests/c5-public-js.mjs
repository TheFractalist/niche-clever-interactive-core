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
assert(!/localStorage|sessionStorage|document\.cookie/.test(source), 'The public panel must not persist a transcript or identifier.');
assert(source.includes("textarea.focus()"), 'The public panel must restore keyboard focus after a request.');
assert(source.includes('clearTranscript'), 'The public panel must provide a session-only transcript reset.');
assert(source.includes("panel.setAttribute('aria-busy', 'true')"), 'The public panel must expose loading state to assistive technology.');
assert(source.includes("panel.removeAttribute('aria-busy')"), 'The public panel must clear loading state after a request.');

if (failures.length) {
  console.error(`FAIL\n- ${failures.join('\n- ')}`);
  process.exit(1);
}

console.log('PASS: C5 public JavaScript boundary checks verified');
