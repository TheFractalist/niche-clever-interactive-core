import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../assets/js/admin.js', import.meta.url), 'utf8');
const failures = [];
const assert = (condition, message) => { if (!condition) failures.push(message); };

// Minimal DOM: help toggles with notes, event dispatch with bubbling, closest/matches/querySelectorAll.
const listeners = {};
const all = [];
const make = (cls, parent) => {
  const el = { cls, parent, attrs: {}, hidden: false, id: '',
    getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; },
    setAttribute(k, v) { this.attrs[k] = String(v); },
    removeAttribute(k) { delete this.attrs[k]; },
    matches(sel) { return sel.split(',').some((s) => s.trim().replace(/^\./, '') === this.cls); },
    closest(sel) { let n = this; while (n) { if (n.matches(sel)) return n; n = n.parent; } return null; },
    contains(o) { let n = o; while (n) { if (n === this) return true; n = n.parent; } return false; } };
  all.push(el);
  return el;
};
const notes = {};
const toggles = [];
for (let i = 0; i < 3; i += 1) {
  const label = make('ncli-admin__field-label', null);
  const toggle = make('ncli-admin__help-toggle', label);
  label.querySelector = () => toggle;
  toggle.attrs['aria-controls'] = 'h' + i; toggle.attrs['aria-expanded'] = 'false';
  const note = make('ncli-admin__field-help', label); note.id = 'h' + i; note.hidden = true; notes['h' + i] = note;
  toggles.push(toggle);
}
const outside = make('somewhere-else', null);
const documentStub = {
  readyState: 'complete', activeElement: null,
  addEventListener(type, fn) { (listeners[type] = listeners[type] || []).push(fn); },
  getElementById: (id) => notes[id] || null,
  querySelectorAll: (sel) => (sel.includes('aria-expanded="true"') ? toggles.filter((t) => t.attrs['aria-expanded'] === 'true') : []),
};
const timers = [];
const windowStub = { setTimeout: (fn) => { timers.push(fn); return timers.length; }, clearTimeout() {} };
new Function('window', 'document', source)(windowStub, documentStub);
const fire = (type, target, extra = {}) => (listeners[type] || []).forEach((fn) => fn({ target, preventDefault() {}, ...extra }));
const flush = () => { while (timers.length) timers.shift()(); };
const open = () => toggles.filter((t) => t.attrs['aria-expanded'] === 'true').length;
const visible = () => Object.values(notes).filter((n) => !n.hidden).length;

fire('click', toggles[0]);
assert(open() === 1 && visible() === 1, 'A click opens one note.');
fire('click', toggles[1]);
assert(open() === 1 && visible() === 1 && toggles[1].attrs['aria-expanded'] === 'true', 'Opening a second note closes the first: never more than one open.');
fire('click', toggles[1]);
assert(open() === 0 && visible() === 0, 'A second click on the same button closes its note.');
fire('click', toggles[2]);
fire('click', outside);
assert(open() === 0 && visible() === 0, 'A click outside closes the open note.');
fire('click', toggles[0]);
fire('keydown', outside, { key: 'Escape' });
assert(open() === 0 && visible() === 0, 'Escape closes the open note wherever focus is.');
fire('click', toggles[0]);
fire('click', notes.h0);
assert(open() === 1, 'A click inside the note keeps it open so its text can be selected.');
fire('click', outside);
fire('mouseover', toggles[0]); fire('mouseover', toggles[1]);
assert(open() === 1 && visible() === 1, 'Hovering over several labels leaves only the latest note open.');
fire('mouseout', toggles[1]); flush();
assert(open() === 0 && visible() === 0, 'Moving the pointer away closes an unpinned note.');
fire('click', toggles[2]); fire('mouseout', toggles[2]); flush();
assert(open() === 1, 'A pinned note stays open when the pointer leaves.');

// The markup an extension uses for a help note must survive Core's allowlist, or every note shows at once.
const hosted = readFileSync(new URL('../src/Admin/ExtensionScreens.php', import.meta.url), 'utf8');
const allowed = (tag) => {
  const from = hosted.indexOf("'" + tag + "' => [");
  return from === -1 ? '' : hosted.slice(from, hosted.indexOf(']', from));
};
for (const attribute of ['aria-controls', 'aria-expanded', 'aria-label']) {
  assert(allowed('button').includes("'" + attribute + "'"), 'Hosted extension buttons keep ' + attribute + ', which a help toggle needs.');
}
for (const attribute of ['hidden', 'role']) {
  assert(allowed('span').includes("'" + attribute + "'"), 'Hosted extension spans keep ' + attribute + ', which a help note needs.');
}

if (failures.length) { console.error('FAIL\n- ' + failures.join('\n- ')); process.exit(1); }
console.log('PASS: admin help-note behaviour verified');
