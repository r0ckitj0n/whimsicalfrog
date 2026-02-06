/**
 * Stylelint configuration
 * - Enforces no attribute selectors to keep styling strictly class-based.
 * - Ignores backups, dist, and recovered legacy slices.
 */
module.exports = {
  extends: [
    'stylelint-config-standard',
  ],
  ignoreFiles: [
    'backups/**',
    'dist/**',
    'src/recovered/**',
    'node_modules/**',
  ],
  rules: {
    // Disallow attribute selectors like [data-action], [onclick], etc.
    // Keep styling strictly class-based per team conventions.
    'selector-max-attribute': 0,
  },
};
