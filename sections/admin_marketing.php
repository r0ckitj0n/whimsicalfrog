<?php
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/includes/vite_helper.php';

// Ensure shared layout (header/footer) is bootstrapped so the admin navbar is present
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

// Always include admin navbar on marketing page, even when accessed directly
$section = 'marketing';
include_once dirname(__DIR__) . '/components/admin_nav_tabs.php';

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
</style>

<div class="admin-marketing-page">
    
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

    <!-- Tools -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="admin-card">
            <h3 class="admin-card-title">🤖 AI Tools</h3>
            <div class="space-y-3">
                <button data-tool="suggestions" class="btn btn-primary w-full">Product Suggestions</button>
                <button data-tool="content" class="btn btn-secondary w-full">Content Generator</button>
                <button data-tool="social-media" class="btn btn-secondary w-full">Social Media</button>
            </div>
        </div>
        <div class="admin-card">
            <h3 class="admin-card-title">📧 Email Marketing</h3>
            <div class="space-y-3">
                <button data-tool="newsletters" class="btn btn-primary w-full">Newsletters</button>
                <button data-tool="automation" class="btn btn-secondary w-full">Automation</button>
            </div>
        </div>
        <div class="admin-card">
            <h3 class="admin-card-title">💰 Promotions</h3>
            <div class="space-y-3">
                <button data-tool="discounts" class="btn btn-primary w-full">Discount Codes</button>
                <button data-tool="coupons" class="btn btn-secondary w-full">Coupons</button>
            </div>
        </div>
    </div>

    <!-- AI Tools Section -->
    <div id="suggestions-section" class="marketing-tool-section hidden mt-6">
        <div class="admin-card">
            <h3 class="admin-card-title">🤖 AI Product Suggestions</h3>
            <div class="space-y-4">
                <input type="text" id="suggestion-sku" class="admin-form-input" placeholder="Enter product SKU">
                <button onclick="generateSuggestions()" class="btn btn-primary">Generate AI Suggestions</button>
                <div id="suggestions-result" class="hidden mt-4 p-4 bg-gray-50 rounded"></div>
            </div>
        </div>
    </div>

    <!-- Social Media Section -->
    <div id="social-media-section" class="marketing-tool-section hidden mt-6">
        <div class="admin-card">
            <h3 class="admin-card-title">📱 Social Media Manager</h3>
            <div id="social-accounts-list"></div>
            <button onclick="loadSocialAccounts()" class="btn btn-primary mt-4">Manage Social Accounts</button>
        </div>
    </div>

    <!-- Other sections -->
    <div id="content-section" class="marketing-tool-section hidden mt-6">
        <div class="admin-card">
            <h3 class="admin-card-title">✍️ Content Generator</h3>
            <p>AI-powered content creation tools</p>
        </div>
    </div>
</div>

<script>
// Global functions for marketing tools
async function generateSuggestions() {
    const sku = document.getElementById('suggestion-sku').value;
    if (!sku) {
        alert('Please enter a product SKU');
        return;
    }
    
    const resultDiv = document.getElementById('suggestions-result');
    resultDiv.innerHTML = '<div class="text-center">Generating AI suggestions...</div>';
    resultDiv.classList.remove('hidden');
    
    try {
        const response = await fetch('/api/suggest_marketing.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sku: sku })
        });
        
        const data = await response.json();
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

async function loadSocialAccounts() {
    AdminMarketingModule.loadSocialAccounts();
}
</script>

<?php echo vite_entry('src/entries/admin-marketing.js'); ?>
