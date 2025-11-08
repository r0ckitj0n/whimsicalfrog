import Chart from 'chart.js/auto';
import { ApiClient } from '../core/api-client.js';

const AdminMarketingModule = {
    currentTimeframe: 7,
    salesChartInstance: null,
    paymentChartInstance: null,
    topCategoriesChartInstance: null,
    topProductsChartInstance: null,
    orderStatusChartInstance: null,
    newReturningChartInstance: null,
    shippingChartInstance: null,
    aovTrendChartInstance: null,
    channelsChartInstance: null,
    channelRevenueChartInstance: null,
    // --- Drilldown helpers ---
    getClickedLabel(chart, evt){
        try{
            const points = chart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
            if (points && points.length){
                const { index } = points[0];
                return chart.data.labels?.[index];
            }
        }catch(_){ }
        return null;
    },
    goToOrdersWithQuery(q){
        try{
            const url = new URL('/admin/orders', window.location.origin);
            Object.entries(q || {}).forEach(([k,v])=>{ if (v!=null && v!=='') url.searchParams.set(k, v); });
            window.location.href = url.pathname + '?' + url.searchParams.toString();
        }catch(_){ }
    },
    // Simple modal helpers for iframe-local admin modals
    showOverlay(id) {
        try {
            const el = document.getElementById(id);
            if (!el) return false;
            // If running inside the AI tools iframe, always request parent to open tool overlay (full-viewport tint)
            try {
                const isEmbed = (window.parent && window.parent !== window);
                if (isEmbed) {
                    const map = {
                        socialManagerModal: { url: '/sections/tools/social_manager.php?modal=1', title: '📱 Social Accounts Manager' },
                        suggestionsManagerModal: { url: '/sections/tools/ai_suggestions.php?modal=1', title: '🤖 Suggestions Manager' },
                        automationManagerModal: { url: '/sections/tools/automation_manager.php?modal=1', title: '⚙️ Automation Manager' },
                        intentHeuristicsManagerModal: { url: '/sections/tools/intent_heuristics_manager.php?modal=1', title: '🧠 Intent Heuristics Config' },
                        contentGeneratorModal: { url: '/sections/tools/ai_content_generator.php?modal=1', title: '✍️ Content Generator' },
                        newsletterManagerModal: { url: '/sections/tools/newsletters_manager.php?modal=1', title: '📧 Newsletter Manager' },
                        discountManagerModal: { url: '/sections/tools/discounts_manager.php?modal=1', title: '💸 Discount Codes Manager' },
                        couponManagerModal: { url: '/sections/tools/coupons_manager.php?modal=1', title: '🎟️ Coupons Manager' }
                    };
                    const entry = map[id];
                    if (entry) {
                        try { window.parent.postMessage({ source: 'wf-ai', type: 'open-tool', url: entry.url, title: entry.title }, '*'); } catch(_) {}
                        return true; // parent will handle; do not render a local overlay
                    }
                }
            } catch(_) {}
            if (el.parentElement && el.parentElement !== document.body) {
                document.body.appendChild(el);
            }
            // Ensure unified modal policy
            try { el.classList.add('wf-modal-auto'); } catch(_) {}
            try { el.classList.add('wf-modal-autowide'); } catch(_) {}
            try { el.classList.add('wf-modal-single-scroll'); } catch(_) {}
            // If inside AI modal (same-origin parent), prefer viewport-fill behavior for full-coverage scrim and remove inner scrim
            try {
                const inIframe = (window.top && window.top !== window);
                if (inIframe) {
                    let parentHasAI = false;
                    try { parentHasAI = !!window.top.document.querySelector('#aiUnifiedModal.show, #aiUnifiedChildModal.show'); } catch(_) {}
                    if (parentHasAI) { el.classList.add('wf-modal-viewport-fill'); el.classList.add('wf-modal-noscrim'); }
                }
            } catch(_) {}
            el.classList.remove('hidden');
            el.classList.add('show');
            el.setAttribute('aria-hidden', 'false');
            // Recompute responsive sizing for non-iframe overlays (primary path)
            try { if (typeof window.markOverlayResponsive === 'function') window.markOverlayResponsive(el); } catch(_) {}
            // Fallback local autosize only if markOverlayResponsive is not available in this iframe
            try {
                if (!(typeof window.markOverlayResponsive === 'function')) {
                    // Reset any inherited/preset sizing so measurement starts from a neutral state
                    const panel = el.querySelector('.admin-modal');
                    const body = panel ? panel.querySelector('.modal-body') : null;
                    const header = panel ? panel.querySelector('.modal-header') : null;
                    if (panel) {
                        try { panel.classList.add('admin-modal--responsive'); } catch(_) {}
                        try { panel.classList.remove('admin-modal--xs','admin-modal--sm','admin-modal--md','admin-modal--lg','admin-modal--lg-narrow','admin-modal--xl','admin-modal--full','admin-modal--square-200','admin-modal--square-260','admin-modal--square-300'); } catch(_) {}
                    }
                    if (body) {
                        try { body.classList.remove('wf-modal-body--scroll','wf-modal-body--autoheight'); } catch(_) {}
                        try { body.classList.add('wf-modal-body--autoheight'); } catch(_) {}
                    }
                    try {
                        const frames = el.querySelectorAll('iframe, .wf-admin-embed-frame');
                        frames.forEach((f) => {
                            try { f.classList.remove('wf-embed--fill','wf-embed-h-s','wf-embed-h-m','wf-embed-h-l','wf-embed-h-xl','wf-embed-h-xxl','wf-admin-embed-frame--tall'); } catch(_) {}
                        });
                    } catch(_) {}
                    if (panel && body) {
                        const getF = (n, f) => { try { const cs = getComputedStyle(n); return (parseFloat(cs[f])||0); } catch(_) { return 0; } };
                        const applyScroll = () => {
                            try {
                                const maxVh = 0.95;
                                const maxPanel = Math.floor(window.innerHeight * maxVh);
                                const available = Math.max(160, maxPanel - (header?header.offsetHeight:0) - (getF(panel,'paddingTop')+getF(panel,'paddingBottom')) - (getF(body,'paddingTop')+getF(body,'paddingBottom')));
                                // If the body directly wraps an iframe, suppress outer scroll (let iframe scroll)
                                const directIframe = !!body.querySelector(':scope > iframe, :scope > .wf-admin-embed-frame');
                                const need = directIframe ? false : ((body.scrollHeight - available) > 8);
                                body.classList.toggle('wf-modal-body--scroll', need);
                                body.classList.toggle('wf-modal-body--autoheight', !need);
                            } catch(_) {}
                        };
                        applyScroll();
                        try { if (!el.__wfLocalRO) { const ro = new ResizeObserver(()=>applyScroll()); ro.observe(body); el.__wfLocalRO = ro; } } catch(_) {}
                        try { if (!el.__wfLocalMO) { const mo = new MutationObserver(()=>applyScroll()); mo.observe(body, { childList:true, subtree:true, characterData:true }); el.__wfLocalMO = mo; } } catch(_) {}
                        // Recalculate on window resize to collapse back from tall overlays
                        try { if (!el.__wfLocalResize) { const onR=()=>applyScroll(); window.addEventListener('resize', onR, { passive:true }); el.__wfLocalResize = onR; } } catch(_) {}
                    }
                }
            } catch(_) {}
            return true;
        } catch (_) { return false; }
    },

    // --------- Suggestions Manager (by SKU) ---------
    async openSuggestionsManager() {
        const modalId = 'suggestionsManagerModal';
        const content = document.getElementById('suggestionsManagerContent');
        if (!content) { this.showOverlay(modalId); return; }
        content.innerHTML = `
            <div class="space-y-3">
                <div>
                    <label class="text-xs text-gray-600">Choose Item</label>
                    <select id="ism-item-select" class="admin-form-input"><option>Loading items…</option></select>
                    <div id="ism-preview" class="mt-1 text-xs text-gray-600 hidden"></div>
                </div>
                <div class="flex gap-2">
                    <button id="ism-generate" class="btn btn-primary">Generate All</button>
                    <button id="ism-clear" class="btn btn-secondary">Clear</button>
                </div>
                <div id="ism-editor" class="hidden space-y-3">
                    <div>
                        <label class="text-xs text-gray-600">Title</label>
                        <input id="ism-title" class="admin-form-input" placeholder="Generated title">
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">Description</label>
                        <textarea id="ism-desc" class="admin-form-textarea" rows="6" placeholder="Generated description"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-600">Suggested Price</label>
                            <input id="ism-price" type="number" step="0.01" class="admin-form-input" placeholder="Retail price">
                            <div id="ism-price-notes" class="mt-1 text-xs text-gray-600"></div>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600">Suggested Cost</label>
                            <input id="ism-cost" type="number" step="0.01" class="admin-form-input" placeholder="Cost price">
                            <div id="ism-cost-breakdown" class="mt-1 text-xs text-gray-600"></div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button id="ism-apply-all" class="btn btn-primary">Apply All</button>
                        <button id="ism-apply-title" class="btn btn-secondary btn-sm">Apply Title</button>
                        <button id="ism-apply-desc" class="btn btn-secondary btn-sm">Apply Description</button>
                        <button id="ism-apply-price" class="btn btn-secondary btn-sm">Apply Price</button>
                        <button id="ism-apply-cost" class="btn btn-secondary btn-sm">Apply Cost</button>
                    </div>
                    <div id="ism-status" class="text-xs text-gray-600"></div>
                </div>
            </div>`;

        const select = content.querySelector('#ism-item-select');
        const preview = content.querySelector('#ism-preview');
        const editor = content.querySelector('#ism-editor');
        const titleEl = content.querySelector('#ism-title');
        const descEl = content.querySelector('#ism-desc');
        const priceEl = content.querySelector('#ism-price');
        const costEl = content.querySelector('#ism-cost');
        const priceNotes = content.querySelector('#ism-price-notes');
        const costBreakdown = content.querySelector('#ism-cost-breakdown');
        const statusEl = content.querySelector('#ism-status');

        let items = [];
        let current = null; // currently selected item object

        const setStatus = (msg, ok = true) => {
            if (!statusEl) return; statusEl.textContent = msg || ''; statusEl.classList.toggle('text-red-600', !ok);
        };

        const loadItems = async () => {
            try {
                const res = await ApiClient.get('/api/inventory.php');
                items = Array.isArray(res?.data) ? res.data : (Array.isArray(res) ? res : []);
            } catch { items = []; }
            if (!items.length) {
                select.innerHTML = '<option value="">No items found</option>';
                return;
            }
            const opts = ['<option value="">Select an item…</option>'].concat(items.map(it => {
                const label = `${it.sku || ''} — ${it.name || '(Unnamed Item)'}`.trim();
                return `<option value="${String(it.sku || '').replace(/"/g,'&quot;')}">${label}</option>`;
            }));
            select.innerHTML = opts.join('');
        };

        const updatePreview = () => {
            const sku = select.value;
            if (!sku) { preview.classList.add('hidden'); preview.innerHTML=''; current = null; editor.classList.add('hidden'); return; }
            current = items.find(i => String(i.sku) === sku) || null;
            const name = current?.name || sku;
            const category = current?.category || '';
            const retail = current?.retailPrice ?? '';
            const cost = current?.costPrice ?? '';
            preview.innerHTML = `<div><span class="font-medium">${name}</span> <span class="text-gray-500">(${category || 'No category'})</span></div>
                                 <div>Current Price: ${retail ? `$${Number(retail).toFixed(2)}` : '—'} • Current Cost: ${cost ? `$${Number(cost).toFixed(2)}` : '—'}</div>`;
            preview.classList.remove('hidden');
        };

        const clearEditor = () => {
            titleEl.value = '';
            descEl.value = '';
            priceEl.value = '';
            costEl.value = '';
            priceNotes.innerHTML = '';
            costBreakdown.innerHTML = '';
            setStatus('');
            editor.classList.add('hidden');
        };

        content.querySelector('#ism-clear')?.addEventListener('click', () => { if (select) select.selectedIndex = 0; updatePreview(); clearEditor(); });
        select.addEventListener('change', () => { updatePreview(); clearEditor(); });
        await loadItems();
        updatePreview();

        const generateAll = async () => {
            if (!current) {
                if (typeof window.showAlertModal === 'function') {
                    await window.showAlertModal({ title: 'Select Item', message: 'Please choose an item.' });
                } else { alert('Please choose an item'); }
                return;
            }
            setStatus('Generating content…');
            editor.classList.remove('hidden');
            // Run marketing + cost in parallel first
            const sku = current.sku;
            const name = current.name || sku;
            const category = current.category || '';
            const baseDesc = current.description || '';
            try {
                const [mkt, cost] = await Promise.all([
                    ApiClient.post('/api/suggest_marketing.php', { name, description: baseDesc, category, sku }),
                    ApiClient.post('/api/suggest_cost.php', { name, description: baseDesc, category, sku })
                ]);
                const genTitle = (mkt.title || mkt.suggested_title || '').trim();
                const genDesc = (mkt.description || mkt.suggested_description || '').trim();
                titleEl.value = genTitle || name;
                descEl.value = genDesc || baseDesc;
                const suggestedCost = Number(cost?.suggestedCost ?? cost?.cost ?? 0) || 0;
                if (suggestedCost > 0) costEl.value = suggestedCost.toFixed(2);
                const br = cost?.breakdown || {};
                costBreakdown.innerHTML = br && (br.materials || br.labor || br.energy || br.equipment)
                  ? `Materials: $${Number(br.materials||0).toFixed(2)} • Labor: $${Number(br.labor||0).toFixed(2)} • Energy: $${Number(br.energy||0).toFixed(2)} • Equipment: $${Number(br.equipment||0).toFixed(2)}`
                  : '';
                // Then generate price using suggested cost as input when available
                const pricePayload = { name: titleEl.value || name, description: descEl.value || baseDesc, category, sku };
                if (suggestedCost > 0) pricePayload.costPrice = suggestedCost;
                else if (current.costPrice) pricePayload.costPrice = Number(current.costPrice);
                const price = await ApiClient.post('/api/suggest_price.php', pricePayload);
                const suggestedPrice = Number(price?.suggestedPrice ?? price?.price ?? 0) || 0;
                if (suggestedPrice > 0) priceEl.value = suggestedPrice.toFixed(2);
                priceNotes.innerHTML = price?.reasoning ? String(price.reasoning) : '';
                setStatus('Generated. Review and edit before applying.');
            } catch (err) {
                setStatus((err && err.message) || 'Generation failed', false);
            }
        };
        content.querySelector('#ism-generate')?.addEventListener('click', generateAll);

        const applyField = async (field, value) => {
            if (!current) throw new Error('No item selected');
            return ApiClient.post('/api/update_inventory.php', { sku: current.sku, field, value });
        };
        const applyMarketingField = async (field, value) => {
            if (!current) throw new Error('No item selected');
            return ApiClient.post('/api/marketing_manager.php?action=update_field', { sku: current.sku, field, value });
        };

        content.querySelector('#ism-apply-title')?.addEventListener('click', async () => {
            try { await applyField('name', titleEl.value || ''); await applyMarketingField('suggested_title', titleEl.value || ''); setStatus('Title applied'); }
            catch { setStatus('Failed to apply title', false); }
        });
        content.querySelector('#ism-apply-desc')?.addEventListener('click', async () => {
            try { await applyField('description', descEl.value || ''); await applyMarketingField('suggested_description', descEl.value || ''); setStatus('Description applied'); }
            catch { setStatus('Failed to apply description', false); }
        });
        content.querySelector('#ism-apply-price')?.addEventListener('click', async () => {
            try { await applyField('retailPrice', Number(priceEl.value || 0)); setStatus('Price applied'); }
            catch { setStatus('Failed to apply price', false); }
        });
        content.querySelector('#ism-apply-cost')?.addEventListener('click', async () => {
            try { await applyField('costPrice', Number(costEl.value || 0)); setStatus('Cost applied'); }
            catch { setStatus('Failed to apply cost', false); }
        });
        content.querySelector('#ism-apply-all')?.addEventListener('click', async () => {
            try {
                await applyField('name', titleEl.value || '');
                await applyField('description', descEl.value || '');
                if (priceEl.value) await applyField('retailPrice', Number(priceEl.value));
                if (costEl.value) await applyField('costPrice', Number(costEl.value));
                await applyMarketingField('suggested_title', titleEl.value || '');
                await applyMarketingField('suggested_description', descEl.value || '');
                setStatus('All changes applied');
            } catch { setStatus('Failed to apply changes', false); }
        });

        this.showOverlay(modalId);
    },

    async openContentGenerator() {
        const modalId = 'contentGeneratorModal';
        const el = document.getElementById('contentGeneratorContent');
        if (el) {
            el.innerHTML = `
                <div class="grid gap-3">
                    <div>
                        <label class="text-xs text-gray-600">Choose Item</label>
                        <select id="cg-item-select" class="admin-form-input">
                            <option value="">Loading items…</option>
                        </select>
                    </div>
                    <div id="cg-item-preview" class="text-xs text-gray-600 hidden"></div>
                    <textarea id="cg-item-desc" class="admin-form-textarea" placeholder="Existing description (optional)"></textarea>
                    <div class="flex gap-2">
                        <select id="cg-voice" class="admin-form-input">
                            <option value="">Brand voice (auto)</option>
                            <option>friendly</option><option>professional</option><option>playful</option><option>luxurious</option><option>casual</option>
                        </select>
                        <select id="cg-tone" class="admin-form-input">
                            <option value="">Tone (auto)</option>
                            <option>professional</option><option>friendly</option><option>casual</option><option>energetic</option><option>playful</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button id="cg-generate" class="btn btn-primary">Generate</button>
                        <button id="cg-clear" class="btn btn-secondary">Clear</button>
                    </div>
                    <div id="cg-result" class="hidden p-3 bg-gray-50 rounded text-sm"></div>
                </div>`;

            const select = el.querySelector('#cg-item-select');
            const preview = el.querySelector('#cg-item-preview');
            const descArea = el.querySelector('#cg-item-desc');
            let _lastGen = null; // holds last generated {title, description}

            // Load items for dropdown
            let items = [];
            try {
                const res = await ApiClient.get('/api/inventory.php');
                items = Array.isArray(res?.data) ? res.data : (Array.isArray(res) ? res : []);
            } catch (_) { items = []; }

            if (!items.length) {
                select.innerHTML = '<option value="">No items found</option>';
            } else {
                const options = ['<option value="">Select an item…</option>'].concat(
                    items.map(it => {
                        const label = `${it.sku || ''} — ${it.name || '(Unnamed Item)'}`.trim();
                        return `<option value="${(it.sku || '').replace(/"/g,'&quot;')}" data-name="${(it.name || '').replace(/"/g,'&quot;')}" data-category="${(it.category || '').replace(/"/g,'&quot;')}" data-desc="${(it.description || '').replace(/"/g,'&quot;')}">${label}</option>`;
                    })
                );
                select.innerHTML = options.join('');
            }

            const updatePreview = () => {
                const opt = select.options[select.selectedIndex];
                if (!opt || !opt.value) { preview.classList.add('hidden'); preview.innerHTML = ''; return; }
                const name = opt.getAttribute('data-name') || '';
                const category = opt.getAttribute('data-category') || '';
                const desc = opt.getAttribute('data-desc') || '';
                preview.innerHTML = `<div><span class="font-medium">${name}</span> <span class="text-gray-500">(${category || 'No category'})</span></div>`;
                preview.classList.remove('hidden');
                if (desc && (!descArea.value || descArea.value.trim() === '')) descArea.value = desc;
            };
            select.addEventListener('change', updatePreview);
            updatePreview();

            // Bind buttons
            el.querySelector('#cg-generate')?.addEventListener('click', async () => {
                const opt = select.options[select.selectedIndex];
                if (!opt || !opt.value) {
                    if (typeof window.showAlertModal === 'function') {
                        await window.showAlertModal({ title: 'Select Item', message: 'Please choose an item.' });
                    } else { alert('Please choose an item'); }
                    return;
                }
                const sku = opt.value;
                const name = opt.getAttribute('data-name') || sku;
                const category = opt.getAttribute('data-category') || '';
                const description = (descArea?.value || '').trim();
                const brandVoice = document.getElementById('cg-voice')?.value || '';
                const contentTone = document.getElementById('cg-tone')?.value || '';
                const box = document.getElementById('cg-result');
                if (!box) return;
                box.classList.remove('hidden');
                box.innerHTML = 'Generating content…';
                try {
                    const data = await ApiClient.post('/api/suggest_marketing.php', { name, description, category, sku, brandVoice, contentTone });
                    let keywords = [];
                    if (Array.isArray(data.seo_keywords)) keywords = data.seo_keywords;
                    else if (typeof data.seo_keywords === 'string') { try { keywords = JSON.parse(data.seo_keywords); } catch(_) {} }
                    else if (Array.isArray(data.keywords)) keywords = data.keywords;
                    const genTitle = (data.title || data.suggested_title || '').trim();
                    const genDesc = (data.description || data.suggested_description || '').trim();
                    _lastGen = { title: genTitle, description: genDesc };
                    box.innerHTML = `<div class="font-medium mb-1">${genTitle || 'Generated Title'}</div>
                                     <div class="mb-2">${genDesc}</div>
                                     <div class="text-xs text-gray-600">Keywords: ${keywords.length ? keywords.join(', ') : ''}</div>
                                     <div class="mt-3 flex gap-2">
                                        <button id="cg-apply-title" class="btn btn-primary btn-sm">Apply Title</button>
                                        <button id="cg-apply-desc" class="btn btn-secondary btn-sm">Apply Description</button>
                                        <button id="cg-apply-both" class="btn btn-secondary btn-sm">Apply Both</button>
                                     </div>
                                     <div id="cg-apply-status" class="text-xs text-gray-600 mt-2"></div>`;

                    const statusEl = document.getElementById('cg-apply-status');
                    const setStatus = (msg, ok = true) => {
                        if (!statusEl) return; statusEl.textContent = msg; statusEl.classList.toggle('text-red-600', !ok);
                    };
                    const applyTitle = async () => {
                        if (!_lastGen || !_lastGen.title) { setStatus('No title to apply', false); return; }
                        try { await ApiClient.post('/api/update_inventory.php', { sku, field: 'name', value: _lastGen.title }); setStatus('Title applied'); }
                        catch (e) { setStatus('Failed to apply title', false); }
                    };
                    const applyDesc = async () => {
                        if (!_lastGen || !_lastGen.description) { setStatus('No description to apply', false); return; }
                        try { await ApiClient.post('/api/update_inventory.php', { sku, field: 'description', value: _lastGen.description }); setStatus('Description applied'); }
                        catch (e) { setStatus('Failed to apply description', false); }
                    };
                    box.querySelector('#cg-apply-title')?.addEventListener('click', async () => { await applyTitle(); });
                    box.querySelector('#cg-apply-desc')?.addEventListener('click', async () => { await applyDesc(); });
                    box.querySelector('#cg-apply-both')?.addEventListener('click', async () => { await applyTitle(); await applyDesc(); });
                } catch (err) {
                    box.innerHTML = `<span class="text-red-600">${(err && err.message) || 'Generation failed'}</span>`;
                }
            });
            el.querySelector('#cg-clear')?.addEventListener('click', () => {
                if (select) select.selectedIndex = 0; updatePreview();
                if (descArea) descArea.value = '';
                const box = document.getElementById('cg-result'); if (box) { box.classList.add('hidden'); box.innerHTML=''; }
            });
        }
        this.showOverlay(modalId);
    },

    // ---------------- Marketing Settings (CRUD via business_settings) ----------------
    async loadMarketingSettingsMap() {
        try {
            const data = await ApiClient.get('business_settings.php', { action: 'get_by_category', category: 'marketing' });
            const arr = Array.isArray(data?.settings) ? data.settings : [];
            const map = {};
            for (const row of arr) {
                const key = row.setting_key;
                let val = row.setting_value;
                try { if (typeof val === 'string' && (val.trim().startsWith('{') || val.trim().startsWith('['))) val = JSON.parse(val); } catch(_) {}
                map[key] = val;
            }
            return map;
        } catch (e) {
            console.error('Failed to load marketing settings', e);
            return {};
        }
    },
    async upsertMarketingSettings(settingsMap) {
        return ApiClient.post('business_settings.php?action=upsert_settings', { category: 'marketing', settings: settingsMap });
    },

    // --------- Newsletters Manager ---------
    async openNewslettersManager() {
        const modalId = 'newsletterManagerModal';
        const content = document.getElementById('newsletterManagerContent');
        if (!content) { this.showOverlay(modalId); return; }
        content.innerHTML = 'Loading…';
        const map = await this.loadMarketingSettingsMap();
        const items = Array.isArray(map['marketing_newsletters']) ? map['marketing_newsletters'] : [];
        content.innerHTML = this.renderListManager('newsletter', items, ['title','subject','scheduledAt','status']);
        this.bindListManager('newsletter', items, async (updated) => {
            await this.upsertMarketingSettings({ marketing_newsletters: updated });
        });
        this.showOverlay(modalId);
    },

    // --------- Automation Manager ---------
    async openAutomationManager() {
        const modalId = 'automationManagerModal';
        // Prefer parent overlay if embedded
        try {
            const isEmbed = (window.parent && window.parent !== window);
            if (isEmbed) {
                try { window.parent.postMessage({ source: 'wf-ai', type: 'open-tool', url: '/sections/tools/automation_manager.php?modal=1', title: '⚙️ Automation Manager' }, '*'); return; } catch(_) {}
            }
        } catch(_) {}
        const content = document.getElementById('automationManagerContent');
        if (!content) { this.showOverlay(modalId); return; }
        content.innerHTML = 'Loading…';
        const map = await this.loadMarketingSettingsMap();
        const items = Array.isArray(map['marketing_automations']) ? map['marketing_automations'] : [];
        content.innerHTML = this.renderListManager('automation', items, ['name','trigger','status']);
        this.bindListManager('automation', items, async (updated) => {
            await this.upsertMarketingSettings({ marketing_automations: updated });
        });
        this.showOverlay(modalId);
    },

    // --------- Discount Codes Manager ---------
    async openDiscountsManager() {
        const modalId = 'discountManagerModal';
        const content = document.getElementById('discountManagerContent');
        if (!content) { this.showOverlay(modalId); return; }
        content.innerHTML = 'Loading…';
        const map = await this.loadMarketingSettingsMap();
        const items = Array.isArray(map['marketing_discounts']) ? map['marketing_discounts'] : [];
        content.innerHTML = this.renderListManager('discount', items, ['code','type','value','minTotal','expires','active']);
        this.bindListManager('discount', items, async (updated) => {
            await this.upsertMarketingSettings({ marketing_discounts: updated });
        });
        this.showOverlay(modalId);
    },

    // --------- Coupons Manager ---------
    async openCouponsManager() {
        const modalId = 'couponManagerModal';
        const content = document.getElementById('couponManagerContent');
        if (!content) { this.showOverlay(modalId); return; }
        content.innerHTML = 'Loading…';
        const map = await this.loadMarketingSettingsMap();
        const items = Array.isArray(map['marketing_coupons']) ? map['marketing_coupons'] : [];
        content.innerHTML = this.renderListManager('coupon', items, ['code','description','value','expires','active']);
        this.bindListManager('coupon', items, async (updated) => {
            await this.upsertMarketingSettings({ marketing_coupons: updated });
        });
        this.showOverlay(modalId);
    },

    // --------- Intent Heuristics Manager (inline-first; fallback iframe) ---------
    async openIntentHeuristicsManager() {
        const modalId = 'intentHeuristicsManagerModal';
        const inline = document.getElementById('intentHeuristicsContent');
        if (inline) {
            inline.innerHTML = `
              <div class="space-y-3 text-sm text-gray-700">
                <div class="flex gap-2">
                  <button id="ih-load-defaults" class="btn btn-secondary">Load Defaults</button>
                  <button id="ih-reload" class="btn btn-secondary">Reload Current</button>
                  <button id="ih-save" class="btn btn-primary">Save</button>
                </div>
                <div class="card">
                  <div class="section-title">Weights</div>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div><label class="text-xs text-gray-600">Popularity cap</label><input class="admin-form-input" type="number" step="0.1" id="w_popularity_cap"></div>
                    <div><label class="text-xs text-gray-600">Keyword positive</label><input class="admin-form-input" type="number" step="0.1" id="w_kw_positive"></div>
                    <div><label class="text-xs text-gray-600">Category positive</label><input class="admin-form-input" type="number" step="0.1" id="w_cat_positive"></div>
                    <div><label class="text-xs text-gray-600">Seasonal boost</label><input class="admin-form-input" type="number" step="0.1" id="w_seasonal"></div>
                    <div><label class="text-xs text-gray-600">Same category</label><input class="admin-form-input" type="number" step="0.1" id="w_same_category"></div>
                    <div><label class="text-xs text-gray-600">Upgrade ratio threshold</label><input class="admin-form-input" type="number" step="0.01" id="w_upgrade_ratio"></div>
                    <div><label class="text-xs text-gray-600">Upgrade price boost</label><input class="admin-form-input" type="number" step="0.1" id="w_upgrade_price"></div>
                    <div><label class="text-xs text-gray-600">Upgrade label boost</label><input class="admin-form-input" type="number" step="0.1" id="w_upgrade_label"></div>
                    <div><label class="text-xs text-gray-600">Replacement label boost</label><input class="admin-form-input" type="number" step="0.1" id="w_replacement_label"></div>
                    <div><label class="text-xs text-gray-600">Gift set boost</label><input class="admin-form-input" type="number" step="0.1" id="w_gift_set"></div>
                    <div><label class="text-xs text-gray-600">Gift price boost</label><input class="admin-form-input" type="number" step="0.1" id="w_gift_price"></div>
                    <div><label class="text-xs text-gray-600">Teacher price ceiling</label><input class="admin-form-input" type="number" step="0.1" id="w_teacher_ceiling"></div>
                    <div><label class="text-xs text-gray-600">Teacher price boost</label><input class="admin-form-input" type="number" step="0.1" id="w_teacher_boost"></div>
                    <div><label class="text-xs text-gray-600">Budget proximity multiplier</label><input class="admin-form-input" type="number" step="0.1" id="w_budget_mult"></div>
                    <div><label class="text-xs text-gray-600">Negative keyword penalty</label><input class="admin-form-input" type="number" step="0.1" id="w_neg_penalty"></div>
                    <div><label class="text-xs text-gray-600">Intent badge threshold</label><input class="admin-form-input" type="number" step="0.1" id="w_badge_threshold"></div>
                  </div>
                </div>
                <div class="card">
                  <div class="section-title">Budget Ranges</div>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div><label class="text-xs text-gray-600">Low (min)</label><input class="admin-form-input" type="number" step="0.1" id="b_low_min"></div>
                    <div><label class="text-xs text-gray-600">Low (max)</label><input class="admin-form-input" type="number" step="0.1" id="b_low_max"></div>
                    <div><label class="text-xs text-gray-600">Mid (min)</label><input class="admin-form-input" type="number" step="0.1" id="b_mid_min"></div>
                    <div><label class="text-xs text-gray-600">Mid (max)</label><input class="admin-form-input" type="number" step="0.1" id="b_mid_max"></div>
                    <div><label class="text-xs text-gray-600">High (min)</label><input class="admin-form-input" type="number" step="0.1" id="b_high_min"></div>
                    <div><label class="text-xs text-gray-600">High (max)</label><input class="admin-form-input" type="number" step="0.1" id="b_high_max"></div>
                  </div>
                </div>
                <div class="card">
                  <div class="section-title">Advanced: Seasonal Months Hints (JSON)</div>
                  <textarea id="t_seasonal" class="admin-form-textarea" rows="4" placeholder='{"12": ["christmas"], "2": ["valentine"]}'></textarea>
                </div>
                <div class="card">
                  <div class="section-title">Advanced: Keyword Positives by Intent (JSON)</div>
                  <textarea id="t_kw_pos" class="admin-form-textarea" rows="6" placeholder='{"upgrade": ["pro","premium"]}'></textarea>
                </div>
                <div class="card">
                  <div class="section-title">Advanced: Keyword Negatives by Intent (JSON)</div>
                  <textarea id="t_kw_neg" class="admin-form-textarea" rows="4" placeholder='{"replacement": ["gift","decor"]}'></textarea>
                </div>
                <div class="card">
                  <div class="section-title">Advanced: Category Affinities by Intent (JSON)</div>
                  <textarea id="t_cat_pos" class="admin-form-textarea" rows="6" placeholder='{"home-decor": ["home decor","signs"]}'></textarea>
                </div>
                <div id="ih-status" class="text-xs text-gray-600" role="status" aria-live="polite"></div>
              </div>`;

            const statusEl = inline.querySelector('#ih-status');
            try { if (statusEl) { statusEl.setAttribute('role','status'); statusEl.setAttribute('aria-live','polite'); } } catch(_) {}
            const setStatus = (msg, ok = true) => { if (!statusEl) return; statusEl.textContent = msg || ''; statusEl.classList.toggle('text-red-600', !ok); };

            const Defaults = {
              weights: {
                popularity_cap: 3.0,
                kw_positive: 2.5,
                cat_positive: 3.5,
                seasonal: 2.0,
                same_category: 2.0,
                upgrade_price_ratio_threshold: 1.25,
                upgrade_price_boost: 3.0,
                upgrade_label_boost: 2.5,
                replacement_label_boost: 3.0,
                gift_set_boost: 1.0,
                gift_price_boost: 1.5,
                teacher_price_ceiling: 30.0,
                teacher_price_boost: 1.5,
                budget_proximity_mult: 2.0,
                neg_keyword_penalty: 2.0,
                intent_badge_threshold: 2.0
              },
              budget_ranges: { low: [8.0,20.0], mid: [15.0,40.0], high: [35.0,120.0] },
              keywords: {
                positive: {
                  gift: ["gift","set","bundle","present","pack","box"],
                  personal: [],
                  replacement: ["refill","replacement","spare","recharge","insert"],
                  upgrade: ["upgrade","pro","deluxe","premium","xl","plus","pro+","ultimate"],
                  "diy-project": ["diy","kit","project","starter","make your own","how to"],
                  "home-decor": ["decor","wall","frame","sign","plaque","art","canvas"],
                  holiday: ["holiday","christmas","xmas","easter","halloween","valentine","mother","father"],
                  birthday: ["birthday","party","celebration","cake","bday"],
                  anniversary: ["anniversary","love","heart","romance","romantic"],
                  wedding: ["wedding","bride","groom","bridal","mr & mrs","mr and mrs"],
                  "teacher-gift": ["teacher","school","classroom","teach"],
                  "office-decor": ["office","desk","workspace","cubicle"],
                  "event-supplies": ["event","party","supplies","decoration","bulk"],
                  "workshop-class": ["class","workshop","lesson","course","tutorial"]
                },
                negative: { gift: ["refill","replacement"], replacement: ["gift","decor"], upgrade: ["refill"] },
                categories: {
                  gift: ["gifts","gift sets","bundles"],
                  replacement: ["supplies","refills","consumables"],
                  "diy-project": ["diy","kits","craft kits","projects"],
                  "home-decor": ["home decor","decor","wall art","signs"],
                  holiday: ["holiday","seasonal"],
                  "office-decor": ["office decor"],
                  "event-supplies": ["event supplies","party"],
                  "workshop-class": ["classes","workshops"]
                }
              },
              seasonal_months: { 1:["valentine"], 2:["valentine"], 3:["easter"], 4:["easter"], 5:["mother"], 6:["father"], 9:["halloween"], 10:["halloween"], 11:["christmas"], 12:["christmas"] }
            };

            const setForm = (cfg) => {
              const W = cfg && cfg.weights ? cfg.weights : {};
              const BR = cfg && cfg.budget_ranges ? cfg.budget_ranges : {};
              const low = BR.low || [null,null];
              const mid = BR.mid || [null,null];
              const high = BR.high || [null,null];
              const v = (id, val) => { const n=document.getElementById(id); if (n) n.value = (val ?? '') ; };
              v('w_popularity_cap', W.popularity_cap);
              v('w_kw_positive', W.kw_positive);
              v('w_cat_positive', W.cat_positive);
              v('w_seasonal', W.seasonal);
              v('w_same_category', W.same_category);
              v('w_upgrade_ratio', W.upgrade_price_ratio_threshold);
              v('w_upgrade_price', W.upgrade_price_boost);
              v('w_upgrade_label', W.upgrade_label_boost);
              v('w_replacement_label', W.replacement_label_boost);
              v('w_gift_set', W.gift_set_boost);
              v('w_gift_price', W.gift_price_boost);
              v('w_teacher_ceiling', W.teacher_price_ceiling);
              v('w_teacher_boost', W.teacher_price_boost);
              v('w_budget_mult', W.budget_proximity_mult);
              v('w_neg_penalty', W.neg_keyword_penalty);
              v('w_badge_threshold', W.intent_badge_threshold);
              v('b_low_min', low[0]); v('b_low_max', low[1]);
              v('b_mid_min', mid[0]); v('b_mid_max', mid[1]);
              v('b_high_min', high[0]); v('b_high_max', high[1]);
              const t = (id, obj) => { const n=document.getElementById(id); if (n) n.value = JSON.stringify(obj || {}, null, 2); };
              const K = cfg && cfg.keywords ? cfg.keywords : {};
              t('t_seasonal', cfg && cfg.seasonal_months ? cfg.seasonal_months : {});
              t('t_kw_pos', K.positive || {});
              t('t_kw_neg', K.negative || {});
              t('t_cat_pos', K.categories || {});
            };

            const getForm = () => {
              const num = (id) => { const n=document.getElementById(id); return parseFloat((n && n.value) ? n.value : '0'); };
              const js = (id) => { const n=document.getElementById(id); try { return JSON.parse((n && n.value) ? n.value : '{}'); } catch(e) { return {}; } };
              const cfg = { weights:{}, budget_ranges:{}, keywords:{ positive:{}, negative:{}, categories:{} }, seasonal_months:{} };
              cfg.weights.popularity_cap = num('w_popularity_cap');
              cfg.weights.kw_positive = num('w_kw_positive');
              cfg.weights.cat_positive = num('w_cat_positive');
              cfg.weights.seasonal = num('w_seasonal');
              cfg.weights.same_category = num('w_same_category');
              cfg.weights.upgrade_price_ratio_threshold = num('w_upgrade_ratio');
              cfg.weights.upgrade_price_boost = num('w_upgrade_price');
              cfg.weights.upgrade_label_boost = num('w_upgrade_label');
              cfg.weights.replacement_label_boost = num('w_replacement_label');
              cfg.weights.gift_set_boost = num('w_gift_set');
              cfg.weights.gift_price_boost = num('w_gift_price');
              cfg.weights.teacher_price_ceiling = num('w_teacher_ceiling');
              cfg.weights.teacher_price_boost = num('w_teacher_boost');
              cfg.weights.budget_proximity_mult = num('w_budget_mult');
              cfg.weights.neg_keyword_penalty = num('w_neg_penalty');
              cfg.weights.intent_badge_threshold = num('w_badge_threshold');
              cfg.budget_ranges.low = [ num('b_low_min'), num('b_low_max') ];
              cfg.budget_ranges.mid = [ num('b_mid_min'), num('b_mid_max') ];
              cfg.budget_ranges.high = [ num('b_high_min'), num('b_high_max') ];
              cfg.seasonal_months = js('t_seasonal');
              cfg.keywords.positive = js('t_kw_pos');
              cfg.keywords.negative = js('t_kw_neg');
              cfg.keywords.categories = js('t_cat_pos');
              return cfg;
            };

            const setDefaults = () => { setForm(Defaults); setStatus('Loaded defaults'); };
            const reload = async () => {
              try {
                setStatus('Loading current…');
                const data = await ApiClient.get('business_settings.php', { action: 'get_setting', key: 'cart_intent_heuristics' });
                const row = data && data.setting ? data.setting : null;
                if (!row || !row.setting_value) { setForm(Defaults); setStatus('Loaded defaults (no stored config)'); return; }
                let cfg = null; try { cfg = JSON.parse(row.setting_value); } catch(_) { cfg = null; }
                if (!cfg || typeof cfg !== 'object') { setForm(Defaults); setStatus('Invalid stored config, showing defaults'); return; }
                setForm(cfg); setStatus('Loaded current');
              } catch(_) { setForm(Defaults); setStatus('Failed to load, showing defaults', false); }
            };
            const save = async () => {
              try {
                const cfg = getForm();
                await ApiClient.post('business_settings.php?action=upsert_settings', { category: 'ecommerce', settings: { cart_intent_heuristics: cfg } });
                setStatus('Saved');
              } catch(_) { setStatus('Save failed', false); }
            };
            // JSON validation & Save guarding
            const saveBtn = inline.querySelector('#ih-save');
            const jsonIds = ['t_seasonal','t_kw_pos','t_kw_neg','t_cat_pos'];
            const validateJsonFields = () => {
              const invalid = [];
              jsonIds.forEach((id) => {
                const el = document.getElementById(id);
                if (!el) return;
                const val = el.value || '';
                let ok = true;
                try { if (val.trim() !== '') JSON.parse(val); } catch(e){ ok = false; }
                el.classList.toggle('border-red-500', !ok);
                el.classList.toggle('focus:ring-red-500', !ok);
                if (!ok) invalid.push(id);
              });
              if (saveBtn) saveBtn.disabled = invalid.length > 0;
              if (invalid.length > 0) setStatus('Invalid JSON in: ' + invalid.join(', '), false);
              else setStatus('');
              return invalid.length === 0;
            };
            jsonIds.forEach((id)=>{ const el=document.getElementById(id); if (el) el.addEventListener('input', validateJsonFields); });
            inline.querySelector('#ih-load-defaults')?.addEventListener('click', setDefaults);
            inline.querySelector('#ih-reload')?.addEventListener('click', reload);
            inline.querySelector('#ih-save')?.addEventListener('click', save);
            setDefaults();
            await reload();
            validateJsonFields();
            this.showOverlay(modalId);
            return;
        }
        // Fallback behavior (legacy iframe path)
        try {
            const isEmbed = (window.parent && window.parent !== window);
            if (isEmbed) {
                try { window.parent.postMessage({ source: 'wf-ai', type: 'open-tool', url: '/sections/tools/intent_heuristics_manager.php?modal=1', title: '🧠 Intent Heuristics Config' }, '*'); return; } catch(_) {}
            }
        } catch(_) {}
        try {
            const frame = document.getElementById('intentHeuristicsManagerFrame');
            if (frame) {
                const base = (frame.getAttribute('data-src') || frame.getAttribute('src') || '/sections/tools/intent_heuristics_manager.php?modal=1');
                frame.setAttribute('src', base);
            }
        } catch(_) {}
        this.showOverlay(modalId);
    },

    // --------- Marketing Overview (Charts) ---------
    openMarketingOverview() {
        const modalId = 'marketingOverviewModal';
        this.showOverlay(modalId);
        // Initialize charts using data embedded in page JSON
        try { this.initializeCharts(document); } catch (_) {}
    },

    // Generic small list manager UI
    renderListManager(kind, items, fields) {
        const rows = (items || []).map((it, idx) => {
            const inputs = fields.map(f => {
                const val = (it && it[f] != null) ? it[f] : '';
                const type = (f === 'value' || f === 'minTotal') ? 'number' : (f === 'expires' ? 'date' : (typeof val === 'boolean' || f === 'active' ? 'checkbox' : 'text'));
                const checked = (type === 'checkbox' && (val === true || String(val) === 'true')) ? 'checked' : '';
                const input = type === 'checkbox'
                  ? `<input data-kind="${kind}" data-idx="${idx}" data-field="${f}" type="checkbox" ${checked} class="form-checkbox">`
                  : `<input data-kind="${kind}" data-idx="${idx}" data-field="${f}" type="${type}" value="${String(val).replace(/"/g,'&quot;')}" class="admin-form-input">`;
                return `<label class="text-xs text-gray-600">${f}</label>${input}`;
            }).join('');
            return `<div class="rounded border p-3 space-y-1" data-row="${idx}">
                        <div class="grid md:grid-cols-${Math.min(3, fields.length)} gap-2">${inputs}</div>
                        <div class="flex justify-end"><button data-action="remove-${kind}" data-idx="${idx}" class="btn btn-secondary btn-sm">Remove</button></div>
                    </div>`;
        }).join('');
        const addButton = `<button data-action="add-${kind}" class="btn btn-primary btn-sm">Add ${kind}</button>`;
        const saveButton = `<button data-action="save-${kind}" class="btn btn-primary btn-sm">Save</button>`;
        return `<div class="space-y-2">${rows || '<div class="text-sm text-gray-600">No entries yet.</div>'}<div class="flex gap-2 justify-end mt-2">${addButton}${saveButton}</div></div>`;
    },
    bindListManager(kind, items, onSave) {
        const container = document.querySelector(`#${kind === 'discount' ? 'discountManagerContent' : kind === 'coupon' ? 'couponManagerContent' : kind === 'automation' ? 'automationManagerContent' : 'newsletterManagerContent'}`);
        if (!container) return;
        const getValue = (el) => {
            if (el.type === 'checkbox') return el.checked;
            if (el.type === 'number') return el.value === '' ? null : Number(el.value);
            return el.value;
        };
        const syncInputs = () => {
            container.querySelectorAll('[data-field]')?.forEach(input => {
                const idx = Number(input.getAttribute('data-idx'));
                const field = input.getAttribute('data-field');
                if (!items[idx]) items[idx] = {};
                items[idx][field] = getValue(input);
            });
        };
        container.addEventListener('click', async (ev) => {
            const t = ev.target.closest('button');
            if (!t) return;
            if (t.matches(`[data-action="add-${kind}"]`)) {
                ev.preventDefault();
                items.push({});
                container.innerHTML = this.renderListManager(kind, items, Array.from(new Set(Array.from(container.querySelectorAll('[data-field]')).map(e => e.getAttribute('data-field')))));
                this.bindListManager(kind, items, onSave);
                return;
            }
            if (t.matches(`[data-action="remove-${kind}"]`)) {
                ev.preventDefault();
                const idx = Number(t.getAttribute('data-idx'));
                items.splice(idx, 1);
                container.innerHTML = this.renderListManager(kind, items, Array.from(new Set(Array.from(container.querySelectorAll('[data-field]')).map(e => e.getAttribute('data-field')))));
                this.bindListManager(kind, items, onSave);
                return;
            }
            if (t.matches(`[data-action="save-${kind}"]`)) {
                ev.preventDefault();
                syncInputs();
                try { await onSave(items); 
                    if (typeof window.showAlertModal === 'function') { await window.showAlertModal({ title: 'Saved', message: 'Changes saved.' }); }
                    else { alert('Saved'); }
                } catch (e) {
                    if (typeof window.showAlertModal === 'function') { await window.showAlertModal({ title: 'Save Failed', message: 'Unable to save changes.', icon: '⚠️', iconType: 'warning' }); }
                    else { alert('Save failed'); }
                }
                return;
            }
        });
    },
    hideOverlay(id) {
        try {
            const el = document.getElementById(id);
            if (!el) return false;
            el.classList.remove('show');
            el.classList.add('hidden');
            el.setAttribute('aria-hidden', 'true');
            // Notify parent iframe autosizer that inner overlay closed
            try { if (window.parent && window.parent !== window) window.parent.postMessage({ source: 'wf-embed-size', overlay: false }, '*'); } catch(_) {}
            return true;
        } catch (_) { return false; }
    },

    init() {
        const marketingPage = document.querySelector('.admin-marketing-page');
        if (!marketingPage) {
            return; // Only run on the marketing page
        }

        this.bindEventListeners();
        this.initializeCharts(marketingPage);

        // Optional deep-link support to open tools via query param (e.g., ?wf_open=intent)
        try {
            const qp = new URLSearchParams(window.location.search || '');
            const key = (qp.get('wf_open') || qp.get('open') || '').toLowerCase();
            const map = {
              'suggestions': () => this.openSuggestionsManager(),
              'social': () => this.openSocialManager(),
              'automation': () => this.openAutomationManager(),
              'content': () => this.openContentGenerator(),
              'newsletters': () => this.openNewslettersManager(),
              'discounts': () => this.openDiscountsManager(),
              'coupons': () => this.openCouponsManager(),
              'intent': () => this.openIntentHeuristicsManager()
            };
            if (map[key]) map[key]();
        } catch(_) {}
    },

    bindEventListeners() {
        document.body.addEventListener('click', (e) => {
            const target = e.target;

            const overlayEl = target.closest('.admin-modal-overlay');
            if (overlayEl && !target.closest('.admin-modal')) {
                e.preventDefault();
                if (overlayEl.id) {
                    this.hideOverlay(overlayEl.id);
                } else {
                    // Fallback: close known marketing overlays
                    ['socialManagerModal','newsletterManagerModal','automationManagerModal','discountManagerModal','couponManagerModal','suggestionsManagerModal','contentGeneratorModal','intentHeuristicsManagerModal','marketingOverviewModal']
                        .forEach(id => this.hideOverlay(id));
                }
                return;
            }

            // Tool section toggles
            const toolButton = target.closest('[data-tool]');
            if (toolButton) {
                this.showMarketingTool(toolButton.dataset.tool);
                return;
            }

            // Form toggles
            const toggleBtn = target.closest('[data-toggle-form]');
            if (toggleBtn) {
                const formId = toggleBtn.dataset.toggleForm;
                this.toggleForm(formId);
                return;
            }

            // Generate discount code
            if (target.closest('#generateDiscountBtn')) {
                this.generateDiscountCode();
                return;
            }
            
            // Initialize tables
            if (target.closest('#initMarketingTablesBtn')) {
                this.initializeMarketingTables();
                return;
            }

            // Sub-modal openers
            const isEmbed = (() => { try { return window.parent && window.parent !== window; } catch(_) { return false; } })();
            const tryOpenParent = (url, title) => {
                try {
                    if (!isEmbed) return false;
                    window.parent.postMessage({ source: 'wf-ai', type: 'open-tool', url, title }, '*');
                    return true;
                } catch(_) { return false; }
            };

            if (target.closest('[data-action="open-social-manager"]')) {
                e.preventDefault();
                if (!tryOpenParent('/sections/tools/social_manager.php?modal=1', '📱 Social Accounts Manager')) {
                    this.openSocialManager();
                }
                return;
            }
            if (target.closest('[data-action="open-content-generator"]')) {
                e.preventDefault();
                if (!tryOpenParent('/sections/tools/ai_content_generator.php?modal=1', '✍️ Content Generator')) {
                    this.openContentGenerator();
                }
                return;
            }
            if (target.closest('[data-action="open-newsletters-manager"]')) {
                e.preventDefault();
                if (!tryOpenParent('/sections/tools/newsletters_manager.php?modal=1', '📧 Newsletter Manager')) {
                    this.openNewslettersManager();
                }
                return;
            }
            if (target.closest('[data-action="open-automation-manager"]')) {
                e.preventDefault();
                if (!tryOpenParent('/sections/tools/automation_manager.php?modal=1', '⚙️ Automation Manager')) {
                    this.openAutomationManager();
                }
                return;
            }
            if (target.closest('[data-action="open-marketing-overview"]')) {
                e.preventDefault();
                this.openMarketingOverview();
                return;
            }
            if (target.closest('[data-action="open-discounts-manager"]')) {
                e.preventDefault();
                if (!tryOpenParent('/sections/tools/discounts_manager.php?modal=1', '💸 Discount Codes Manager')) {
                    this.openDiscountsManager();
                }
                return;
            }
            if (target.closest('[data-action="open-coupons-manager"]')) {
                e.preventDefault();
                if (!tryOpenParent('/sections/tools/coupons_manager.php?modal=1', '🎟️ Coupons Manager')) {
                    this.openCouponsManager();
                }
                return;
            }
            if (target.closest('[data-action="open-intent-heuristics-manager"]')) {
                e.preventDefault();
                if (!tryOpenParent('/sections/tools/intent_heuristics_manager.php?modal=1', '🧠 Intent Heuristics Config')) {
                    this.openIntentHeuristicsManager();
                }
                return;
            }
            if (target.closest('[data-action="open-ai-provider-parent"]')) {
                e.preventDefault();
                try { if (window.parent && window.parent !== window) { window.parent.postMessage({ source: 'wf-ai', type: 'open-provider' }, '*'); } } catch(_) {}
                return;
            }
            if (target.closest('[data-action="open-suggestions-manager"]')) {
                e.preventDefault();
                if (!tryOpenParent('/sections/tools/ai_suggestions.php?modal=1', '🤖 Suggestions Manager')) {
                    this.openSuggestionsManager();
                }
                return;
            }

            // Refresh social accounts in-place
            if (target.closest('[data-action="refresh-social-accounts"]')) {
                e.preventDefault();
                this.loadSocialAccounts();
                return;
            }

            // Generate suggestions via data-action
            if (target.closest('[data-action="generate-suggestions"]')) {
                e.preventDefault();
                if (typeof window.generateSuggestions === 'function') {
                    window.generateSuggestions();
                } else {
                    this.generateSuggestionsFallback();
                }
                return;
            }

            // Timeframe switcher
            const tfBtn = target.closest('[data-timeframe]');
            if (tfBtn) {
                e.preventDefault();
                const tf = parseInt(tfBtn.getAttribute('data-timeframe'), 10) || 7;
                this.setTimeframeActive(tf);
                this.fetchOverview(tf).then((data) => {
                    if (data && data.success) this.applyOverviewData(data);
                }).catch(()=>{});
                return;
            }

            // Close any iframe-local admin modal
            if (target.closest('.admin-modal-close,[data-action="close-admin-modal"]')) {
                const overlay = target.closest('.admin-modal-overlay');
                if (overlay && overlay.id) {
                    this.hideOverlay(overlay.id);
                    return;
                }
                // Fallback: close all if specific overlay not found
                ['socialManagerModal','newsletterManagerModal','automationManagerModal','discountManagerModal','couponManagerModal','suggestionsManagerModal']
                    .forEach(id => this.hideOverlay(id));
                return;
            }
        });
    },

    showMarketingTool(toolId) {
        document.querySelectorAll('.marketing-tool-section').forEach(section => {
            section.classList.add('hidden');
        });
        const activeSection = document.getElementById(`${toolId}-section`);
        if (activeSection) {
            activeSection.classList.remove('hidden');
        }
        
        // Load specific tool data
        if (toolId === 'social-media') {
            this.loadSocialAccounts();
        }
    },

    async loadSocialAccounts() {
        try {
            const data = await ApiClient.get('/api/get_social_accounts.php');
            
            const container = document.getElementById('social-accounts-list');
            if (data.success && data.accounts) {
                container.innerHTML = this.renderSocialAccounts(data.accounts);
            } else {
                container.innerHTML = '<p class="text-gray-600">No social accounts connected.</p>';
            }
        } catch (error) {
            console.error('Error loading social accounts:', error);
        }
    },

    async openSocialManager() {
        try {
            const data = await ApiClient.get('/api/get_social_accounts.php');
            const modalId = 'socialManagerModal';
            const content = document.getElementById('socialManagerContent');
            if (content) {
                if (data.success && data.accounts) {
                    content.innerHTML = this.renderSocialAccounts(data.accounts);
                } else {
                    content.innerHTML = '<p class="text-gray-600">No social accounts connected.</p>';
                }
            }
            this.showOverlay(modalId);
        } catch (err) {
            console.error('Error opening social manager:', err);
            this.showOverlay('socialManagerModal');
        }
    },

    renderSocialAccounts(accounts) {
        return accounts.map(account => `
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded mb-2">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm">
                        ${account.platform.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="font-medium">${account.platform}</div>
                        <div class="text-sm text-gray-600">@${account.account_name}</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs px-2 py-1 rounded ${account.connected ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                        ${account.connected ? 'Connected' : 'Disconnected'}
                    </span>
                    <button onclick="AdminMarketingModule.manageSocialAccount('${account.id}')" class="btn btn-sm btn-secondary">
                        Manage
                    </button>
                </div>
            </div>
        `).join('');
    },

    async manageSocialAccount(_accountId) {
        // Open social account management modal
        if (typeof window.showAlertModal === 'function') {
            await window.showAlertModal({ title: 'Coming Soon', message: 'Social account management is coming soon.' });
        } else { alert('Social account management coming soon!'); }
    },

    toggleForm(formId) {
        const form = document.getElementById(formId);
        if (form) {
            form.classList.toggle('hidden');
        }
    },

    generateDiscountCode() {
        const codeInput = document.getElementById('newDiscountCode');
        if (codeInput) {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let code = '';
            for (let i = 0; i < 8; i++) {
                code += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            codeInput.value = code;
        }
    },

    initializeCharts(container) {
        const chartDataEl = (container && container.querySelector) ? (container.querySelector('#marketingChartData') || document.getElementById('marketingChartData')) : document.getElementById('marketingChartData');
        if (!chartDataEl) return;

        try {
            const data = JSON.parse(chartDataEl.textContent);
            this.applyOverviewData(data);
        } catch (e) {
            console.error('Failed to parse marketing chart data:', e);
        }

        // Also fetch live data for default timeframe (7d) to ensure freshness
        this.setTimeframeActive(7);
        this.fetchOverview(7).then((data)=>{ if (data && data.success) this.applyOverviewData(data); }).catch(()=>{});
    },

    setTimeframeActive(tf){
        try{
            document.querySelectorAll('[data-timeframe]')?.forEach(btn=>{
                const v = parseInt(btn.getAttribute('data-timeframe'), 10) || 7;
                btn.classList.toggle('btn-primary', v === tf);
                btn.classList.toggle('btn-secondary', v !== tf);
            });
            this.currentTimeframe = tf;
            this.updateExportLinks(tf);
        }catch(_){ }
    },

    updateExportLinks(tf){
        try{
            document.querySelectorAll('a[data-export-type]')?.forEach(a=>{
                const type = a.getAttribute('data-export-type');
                if (!type) return;
                const url = new URL('/api/marketing_overview.php', window.location.origin);
                url.searchParams.set('format','csv');
                url.searchParams.set('type', type);
                url.searchParams.set('timeframe', String(tf||7));
                a.setAttribute('href', url.pathname + '?' + url.searchParams.toString());
            });
        }catch(_){ }
    },

    async fetchOverview(tf){
        try{
            const res = await ApiClient.get('/api/marketing_overview.php', { timeframe: tf });
            return res;
        }catch(e){ console.error('Failed to fetch overview', e); return null; }
    },

    applyOverviewData(data){
        try{
            if (data.sales) this.renderSalesChart(data.sales);
            if (data.payments) this.renderPaymentChart(data.payments);
            if (data.topCategories) this.renderTopCategoriesChart(data.topCategories);
            if (data.topProducts) this.renderTopProductsChart(data.topProducts);
            if (data.status) this.renderOrderStatusChart(data.status);
            if (data.newReturning) this.renderNewReturningChart(data.newReturning);
            if (data.shipping) this.renderShippingMethodChart(data.shipping);
            if (data.aovTrend) this.renderAovTrendChart(data.aovTrend);
            if (data.channels) this.renderChannelsChart(data.channels);
            if (data.channelRevenue) this.renderChannelRevenueChart(data.channelRevenue);
            if (data.kpis) this.updateKpis(data.kpis);
        }catch(e){ console.error('Failed to apply overview data', e); }
    },

    updateKpis(kpis){
        try{
            const revEl = document.getElementById('kpiRevenue');
            const ordEl = document.getElementById('kpiOrders');
            const aovEl = document.getElementById('kpiAov');
            const custEl = document.getElementById('kpiCustomers');
            if (revEl && typeof kpis.revenue === 'number') revEl.textContent = Number(kpis.revenue).toFixed(2);
            if (ordEl && typeof kpis.orders === 'number') ordEl.textContent = String(kpis.orders);
            if (aovEl && typeof kpis.aov === 'number') aovEl.textContent = Number(kpis.aov).toFixed(2);
            if (custEl && typeof kpis.customers === 'number') custEl.textContent = String(kpis.customers);
        }catch(_){ }
    },

    renderSalesChart(data) {
        const ctx = document.getElementById('salesChart')?.getContext('2d');
        if (!ctx) return;

        if (this.salesChartInstance) this.salesChartInstance.destroy();
        this.salesChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Sales',
                    data: data.values,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: { 
                responsive: true, maintainAspectRatio: false,
                onClick: (evt, _els, chart)=>{
                    const label = this.getClickedLabel(chart, evt);
                    if (label) this.goToOrdersWithQuery({ filter_date: label });
                }
            }
        });
    },

    renderChannelsChart(data) {
        const ctx = document.getElementById('channelsChart')?.getContext('2d');
        if (!ctx) return;
        if (this.channelsChartInstance) this.channelsChartInstance.destroy();
        this.channelsChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Sessions by Channel',
                    data: data.values,
                    backgroundColor: ['#60A5FA','#10B981','#F59E0B','#EF4444','#8B5CF6','#6B7280']
                }]
            },
            options: { 
                responsive: true, maintainAspectRatio: false,
                onClick: (evt, _els, chart)=>{
                    const label = this.getClickedLabel(chart, evt);
                    if (label) this.goToOrdersWithQuery({ filter_channel: label });
                }
            }
        });
    },

    renderChannelRevenueChart(data) {
        const ctx = document.getElementById('channelRevenueChart')?.getContext('2d');
        if (!ctx) return;
        if (this.channelRevenueChartInstance) this.channelRevenueChartInstance.destroy();
        this.channelRevenueChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Revenue by Channel',
                    data: data.values,
                    backgroundColor: '#34D399'
                }]
            },
            options: { 
                responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } },
                onClick: (evt, _els, chart)=>{
                    const label = this.getClickedLabel(chart, evt);
                    if (label) this.goToOrdersWithQuery({ filter_channel: label });
                }
            }
        });
    },

    renderPaymentChart(data) {
        const ctx = document.getElementById('paymentMethodChart')?.getContext('2d');
        if (!ctx) return;

        if (this.paymentChartInstance) this.paymentChartInstance.destroy();
        this.paymentChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Payment Methods',
                    data: data.values,
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
                    ]
                }]
            },
            options: { 
                responsive: true, maintainAspectRatio: false,
                onClick: (evt, _els, chart)=>{
                    const label = this.getClickedLabel(chart, evt);
                    if (label) this.goToOrdersWithQuery({ filter_payment_method: label });
                }
            }
        });
    },
    
    renderTopCategoriesChart(data) {
        const ctx = document.getElementById('topCategoriesChart')?.getContext('2d');
        if (!ctx) return;
        if (this.topCategoriesChartInstance) this.topCategoriesChartInstance.destroy();
        this.topCategoriesChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Revenue by Category',
                    data: data.values,
                    backgroundColor: '#60A5FA'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
                onClick: (evt, _els, chart)=>{
                    const label = this.getClickedLabel(chart, evt);
                    if (label) this.goToOrdersWithQuery({ filter_items: label });
                }
            }
        });
    },

    renderTopProductsChart(data) {
        const ctx = document.getElementById('topProductsChart')?.getContext('2d');
        if (!ctx) return;
        if (this.topProductsChartInstance) this.topProductsChartInstance.destroy();
        this.topProductsChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Revenue by Product',
                    data: data.values,
                    backgroundColor: '#34D399'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
                onClick: (evt, _els, chart)=>{
                    const label = this.getClickedLabel(chart, evt);
                    if (label) this.goToOrdersWithQuery({ filter_items: label });
                }
            }
        });
    },

    renderOrderStatusChart(data) {
        const ctx = document.getElementById('orderStatusChart')?.getContext('2d');
        if (!ctx) return;
        if (this.orderStatusChartInstance) this.orderStatusChartInstance.destroy();
        this.orderStatusChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Orders by Status',
                    data: data.values,
                    backgroundColor: ['#F59E0B','#10B981','#3B82F6','#EF4444','#8B5CF6','#6B7280']
                }]
            },
            options: { 
                responsive: true, maintainAspectRatio: false,
                onClick: (evt, _els, chart)=>{
                    const label = this.getClickedLabel(chart, evt);
                    if (label) this.goToOrdersWithQuery({ filter_status: label });
                }
            }
        });
    },

    renderNewReturningChart(data) {
        const ctx = document.getElementById('newReturningChart')?.getContext('2d');
        if (!ctx) return;
        if (this.newReturningChartInstance) this.newReturningChartInstance.destroy();
        this.newReturningChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Customers',
                    data: data.values,
                    backgroundColor: ['#60A5FA','#10B981']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    },

    renderShippingMethodChart(data) {
        const ctx = document.getElementById('shippingMethodChart')?.getContext('2d');
        if (!ctx) return;
        if (this.shippingChartInstance) this.shippingChartInstance.destroy();
        this.shippingChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Shipping Methods',
                    data: data.values,
                    backgroundColor: ['#34D399','#FBBF24','#A78BFA','#F87171','#93C5FD','#FDBA74']
                }]
            },
            options: { 
                responsive: true, maintainAspectRatio: false,
                onClick: (evt, _els, chart)=>{
                    const label = this.getClickedLabel(chart, evt);
                    if (label) this.goToOrdersWithQuery({ filter_shipping_method: label });
                }
            }
        });
    },

    renderAovTrendChart(data) {
        const ctx = document.getElementById('aovTrendChart')?.getContext('2d');
        if (!ctx) return;
        if (this.aovTrendChartInstance) this.aovTrendChartInstance.destroy();
        this.aovTrendChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'AOV',
                    data: data.values,
                    borderColor: '#8B5CF6',
                    backgroundColor: 'rgba(139, 92, 246, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: { 
                responsive: true, maintainAspectRatio: false,
                onClick: (evt, _els, chart)=>{
                    const label = this.getClickedLabel(chart, evt);
                    if (label) this.goToOrdersWithQuery({ filter_date: label });
                }
            }
        });
    },
    
    async initializeMarketingTables() {
        // This function would contain the logic to create marketing tables via an API call.
        // For now, it just shows an alert as a placeholder.
        if (typeof window.showAlertModal === 'function') {
            await window.showAlertModal({ title: 'Initializing', message: 'Initializing marketing tables...' });
        } else { alert('Initializing marketing tables...'); }
    }
    ,
    async generateSuggestionsFallback() {
        const skuInput = document.getElementById('suggestion-sku');
        const sku = skuInput ? skuInput.value : '';
        const resultDiv = document.getElementById('suggestions-result');
        if (!resultDiv) return;
        if (!sku) {
            if (typeof window.showAlertModal === 'function') {
                await window.showAlertModal({ title: 'Missing SKU', message: 'Please enter a product SKU.' });
            } else { alert('Please enter a product SKU'); }
            return;
        }
        resultDiv.innerHTML = '<div class="text-center">Generating AI suggestions...</div>';
        resultDiv.classList.remove('hidden');
        try {
            const data = await window.ApiClient.post('/api/suggest_marketing.php', { sku });
            if (data.success) {
                resultDiv.innerHTML = `
                    <h4 class="font-medium mb-2">AI Suggestions for ${sku}</h4>
                    <div class="space-y-2">
                        <div><strong>Title:</strong> ${data.suggested_title || 'N/A'}</div>
                        <div><strong>Description:</strong> ${data.suggested_description || 'N/A'}</div>
                        <div><strong>Keywords:</strong> ${data.seo_keywords ? JSON.parse(data.seo_keywords).join(', ') : 'N/A'}</div>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = '<div class="text-red-600">Error: ' + (data.error || 'Failed to generate suggestions') + '</div>';
            }
        } catch (error) {
            resultDiv.innerHTML = '<div class="text-red-600">Network error occurred</div>';
        }
    }
};

// Ensure the module is available to inline helpers in the iframe
try { if (typeof window !== 'undefined') { window.AdminMarketingModule = AdminMarketingModule; } } catch(_) {}

// Robust init: if DOM is already ready (common in iframes), run immediately
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => { try { AdminMarketingModule.init(); } catch(_) {} });
} else {
    try { AdminMarketingModule.init(); } catch(_) {}
}

// When embedded in an admin modal (iframe + ?modal=1), emit size to parent for responsive height
(() => {
  try {
    const isEmbed = (window.parent && window.parent !== window);
    const isModalCtx = /[?&]modal=1\b/.test(String(window.location.search || ''));
    if (!isEmbed || !isModalCtx) return;
    // Break any 100%/100vh coupling: in modal context, force intrinsic heights
    try {
      const s = document.createElement('style');
      s.id = 'wf-marketing-modal-height-reset';
      s.textContent = `
        /* Break viewport coupling for marketing page when embedded */
        html, body { height: auto !important; min-height: auto !important; overflow: visible !important; }
        body[data-page='admin/marketing'] { height: auto !important; min-height: auto !important; overflow: visible !important; }
        html:has(body[data-page='admin/marketing']) { height: auto !important; overflow: visible !important; }
        .admin-marketing-page { height: auto !important; min-height: 0 !important; }
      `;
      document.head.appendChild(s);
    } catch(_) {}
    const pickContentNode = () => {
      try {
        const root = document.querySelector('.admin-marketing-page');
        if (!root) return document.body;
        // Prefer a single main card container within the marketing page
        const card = root.querySelector(':scope > .admin-card') || root.querySelector('.admin-card');
        return card || root;
      } catch(_) { return document.body; }
    };
    const outerHeight = (el) => {
      try {
        const r = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        const mt = parseFloat(cs.marginTop)||0, mb = parseFloat(cs.marginBottom)||0;
        return Math.ceil((r.height||0) + mt + mb);
      } catch(_) { return 0; }
    };
    const emit = () => {
      try {
        const node = pickContentNode();
        // Measure a stable content container; avoid viewport-coupled values
        let h = outerHeight(node);
        if (!h || h < 1) {
          const root = document.querySelector('.admin-marketing-page') || document.body;
          h = Math.ceil(root.scrollHeight || 0);
        }
        h = Math.max(0, h);
        try { if (window.__WF_DEBUG) console.debug('[wf-embed-size] child ->', h); } catch(_) {}
        window.parent.postMessage({ source: 'wf-embed-size', height: h }, '*');
      } catch(_) {}
    };
    try { window.addEventListener('load', emit, { once: false }); } catch(_) {}
    try { emit(); } catch(_) {}
    try {
      const target = pickContentNode();
      const ro = new ResizeObserver(() => emit());
      ro.observe(target);
      window.__wfMarketingRO = ro;
    } catch(_) { setInterval(emit, 1000); }
  } catch(_) {}
})();
