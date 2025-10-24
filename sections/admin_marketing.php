<?php
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/includes/vite_helper.php';

// Detect modal context
$isModal = (isset($_GET['modal']) && $_GET['modal'] == '1');

// When not in modal, include full admin layout and navbar
if (!$isModal) {
    if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
        $page = 'admin';
        include dirname(__DIR__) . '/partials/header.php';
        if (!function_exists('__wf_admin_marketing_footer_shutdown')) {
            function __wf_admin_marketing_footer_shutdown()
            {
                @include __DIR__ . '/../partials/footer.php';
            }
        }
        register_shutdown_function('__wf_admin_marketing_footer_shutdown');
    }
    // Always include admin navbar on marketing page when not embedded in a modal
    $section = 'marketing';
    include_once dirname(__DIR__) . '/components/admin_nav_tabs.php';
}

$pdo = Database::getInstance();

// Get marketing suggestions count with error handling
$suggestionCount = 0;
try {
    $result = Database::queryOne("SELECT COUNT(*) as count FROM marketing_suggestions");
    $suggestionCount = $result['count'] ?? 0;
} catch (PDOException $e) {
    // Table might not exist yet
    error_log("Marketing suggestions table not found: " . $e->getMessage());
}
?>

<style>
body[data-page='admin/marketing'] #admin-section-content {
    margin-top: 0 !important;
    border-top: none !important;
}
.admin-marketing-page {
    margin-top: 0 !important;
}
/* Ensure elements with class 'hidden' are truly hidden inside this iframe */
.hidden { display: none !important; }
</style>

<div class="admin-marketing-page">
    
    <!-- Overview (intro removed; container neutralized to preserve structure around sub-modals) -->
    <div class="admin-card mb-0">
        <!-- intro removed per request; keep a tiny placeholder to avoid mis-nesting -->
        <div class="hidden"></div>

    <!-- Sub-modals inside iframe -->
    <div id="socialManagerModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="socialManagerTitle">
        <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
            <div class="modal-header">
                <h2 id="socialManagerTitle" class="admin-card-title">📱 Social Accounts Manager</h2>
                <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <div id="socialManagerContent" class="space-y-2 text-sm text-gray-700">Loading accounts…</div>
            </div>
        </div>
    </div>

    <div id="newsletterManagerModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="newsletterManagerTitle">
        <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
            <div class="modal-header">
                <h2 id="newsletterManagerTitle" class="admin-card-title">📧 Newsletter Manager</h2>
                <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <div id="newsletterManagerContent" class="space-y-3 text-sm text-gray-700">Loading newsletters…</div>
            </div>
        </div>
    </div>

    <div id="automationManagerModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="automationManagerTitle">
        <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
            <div class="modal-header">
                <h2 id="automationManagerTitle" class="admin-card-title">⚙️ Automation Manager</h2>
                <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <div id="automationManagerContent" class="space-y-3 text-sm text-gray-700">Loading automations…</div>
            </div>
        </div>
    </div>

    <div id="discountManagerModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="discountManagerTitle">
        <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
            <div class="modal-header">
                <h2 id="discountManagerTitle" class="admin-card-title">💸 Discount Codes Manager</h2>
                <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <div id="discountManagerContent" class="space-y-3 text-sm text-gray-700">Loading discounts…</div>
            </div>
        </div>
    </div>

    <div id="couponManagerModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="couponManagerTitle">
        <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
            <div class="modal-header">
                <h2 id="couponManagerTitle" class="admin-card-title">🎟️ Coupons Manager</h2>
                <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <div id="couponManagerContent" class="space-y-3 text-sm text-gray-700">Loading coupons…</div>
            </div>
        </div>
    </div>

    <div id="suggestionsManagerModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="suggestionsManagerTitle">
        <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
            <div class="modal-header">
                <h2 id="suggestionsManagerTitle" class="admin-card-title">🤖 Suggestions Manager</h2>
                <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <div id="suggestionsManagerContent" class="text-sm text-gray-700">View and curate AI suggestions. (Coming soon)</div>
            </div>
        </div>
    </div>

    <div id="contentGeneratorModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="contentGeneratorTitle">
        <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
            <div class="modal-header">
                <h2 id="contentGeneratorTitle" class="admin-card-title">✍️ Content Generator</h2>
                <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <div id="contentGeneratorContent" class="space-y-3 text-sm text-gray-700">Loading content generator…</div>
            </div>
        </div>
    </div>

