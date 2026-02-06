export default [
  {
    ignores: [
      "backups/**",
      "node_modules/**",
      "dist/**",
      "src/recovered/**",
      // migrated from .eslintignore
      "vendor/**",
      "reports/**",
      ".templates/**",
      "public/**",
      "templates/wf-starter/**",
      "composer/**",
    ],
  },
  {
    files: [
      "src/**/*.js",
    ],
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
      // Discourage raw console.* in app code; prefer WF.log/WF.warn/WF.error helpers
      "no-console": [
        "warn",
        {
          allow: ["error"],
        },
      ],
      // Enforce ApiClient-only: forbid direct use of global fetch in app code
      "no-restricted-globals": [
        "error",
        {
          name: "fetch",
          message: "Use ApiClient (src/core/api-client.js) instead of direct fetch."
        }
      ],
      // Disallow inline style writes (class-based styling only)
      // 1) Block assignments to element.style.<prop>
      "no-restricted-syntax": [
        "error",
        {
          selector: "AssignmentExpression[left.type='MemberExpression'][left.object.type='MemberExpression'][left.object.property.name='style']",
          message: "Do not write to inline styles (element.style.*). Use CSS classes managed by Vite.",
        },
        {
          selector: "CallExpression[callee.type='MemberExpression'][callee.property.name='setProperty'][callee.object.type='MemberExpression'][callee.object.property.name='style']",
          message: "Do not set CSS variables on elements via style.setProperty. Use classes and CSS variables at a higher scope.",
        }
      ],
    },
  },
  {
    files: [
      "src/core/api-client.js",
      "src/js/site-core.js",
      "src/modules/api-client.js",
    ],
    rules: {
      // Allow internal implementations to use fetch
      "no-restricted-globals": "off",
    },
  },
  {
    files: [
      "src/js/site-core.js",
    ],
    rules: {
      // Allow site-core to manage console gating and WF logging implementation details
      "no-console": "off",
    },
  },
  {
    files: [
      "scripts/**/*.mjs",
      "scripts/**/*.js",
      "scripts/**/*.cjs",
    ],
    languageOptions: {
      ecmaVersion: "latest",
      sourceType: "module",
      globals: {
        // Enable Node globals for scripts
        module: "readonly",
        require: "readonly",
        __dirname: "readonly",
        process: "readonly",
        console: "readonly",
      },
    },
    rules: {
      // Relaxed for CLI tooling
      "no-unused-vars": [
        "warn",
        {
          argsIgnorePattern: "^_",
          varsIgnorePattern: "^_",
          caughtErrors: "none",
          ignoreRestSiblings: true,
        },
      ],
      "prefer-const": "warn",
      // Not relevant to Node scripts
      "no-restricted-syntax": "off",
    },
  },
];
