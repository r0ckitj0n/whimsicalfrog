/**
 * WhimsicalFrog Core – Select Auto Sort (TypeScript)
 * Opt-in utility to automatically sort <select> options.
 */

import logger from './logger.js';

export function initSelectAutoSort(): void {
    if (typeof window === 'undefined') return;
    if (window.__WF_SELECT_AUTOSORT_INSTALLED) return;
    window.__WF_SELECT_AUTOSORT_INSTALLED = true;

    try {
        const qs = new URLSearchParams(window.location.search || '');
        const disable = qs.get('wf_disable_select_autosort') === '1';
        if (disable) {
            return;
        }
    } catch { /* URL parsing failed - keep default behavior */ }

    const processed = new WeakSet<HTMLSelectElement>();

    function isPlaceholder(opt: HTMLOptionElement): boolean {
        if (!opt) return false;
        const v = (opt.getAttribute('value') || '').trim();
        if (v === '') return true;
        if (opt.disabled && !v) return true;
        if (/choose|select|pick/i.test(opt.textContent || '')) return true;
        return false;
    }

    function classify(select: HTMLSelectElement): 'numeric' | 'alpha' {
        const opts = Array.from(select.options).filter(o => !isPlaceholder(o));
        const sample = opts.slice(0, 10);
        let num = 0, alpha = 0;
        sample.forEach(o => {
            const t = (o.textContent || '').trim();
            if (/^[0-9]/.test(t)) num++; else alpha++;
        });
        return num > alpha ? 'numeric' : 'alpha';
    }

    function parseLeadingNumber(str: string): number {
        const m = String(str || '').trim().match(/^(-?\d+(?:\.\d+)?)/);
        return m ? parseFloat(m[1]) : Number.NaN;
    }

    function sortSelect(select: HTMLSelectElement, force: boolean = false): void {
        if (!select) return;
        if (!force && processed.has(select)) return;
        if (select.dataset && select.dataset.noAutoSort === '1') return;
        if (select.querySelector('optgroup')) return;

        const opts = Array.from(select.options);
        if (opts.length < 2) {
            processed.add(select);
            return;
        }

        const currentValues = new Set(Array.from(select.selectedOptions).map(o => o.value));
        const placeholders = opts.filter(o => isPlaceholder(o));
        const rest = opts.filter(o => !isPlaceholder(o));
        const mode = classify(select);

        if (mode === 'numeric') {
            rest.sort((a, b) => {
                const na = parseLeadingNumber(a.textContent || ''), nb = parseLeadingNumber(b.textContent || '');
                if (Number.isNaN(na) && Number.isNaN(nb)) return (a.textContent || '').localeCompare(b.textContent || '');
                if (Number.isNaN(na)) return 1;
                if (Number.isNaN(nb)) return -1;
                return na - nb;
            });
        } else {
            const text = (o: HTMLOptionElement) => String(o.textContent || '');
            rest.sort((a, b) => text(a).localeCompare(text(b), undefined, { numeric: true, sensitivity: 'base' }));
        }

        const frag = document.createDocumentFragment();
        placeholders.forEach(o => frag.appendChild(o));
        rest.forEach(o => frag.appendChild(o));

        select.innerHTML = '';
        select.appendChild(frag);

        Array.from(select.options).forEach(o => {
            o.selected = currentValues.has(o.value);
        });

        processed.add(select);
    }

    function runAll(root: Document | HTMLElement = document): void {
        root.querySelectorAll('select').forEach(s => sortSelect(s as HTMLSelectElement));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => runAll(), { once: true });
    } else {
        runAll();
    }

    // Briefly poll after load
    let __wfSortPollCount = 0;
    const __wfSortPollMax = 4;
    const __wfSortPoll = setInterval(() => {
        try { runAll(); } catch { /* Sort pass failed - will retry */ }
        if (++__wfSortPollCount >= __wfSortPollMax) clearInterval(__wfSortPoll);
    }, 250);

    const mo = new MutationObserver((list) => {
        list.forEach(m => {
            if (m.type === 'childList') {
                m.addedNodes.forEach(n => {
                    if (n instanceof HTMLElement) {
                        if (n.tagName === 'SELECT') {
                            sortSelect(n as HTMLSelectElement);
                        } else if (n.tagName === 'OPTION' && n.parentElement instanceof HTMLSelectElement) {
                            sortSelect(n.parentElement, true);
                        } else {
                            n.querySelectorAll('select').forEach(s => sortSelect(s as HTMLSelectElement));
                        }
                    }
                });
            }
        });
    });

    mo.observe(document.documentElement, { childList: true, subtree: true });
}

export default initSelectAutoSort;
