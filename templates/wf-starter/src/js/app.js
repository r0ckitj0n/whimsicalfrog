import '../styles/main.css';

window.WhimsicalFrog = window.WhimsicalFrog || {};
window.wf = window.WhimsicalFrog;

// Example: ready event after globals are established
window.dispatchEvent(new CustomEvent('core:ready'));

document.addEventListener('DOMContentLoaded', () => {
  console.log('WF Starter app ready');
});
