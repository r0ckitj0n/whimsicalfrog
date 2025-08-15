// src/core/logger.js
// Minimal logger that gates debug/info logs behind WF_DEBUG or ?debug=1

const isBrowser = typeof window !== 'undefined';
const DEBUG = isBrowser && (window.WF_DEBUG === true || /(?:^|[?&])debug=1(?:&|$)/.test(window.location.search || ''));

const logger = {
  debug: (...args) => { if (DEBUG && typeof console !== 'undefined' && console.log) console.log(...args); },
  info: (...args) => { if (DEBUG && typeof console !== 'undefined' && console.info) console.info(...args); },
  warn: (...args) => { if (typeof console !== 'undefined' && console.warn) console.warn(...args); },
  error: (...args) => { if (typeof console !== 'undefined' && console.error) console.error(...args); },
};

export default logger;
