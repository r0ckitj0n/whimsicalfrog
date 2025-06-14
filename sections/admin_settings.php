<?php
// Admin settings page - Authentication is now handled by index.php
?>
<style>
  .admin-data-label {
    color: #222 !important;
  }
  .admin-data-value {
    color: #c00 !important;
    font-weight: bold;
  }
</style>
<div class="bg-white shadow rounded-lg p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-2">Payment Integration Settings</h2>
    <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Square Payment Integration</label>
        <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 rounded">
            <strong>Coming Soon:</strong> You will be able to connect your Square account here to accept credit card payments online.<br>
            When available, paste your Square Application ID and Access Token below.<br>
            <em>(This section is a placeholder. No credentials are stored yet.)</em>
        </div>
    </div>
    <form>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Square Application ID</label>
            <input type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Paste Square Application ID here" disabled>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Square Access Token</label>
            <input type="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Paste Square Access Token here" disabled>
        </div>
        <button type="button" class="brand-button px-4 py-2 rounded cursor-not-allowed" disabled>Save (Coming Soon)</button>
    </form>
</div>
<div class="bg-white shadow rounded-lg p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Other Settings</h2>
    <div class="space-y-3">
        <div>
            <p class="text-sm text-gray-600 mb-3">Manage product categories used across inventory and shop.</p>
            <a href="/?page=admin&section=categories" class="inline-flex items-center px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-medium rounded mr-3">Manage Categories</a>
        </div>
        <div>
            <p class="text-sm text-gray-600 mb-3">View information about ID numbering system used throughout the platform.</p>
            <button onclick="openIdLegendModal()" class="inline-flex items-center px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                ID# Legend
            </button>
        </div>
    </div>
</div>

<!-- ID Legend Modal -->
<div id="idLegendModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <!-- Modal Header -->
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900">ID Number Legend</h3>
                <button onclick="closeIdLegendModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Modal Content -->
            <div class="space-y-6">
                <!-- Customer IDs -->
                <div class="bg-purple-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-purple-800 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                        </svg>
                        Customer IDs
                    </h4>
                    <div class="space-y-2">
                        <p class="text-sm text-gray-700"><strong>Format:</strong> [MonthLetter][Day][SequenceNumber]</p>
                        <p class="text-sm text-gray-700"><strong>Example:</strong> <code class="bg-gray-200 px-2 py-1 rounded">F14004</code></p>
                        <div class="text-sm text-gray-600">
                            <p>• <strong>F</strong> = June (A=Jan, B=Feb, C=Mar, D=Apr, E=May, F=Jun, G=Jul, H=Aug, I=Sep, J=Oct, K=Nov, L=Dec)</p>
                            <p>• <strong>14</strong> = 14th day of the month</p>
                            <p>• <strong>004</strong> = 4th customer registered</p>
                        </div>
                    </div>
                </div>

                <!-- Order IDs -->
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-blue-800 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2L3 7v11a1 1 0 001 1h12a1 1 0 001-1V7l-7-5zM8 15v-3h4v3H8z" clip-rule="evenodd"></path>
                        </svg>
                        Order IDs
                    </h4>
                    <div class="space-y-2">
                        <p class="text-sm text-gray-700"><strong>Format:</strong> [CustomerNum][MonthLetter][Day][ShippingCode][RandomNum]</p>
                        <p class="text-sm text-gray-700"><strong>Example:</strong> <code class="bg-gray-200 px-2 py-1 rounded">01F14P23</code></p>
                        <div class="text-sm text-gray-600">
                            <p>• <strong>01</strong> = Last 2 digits of customer number</p>
                            <p>• <strong>F14</strong> = June 14th (order date)</p>
                            <p>• <strong>P</strong> = Pickup (P=Pickup, L=Local, U=USPS, F=FedEx, X=UPS)</p>
                            <p>• <strong>23</strong> = Random 2-digit number for uniqueness</p>
                        </div>
                    </div>
                </div>

                <!-- Product/Inventory IDs -->
                <div class="bg-green-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-green-800 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                        </svg>
                        Product & Inventory IDs
                    </h4>
                    <div class="space-y-2">
                        <p class="text-sm text-gray-700"><strong>Format:</strong> [CategoryPrefix][SequenceNumber]</p>
                        <p class="text-sm text-gray-700"><strong>Examples:</strong></p>
                        <div class="text-sm text-gray-600 space-y-1">
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">I001</code> = Inventory Item #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">TS001</code> = T-Shirt Product #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">TU001</code> = Tumbler Product #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">AW001</code> = Artwork Product #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">MG001</code> = Mug Product #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">GN001</code> = General/Window Wrap Product #1</p>
                        </div>
                    </div>
                </div>

                <!-- Order Item IDs -->
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-yellow-800 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" clip-rule="evenodd"></path>
                        </svg>
                        Order Item IDs
                    </h4>
                    <div class="space-y-2">
                        <p class="text-sm text-gray-700"><strong>Format:</strong> OI[SequentialNumber]</p>
                        <p class="text-sm text-gray-700"><strong>Example:</strong> <code class="bg-gray-200 px-2 py-1 rounded">OI001</code></p>
                        <div class="text-sm text-gray-600">
                            <p>• <strong>OI</strong> = Order Item prefix</p>
                            <p>• <strong>001</strong> = Sequential 3-digit number (001, 002, 003, etc.)</p>
                            <p class="text-xs italic">Simple, clean, and easy to reference!</p>
                        </div>
                    </div>
                </div>

                <!-- Marketing IDs -->
                <div class="bg-red-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-red-800 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"></path>
                        </svg>
                        Marketing IDs
                    </h4>
                    <div class="space-y-2">
                        <p class="text-sm text-gray-700"><strong>Format:</strong> [TypePrefix][RandomAlphanumeric]</p>
                        <p class="text-sm text-gray-700"><strong>Examples:</strong></p>
                        <div class="text-sm text-gray-600 space-y-1">
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">EC001</code> = Email Campaign #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">DC001</code> = Discount Code #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">SP001</code> = Social Post #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">SA001</code> = Social Account #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">ES001</code> = Email Subscriber #1</p>
                        </div>
                    </div>
                </div>

                <!-- Legacy IDs -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        Legacy/Admin IDs
                    </h4>
                    <div class="space-y-2">
                        <p class="text-sm text-gray-700"><strong>Format:</strong> [Letter][SequenceNumber]</p>
                        <p class="text-sm text-gray-700"><strong>Examples:</strong></p>
                        <div class="text-sm text-gray-600 space-y-1">
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">F13001</code> = Legacy customer/admin account</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">U962</code> = Legacy user format (deprecated)</p>
                        </div>
                        <p class="text-xs text-gray-500 italic">Note: Legacy formats are maintained for existing records but new records use the current specifications.</p>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="mt-6 flex justify-end">
                <button onclick="closeIdLegendModal()" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white text-sm font-medium rounded">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openIdLegendModal() {
    document.getElementById('idLegendModal').style.display = 'block';
}

function closeIdLegendModal() {
    document.getElementById('idLegendModal').style.display = 'none';
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('idLegendModal');
    if (event.target == modal) {
        closeIdLegendModal();
    }
}
</script> 
