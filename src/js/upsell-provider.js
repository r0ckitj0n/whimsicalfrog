// Heuristic Upsell Provider (no admin config required)
// Exposes window.WF_getUpsells(skus: string[]) => Promise<Array<{sku,name,price,image}>>
// Uses /api/search_items.php and simple scoring to recommend accessories and complementary items.

import { ApiClient } from '../core/api-client.js';

(function installUpsellProvider(){
  try {
    if (typeof window.WF_getUpsells === 'function') return; // respect custom providers

    const ACCESSORY_WORDS = ['lid','straw','sticker','stickers','decals','bundle','pack','gift','cleaner','brush','holder','hanger','cap','strap','filter','case','pouch'];
    const STOPWORDS = new Set(['the','and','or','for','with','a','an','of','to','in','on','by','at','is','are','this','that']);
    const CACHE_TTL_MS = 60 * 1000; // 60s
    const searchCache = new Map(); // key -> { ts, items }

    function tokenize(name){
      if (!name) return [];
      return String(name).toLowerCase().replace(/[^a-z0-9\s-]/g,' ').split(/[\s-]+/).filter(Boolean).filter(w => !STOPWORDS.has(w));
    }

    function unique(arr){ return Array.from(new Set(arr)); }

    async function search(term){
      try {
        const q = term && term.trim();
        if (!q) return [];
        const now = Date.now();
        const hit = searchCache.get(q);
        if (hit && (now - hit.ts) < CACHE_TTL_MS) return hit.items;
        const res = await ApiClient.get('/api/search_items.php', { q });
        // API returns { success, data: { results: [...] } } or bare array depending on helper
        const list = (res && (Array.isArray(res) ? res : (res.data?.results || res.results))) || [];
        const items = list.map(r => ({
          sku: String(r.sku || ''),
          name: String(r.name || r.title || r.sku || ''),
          price: Number(r.price || r.retailPrice || 0) || 0,
          image: r.image_url || r.image || r.image_path || '',
          category: String(r.category || '')
        })).filter(x => x.sku);
        searchCache.set(q, { ts: now, items });
        return items;
      } catch (_) { return []; }
    }

    async function WF_getUpsells(currentSkus){
      try {
        const skus = Array.isArray(currentSkus) ? currentSkus.map(s => String(s)) : [];
        const inCartSet = new Set(skus);
        const items = (window.WF_Cart && typeof window.WF_Cart.getItems === 'function') ? window.WF_Cart.getItems() : [];

        // Build keyword pool from cart item names
        let keywords = [];
        let avgPrice = 0;
        if (Array.isArray(items) && items.length){
          const total = items.reduce((sum, it) => sum + (Number(it.price)||0), 0);
          avgPrice = total / items.length;
          items.forEach(it => {
            const words = tokenize(it.name || it.sku);
            // Heuristic: keep the 1-2 most specific words (longest)
            const top = words.sort((a,b)=>b.length-a.length).slice(0,2);
            keywords.push(...top);
            // Domain hints
            const nameLower = String(it.name||'').toLowerCase();
            if (/tumbler|cup|mug|bottle/.test(nameLower)) keywords.push('tumbler','accessory','lid','straw');
            if (/shirt|tee|t\s*-?shirt|hoodie|sweatshirt/.test(nameLower)) keywords.push('shirt','sticker','bundle');
            if (/art|print|poster/.test(nameLower)) keywords.push('frame','hanger','stand');
          });
        }
        // Always add generic accessory terms
        keywords.push('accessory','bundle','gift','sticker');
        keywords = unique(keywords).slice(0,8);

        // Run searches in parallel and aggregate
        const batches = await Promise.all(keywords.map(k => search(k)));
        const pool = [];
        const seen = new Set();
        batches.flat().forEach(p => {
          if (!p || !p.sku || inCartSet.has(p.sku)) return;
          if (seen.has(p.sku)) return;
          seen.add(p.sku);
          pool.push(p);
        });

        // Score candidates
        const cartWordBag = unique(items.flatMap(it => tokenize(it.name||it.sku)));
        const cartWordSet = new Set(cartWordBag);
        const budget = avgPrice ? (avgPrice * 0.6) : Infinity;
        const clicks = getClickCounts();

        function scoreProduct(p){
          const nameWords = tokenize(p.name);
          const catWords = tokenize(p.category);
          let score = 0;
          // Accessory keyword boost
          if (ACCESSORY_WORDS.some(w => nameWords.includes(w))) score += 3;
          // Word overlap with cart
          const overlap = nameWords.filter(w => cartWordSet.has(w)).length;
          score += Math.min(3, overlap);
          // Category overlap bonus (up to +2)
          const catOverlap = catWords.filter(w => cartWordSet.has(w)).length;
          score += Math.min(2, catOverlap);
          // Price friendliness (cheaper gets small boost)
          if (isFinite(budget) && p.price > 0 && p.price <= budget) score += 1;
          // Prior user selection boost (sticky learning)
          const c = clicks[p.sku] || 0;
          if (c > 0) score += Math.min(3, c);
          // Recency fallback: none available; keep stable sort by name as tie-breaker
          return score;
        }

        const ranked = pool
          .map(p => ({ p, s: scoreProduct(p) }))
          .filter(x => x.s > 0) // require some relevance
          .sort((a,b) => (b.s - a.s) || (a.p.price - b.p.price) || a.p.name.localeCompare(b.p.name))
          .map(x => x.p)
          .slice(0, 8);

        return ranked;
      } catch(_) { return []; }
    }

    function getClickCounts(){
      try {
        const raw = localStorage.getItem('wf_upsell_clicks') || '{}';
        const obj = JSON.parse(raw);
        return (obj && typeof obj === 'object') ? obj : {};
      } catch(_) { return {}; }
    }

    function recordUpsellClick(sku){
      try {
        if (!sku) return;
        const data = getClickCounts();
        data[sku] = (Number(data[sku]||0) || 0) + 1;
        // Keep map small
        const entries = Object.entries(data).sort((a,b)=> (b[1]-a[1])).slice(0,50);
        const slim = Object.fromEntries(entries);
        localStorage.setItem('wf_upsell_clicks', JSON.stringify(slim));
      } catch(_) {}
    }

    window.WF_getUpsells = WF_getUpsells;
    window.__wfRecordUpsellClick = recordUpsellClick;
    console.log('[UpsellProvider] Installed heuristic provider');
  } catch (e) {
    console.error('[UpsellProvider] Install failed', e);
  }
})();
