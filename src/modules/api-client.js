// Unified export: delegate to the canonical core ApiClient implementation.
// This ensures a single source of truth for HTTP behavior and avoids any
// raw fetch usage in this module path.
export { ApiClient } from '../core/api-client.js';
