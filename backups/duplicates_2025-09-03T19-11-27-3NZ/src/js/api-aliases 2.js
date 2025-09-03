// Aliases exposing api-client helpers on the global window object
// to maintain compatibility with legacy scripts that expect apiGet / apiPost functions.
import apiClient from './api-client.js';

window.apiGet = apiClient.get;
window.apiPost = apiClient.post;
window.apiRequest = apiClient.request;

console.log('[api-aliases] Global API aliases registered');