</div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="admin-card text-center">
            <div class="text-2xl font-bold text-blue-600"><?= $suggestionCount ?></div>
            <div class="text-sm text-gray-600">AI Suggestions</div>
        </div>
        <div class="admin-card text-center">
            <div class="text-2xl font-bold text-green-600">3</div>
            <div class="text-sm text-gray-600">Campaigns</div>
        </div>
        <div class="admin-card text-center">
            <div class="text-2xl font-bold text-purple-600">2.4%</div>
            <div class="text-sm text-gray-600">Conversion</div>
        </div>
        <div class="admin-card text-center">
            <div class="text-2xl font-bold text-orange-600">15</div>
            <div class="text-sm text-gray-600">Emails Sent</div>
        </div>
    </div>

    <!-- Tools (single list of categories with sub-boxes) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="admin-card">
            <h3 class="admin-card-title">🤖 AI Tools</h3>
            <div class="grid gap-3">
                <div class="border rounded p-3">
                    <div class="font-medium">Item Suggestions</div>
                    <div class="text-sm text-gray-600 mb-2">Generate AI content, price, and cost for an item, review/edit, then apply.</div>
                    <div class="flex gap-2">
                        <button data-action="open-suggestions-manager" class="btn btn-primary">Open Manager</button>
                    </div>
                </div>
                <div class="border rounded p-3">
                    <div class="font-medium">Content Generator</div>
                    <div class="text-sm text-gray-600 mb-2">Create AI-assisted marketing content.</div>
                    <button data-action="open-content-generator" class="btn btn-secondary">Open</button>
                </div>
                <div class="border rounded p-3">
                    <div class="font-medium">Social Media</div>
                    <div class="text-sm text-gray-600 mb-2">Connect accounts and manage posts.</div>
                    <button data-action="open-social-manager" class="btn btn-secondary">Open Accounts</button>
                </div>
            </div>
        </div>
        <div class="admin-card">
            <h3 class="admin-card-title">📧 Email Marketing</h3>
            <div class="grid gap-3">
                <div class="border rounded p-3">
                    <div class="font-medium">Newsletters</div>
                    <div class="text-sm text-gray-600 mb-2">Create, schedule, and review newsletters.</div>
                    <button data-action="open-newsletters-manager" class="btn btn-primary">Open Manager</button>
                </div>
                <div class="border rounded p-3">
                    <div class="font-medium">Automation</div>
                    <div class="text-sm text-gray-600 mb-2">Set up flows and triggers.</div>
                    <button data-action="open-automation-manager" class="btn btn-secondary">Open Manager</button>
                </div>
            </div>
        </div>
        <div class="admin-card">
            <h3 class="admin-card-title">💰 Promotions</h3>
            <div class="grid gap-3">
                <div class="border rounded p-3">
                    <div class="font-medium">Discount Codes</div>
                    <div class="text-sm text-gray-600 mb-2">Generate and manage discount codes.</div>
                    <button data-action="open-discounts-manager" class="btn btn-primary">Open Manager</button>
                </div>
                <div class="border rounded p-3">
                    <div class="font-medium">Coupons</div>
                    <div class="text-sm text-gray-600 mb-2">Create printable or digital coupons.</div>
                    <button data-action="open-coupons-manager" class="btn btn-secondary">Open Manager</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Intro moved to bottom -->
    <div class="mt-8 text-sm text-gray-700 space-y-2">
        <p><strong>AI Tools</strong> help generate item suggestions, content, and manage social media. Use these to speed up marketing tasks and keep your catalog fresh.</p>
        <p><strong>Email Marketing</strong> handles newsletters and automated sequences so you can nurture customers and announce new items.</p>
        <p><strong>Promotions</strong> provides discount codes and coupons to drive conversions and reward loyal shoppers.</p>
        <p>Select a tool below to view its details and available actions.</p>
    </div>
</div>

