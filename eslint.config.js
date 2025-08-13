export default [
  {
    ignores: [
      "backups/**",
      "node_modules/**",
      "dist/**",
      "src/recovered/**"
    ],
  },
  {
    files: ["src/**/*.js", "scripts/**/*.js", "scripts/**/*.cjs"],
    ignores: [
      "js/bundle.js", // generated bundle; skip linting
      "js/sales-checker.js" // generated popup duplication; skip linting
    ],
    languageOptions: {
      ecmaVersion: "latest",
      sourceType: "module",
    },
    rules: {
      "no-unused-vars": [
        "error",
        {
          "argsIgnorePattern": "^_",
          "varsIgnorePattern": "^_",
          "caughtErrors": "none",
          "ignoreRestSiblings": true
        }
      ],
      "no-unreachable": "error",
      "prefer-const": "warn",
    },
  },
];
