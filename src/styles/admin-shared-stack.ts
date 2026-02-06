import './main.css';
import './layouts/site-base.css';
import './components/admin-modals.css';
import logger from '../core/logger.js';

interface CSSGuardState {
  count: number;
  sources: string[];
  warned: boolean;
}

declare global {
  interface Window {
    __WF_SHARED_ADMIN_CSS?: CSSGuardState;
  }
}

if (typeof window !== 'undefined') {
  const globalKey = '__WF_SHARED_ADMIN_CSS';
  const existing: CSSGuardState = window[globalKey] || { count: 0, sources: [], warned: false };
  const source = (import.meta && import.meta.url)
    ? import.meta.url
    : 'admin-shared-stack';

  existing.count += 1;
  existing.sources.push(source);

  if (existing.count > 1 && !existing.warned) {
    try {
      logger.warn('[WF CSS Guard] Shared admin CSS stack imported multiple times.', existing.sources);
    } catch { /* Logger not available */ }
    existing.warned = true;
  }

  window[globalKey] = existing;
}
