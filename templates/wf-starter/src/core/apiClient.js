export default class ApiClient {
  constructor(base = '') { this.base = base || (typeof window !== 'undefined' ? '' : ''); }
  async request(path, { method = 'GET', headers = {}, body } = {}) {
    const url = path.startsWith('http') ? path : `${this.base}${path}`;
    const opts = { method, headers: { 'Content-Type': 'application/json', ...headers } };
    if (body !== undefined) opts.body = typeof body === 'string' ? body : JSON.stringify(body);
    const res = await fetch(url, opts);
    const text = await res.text();
    try { return JSON.parse(text); } catch { return text; }
  }
  get(path) { return this.request(path); }
  post(path, data) { return this.request(path, { method: 'POST', body: data }); }
}
