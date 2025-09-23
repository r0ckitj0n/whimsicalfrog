// Simple CSS visibility test for payment modal
// Copy this into browser console to check element visibility

(function() {
  console.log('[CSS-TEST] Starting CSS visibility test...');

  function checkElementVisibility(selector, name) {
    const el = document.querySelector(selector);
    if (!el) {
      console.error(`[CSS-TEST] ${name} not found: ${selector}`);
      return;
    }

    const styles = window.getComputedStyle(el);
    const visibility = {
      display: styles.display,
      visibility: styles.visibility,
      opacity: styles.opacity,
      'z-index': styles.zIndex,
      position: styles.position,
      width: styles.width,
      height: styles.height,
      'font-size': styles.fontSize,
      color: styles.color,
      textContent: el.textContent
    };

    console.log(`[CSS-TEST] ${name}:`, visibility);

    // Check if element is effectively invisible
    const isInvisible = (
      styles.display === 'none' ||
      styles.visibility === 'hidden' ||
      parseFloat(styles.opacity) === 0 ||
      parseInt(styles.fontSize) === 0
    );

    if (isInvisible) {
      console.error(`[CSS-TEST] ❌ ${name} appears to be invisible!`);
    } else {
      console.log(`[CSS-TEST] ✅ ${name} appears visible`);
    }

    return el;
  }

  // Test key payment modal elements
  const elements = [
    '#pm-orderShipping',
    '#pm-orderTotal',
    '#pm-shippingMethodSelect'
  ];

  elements.forEach(selector => {
    const name = selector.replace('#pm-', '').replace('#', '');
    checkElementVisibility(selector, name);
  });

  console.log('[CSS-TEST] CSS visibility test complete');
})();
