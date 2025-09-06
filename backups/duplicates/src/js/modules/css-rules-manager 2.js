// CSS Rules Manager module (Vite-managed)
// Minimal UI to view and add site-level CSS rules stored server-side.
// Expands later to support scoping by page/section and priority policies.

function h(tag, attrs = {}, children = []) {
  const el = document.createElement(tag);
  Object.entries(attrs || {}).forEach(([k, v]) => {
    if (k === 'class') el.className = v;
    else if (k === 'style' && typeof v === 'object') Object.assign(el.style, v);
    else el.setAttribute(k, v);
  });
  (Array.isArray(children) ? children : [children]).forEach((c) => {
    if (c == null) return;
    if (typeof c === 'string') el.appendChild(document.createTextNode(c));
    else el.appendChild(c);
  });
  return el;
}

async function fetchRules() {
  try {
    const res = await fetch('/api/css_rules/list.php');
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json().catch(() => ({}));
    // Expect { success, rules: [ { id, selector, property, value, important, note } ] }
    return data?.rules || [];
  } catch (_) {
    return [];
  }
}

async function addRule(rule) {
  try {
    const res = await fetch('/api/css_rules/add.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(rule)
    });
    const data = await res.json().catch(() => ({}));
    return !!data?.success;
  } catch (_) { return false; }
}

function renderList(container, rules) {
  container.innerHTML = '';
  if (!rules.length) {
    container.appendChild(h('div', { class: 'text-gray-600' }, 'No rules yet.'));
    return;
  }
  const table = h('table', { class: 'wf-table' });
  table.appendChild(h('thead', {}, h('tr', {}, [
    h('th', {}, 'Selector'), h('th', {}, 'Property'), h('th', {}, 'Value'), h('th', {}, '!important'), h('th', {}, 'Note')
  ])));
  const tbody = h('tbody');
  rules.forEach(r => {
    tbody.appendChild(h('tr', {}, [
      h('td', {}, r.selector || ''),
      h('td', {}, r.property || ''),
      h('td', {}, r.value || ''),
      h('td', {}, r.important ? '✔' : ''),
      h('td', {}, r.note || ''),
    ]));
  });
  table.appendChild(tbody);
  container.appendChild(table);
}

export function init(modalEl) {
  const body = modalEl?.querySelector('.modal-body') || modalEl;
  if (!body) return;

  body.innerHTML = '';
  body.appendChild(h('div', { class: 'text-gray-700 mb-2' }, 'Add site-level CSS rules. Prefer fixing source CSS; use rules sparingly.'));

  const listWrap = h('div', { class: 'mb-4' }, 'Loading rules…');
  body.appendChild(listWrap);

  const form = h('form', { class: 'wf-form', id: 'cssRulesForm' }, [
    h('div', { class: 'grid grid-cols-1 md:grid-cols-4 gap-2' }, [
      h('input', { type: 'text', name: 'selector', placeholder: 'Selector (e.g., .btn-primary)' }),
      h('input', { type: 'text', name: 'property', placeholder: 'Property (e.g., color)' }),
      h('input', { type: 'text', name: 'value', placeholder: 'Value (e.g., #0f766e)' }),
      h('label', { class: 'flex items-center gap-2' }, [
        h('input', { type: 'checkbox', name: 'important' }), 'Important'
      ]),
    ]),
    h('input', { type: 'text', name: 'note', placeholder: 'Optional note', class: 'mt-2' }),
    h('div', { class: 'mt-3' }, [
      h('button', { type: 'submit', class: 'btn btn-primary' }, 'Add Rule'),
    ]),
  ]);
  body.appendChild(form);

  fetchRules().then((rules) => renderList(listWrap, rules));

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const fd = new FormData(form);
    const payload = {
      selector: (fd.get('selector') || '').toString().trim(),
      property: (fd.get('property') || '').toString().trim(),
      value: (fd.get('value') || '').toString().trim(),
      important: !!fd.get('important'),
      note: (fd.get('note') || '').toString().trim(),
    };
    if (!payload.selector || !payload.property) {
      if (typeof window.showNotification === 'function') {
        window.showNotification({ type: 'warning', title: 'Missing fields', message: 'Selector and property are required.' });
      }
      return;
    }
    const btn = form.querySelector('button[type="submit"]');
    const orig = btn?.textContent;
    if (btn) { btn.disabled = true; btn.textContent = 'Adding…'; }
    const ok = await addRule(payload);
    if (btn) { btn.disabled = false; btn.textContent = orig; }
    if (ok) {
      if (typeof window.showNotification === 'function') {
        window.showNotification({ type: 'success', title: 'Rule added', message: 'CSS rule saved.' });
      }
      form.reset();
      fetchRules().then((rules) => renderList(listWrap, rules));
    } else {
      if (typeof window.showNotification === 'function') {
        window.showNotification({ type: 'error', title: 'Failed', message: 'Could not save rule.' });
      }
    }
  });
}

export default { init };
