import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '../..');
const source = readFileSync(resolve(repoRoot, 'includes/auth_cookie.php'), 'utf8');

function assertContains(needle, message) {
  if (!source.includes(needle)) {
    throw new Error(message);
  }
}

assertContains('function wf_auth_is_local_request(): bool', 'auth cookie fallback must be scoped by request host');
assertContains("if (wf_auth_is_local_request()) {\n        return 'wf_auth_fallback_secret_2025_09';\n    }", 'fallback secret must only be returned for local requests');
assertContains('return null;', 'missing production WF_AUTH_SECRET must disable cookie parsing');
assertContains('if ($secret === null) {\n        return null;\n    }', 'cookie parser must reject cookies when no production secret is configured');
assertContains('catch (RuntimeException $e) {\n        wf_auth_clear_cookie($domain, $secure);\n        return;\n    }', 'cookie setter must clear stale persistent cookies without breaking login');

console.log('[auth-cookie-policy] OK');