<script>
// Global functions for marketing tools
async function __wfApiRequest(method, url, data=null, options={}){
    try {
        const A = (typeof window !== 'undefined') ? (window.ApiClient || null) : null;
        const m = String(method||'GET').toUpperCase();
        if (A && typeof A.request === 'function') {
            if (m === 'GET') return A.get(url, (options && options.params) || {});
            if (m === 'POST') return A.post(url, data||{}, options||{});
            if (m === 'PUT') return A.put(url, data||{}, options||{});
            if (m === 'DELETE') return A.delete(url, options||{});
            return A.request(url, { method: m, ...(options||{}) });
        }
        const headers = { 'Content-Type': 'application/json', 'X-WF-ApiClient': '1', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers||{}) };
        const cfg = { credentials:'include', method:m, headers, ...(options||{}) };
        if (data !== null && typeof cfg.body === 'undefined') cfg.body = JSON.stringify(data);
        const res = await fetch(url, cfg);
        return res.json().catch(()=>({}));
    } catch(_) { return {}; }
}
const __wfApiGet = (url, params) => __wfApiRequest('GET', url, null, { params });
const __wfApiPost = (url, body, options) => __wfApiRequest('POST', url, body, options);
async function generateSuggestions() {
    const skuInput = document.getElementById('suggestion-sku');
    if (!skuInput) {
        alert('Item SKU selector not found on page.');
        return;
    }
    const sku = (skuInput.value || '').trim();
    if (!sku) {
        alert('Please select an Item SKU');
        return;
    }

    const resultDiv = document.getElementById('suggestions-result');
    if (!resultDiv) return;
    resultDiv.innerHTML = '<div class="text-center">Generating AI suggestions...</div>';
    resultDiv.classList.remove('hidden');

    try {
        // suggest_marketing currently requires a non-empty name; use SKU as a minimal name fallback
        const data = await __wfApiPost('/api/suggest_marketing.php', { sku, name: sku, description: '', category: '' });
        if (data && data.success === false) {
            const msg = (data && (data.error || data.message)) || 'Request failed';
            resultDiv.innerHTML = `<div class="text-red-600">Error: ${msg}</div>`;
            return;
        }

        const title = data.title || data.suggested_title || 'N/A';
        const description = data.description || data.suggested_description || 'N/A';
        let keywords = [];
        if (Array.isArray(data.seo_keywords)) {
            keywords = data.seo_keywords;
        } else if (typeof data.seo_keywords === 'string') {
            try { keywords = JSON.parse(data.seo_keywords); } catch (_) { keywords = []; }
        } else if (Array.isArray(data.keywords)) {
            keywords = data.keywords;
        }

        resultDiv.innerHTML = `
            <h4 class="font-medium mb-2">AI Suggestions for ${sku}</h4>
            <div class="space-y-2">
                <div><strong>Title:</strong> ${title}</div>
                <div><strong>Description:</strong> ${description}</div>
                <div><strong>Keywords:</strong> ${keywords.length ? keywords.join(', ') : 'N/A'}</div>
            </div>
        `;
    } catch (error) {
        resultDiv.innerHTML = '<div class="text-red-600">Network error occurred</div>';
    }
}

async function loadSocialAccounts() {
    AdminMarketingModule.loadSocialAccounts();
}

// Populate the Item SKU dropdown for Item Suggestions
async function populateSuggestionSkuSelect() {
    try {
        const sel = document.getElementById('suggestion-sku');
        if (!sel) return;
        const payload = await __wfApiGet('/api/inventory.php');
        const items = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []);
        if (!Array.isArray(items) || !items.length) {
            sel.innerHTML = '<option value="">No items found</option>';
            return;
        }
        const options = ['<option value="">Select an item…</option>'].concat(
            items.map(it => {
                const label = `${(it.sku || '').toString()} — ${(it.name || '').toString()}`.trim();
                const sku = (it.sku || '').toString().replace(/\"/g,'&quot;');
                return `<option value="${sku}">${label}</option>`;
            })
        );
        sel.innerHTML = options.join('');
    } catch (_) {
        try {
            const sel = document.getElementById('suggestion-sku');
            if (sel) sel.innerHTML = '<option value="">Failed to load items</option>';
        } catch (_) {}
    }
}

try { document.addEventListener('DOMContentLoaded', () => { try { populateSuggestionSkuSelect(); } catch (_) {} }); } catch (_) { try { populateSuggestionSkuSelect(); } catch (_) {} }
</script>

<?php echo vite_entry('src/entries/admin-marketing.js'); ?>
