/**
 * Utility to wait for a nested function to exist on an object.
 * Example: await window.waitForFunction('WhimsicalFrog.GlobalModal.show', window);
 */

(function () {
  if (typeof window === 'undefined') return;

  /**
   * Waits for a function at a given object path to become available.
   * @param {string} functionPath Dot-separated path, e.g. "Foo.Bar.baz".
   * @param {object} [root=window] Root object to resolve the path from.
   * @param {number} [timeout=5000] Max time in ms to wait.
   * @param {number} [interval=100] Poll interval in ms.
   * @returns {Promise<boolean>} Resolves true if found, false if timed out.
   */
  async function waitForFunction(functionPath, root = window, timeout = 5000, interval = 100) {
    const parts = functionPath.split('.');
    const deadline = Date.now() + timeout;

    return new Promise((resolve) => {
      function check() {
        let obj = root;
        for (const part of parts) {
          if (obj && part in obj) {
            obj = obj[part];
          } else {
            obj = null;
            break;
          }
        }
        if (typeof obj === 'function') {
          return resolve(true);
        }
        if (Date.now() > deadline) {
          return resolve(false);
        }
        setTimeout(check, interval);
      }
      check();
    });
  }

  window.waitForFunction = waitForFunction;
  console.log('[wait-for-function] Utility registered');
})();
