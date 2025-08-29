export default [
  {
    files: ["js/**/*.js"],
    ignores: [
      "backups/**",
      "node_modules/**",
      "js/bundle.js", // generated bundle; skip linting
      "js/sales-checker.js" // generated popup duplication; skip linting
    ],
    languageOptions: {
      ecmaVersion: "latest",
      sourceType: "module",
    },
    rules: {
      "no-unused-vars": "error",
      "no-unreachable": "error",
      "prefer-const": "warn",
    },
  },
];
