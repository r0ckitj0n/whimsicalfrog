import Chart from 'chart.js/auto';
import { ApiClient } from '../core/api-client.js';

const AdminMarketingModule = {
    salesChartInstance: null,
    paymentChartInstance: null,
    // Simple modal helpers for iframe-local admin modals
    showOverlay(id) {
        try {
            const el = document.getElementById(id);
            if (!el) return false;
            if (el.parentElement && el.parentElement !== document.body) {
                document.body.appendChild(el);
            }
            el.classList.remove('hidden');
            el.classList.add('show');
            el.setAttribute('aria-hidden', 'false');
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
            if (!current) { alert('Please choose an item'); return; }
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
                if (!opt || !opt.value) { alert('Please choose an item'); return; }
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
                try { await onSave(items); alert('Saved'); } catch (e) { alert('Save failed'); }
                return;
            }
        });
    },
    hideOverlay(id) {
        try {
            const el = document.getElementById(id);
            if (!el) return false;
            el.classList.add('hidden');
            el.classList.remove('show');
            el.setAttribute('aria-hidden', 'true');
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

        // Deep-link support: open the appropriate manager via ?tool=...
        try {
            const params = new URLSearchParams(window.location.search || '');
            const tool = (params.get('tool') || '').toLowerCase();
            if (tool) {
                switch (tool) {
                    case 'social-media':
                        this.openSocialManager();
                        break;
                    case 'newsletters':
                        this.openNewslettersManager();
                        break;
                    case 'automation':
                        this.openAutomationManager();
                        break;
                    case 'discounts':
                        this.openDiscountsManager();
                        break;
                    case 'coupons':
                        this.openCouponsManager();
                        break;
                    case 'suggestions':
                        if (typeof this.openSuggestionsManager === 'function') { this.openSuggestionsManager(); }
                        else { this.showOverlay('suggestionsManagerModal'); }
                        break;
                    case 'content':
                        this.openContentGenerator();
                        break;
                    default:
                        break;
                }
            }
        } catch (_) {}
    },

    bindEventListeners() {
        document.body.addEventListener('click', (e) => {
            const target = e.target;

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
            if (target.closest('[data-action="open-social-manager"]')) {
                e.preventDefault();
                this.openSocialManager();
                return;
            }
            if (target.closest('[data-action="open-content-generator"]')) {
                e.preventDefault();
                this.openContentGenerator();
                return;
            }
            if (target.closest('[data-action="open-newsletters-manager"]')) {
                e.preventDefault();
                this.openNewslettersManager();
                return;
            }
            if (target.closest('[data-action="open-automation-manager"]')) {
                e.preventDefault();
                this.openAutomationManager();
                return;
            }
            if (target.closest('[data-action="open-discounts-manager"]')) {
                e.preventDefault();
                this.openDiscountsManager();
                return;
            }
            if (target.closest('[data-action="open-coupons-manager"]')) {
                e.preventDefault();
                this.openCouponsManager();
                return;
            }
            if (target.closest('[data-action="open-suggestions-manager"]')) {
                e.preventDefault();
                this.openSuggestionsManager();
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
        alert('Social account management coming soon!');
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
        const chartDataEl = container.querySelector('#marketingChartData');
        if (!chartDataEl) return;

        try {
            const data = JSON.parse(chartDataEl.textContent);
            this.renderSalesChart(data.sales);
            this.renderPaymentChart(data.payments);
        } catch (e) {
            console.error('Failed to parse marketing chart data:', e);
        }
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
            options: { responsive: true, maintainAspectRatio: false }
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
            options: { responsive: true, maintainAspectRatio: false }
        });
    },
    
    async initializeMarketingTables() {
        // This function would contain the logic to create marketing tables via an API call.
        // For now, it just shows an alert as a placeholder.
        alert('Initializing marketing tables...');
    }
    ,
    async generateSuggestionsFallback() {
        const skuInput = document.getElementById('suggestion-sku');
        const sku = skuInput ? skuInput.value : '';
        const resultDiv = document.getElementById('suggestions-result');
        if (!resultDiv) return;
        if (!sku) {
            alert('Please enter a product SKU');
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
