<?php
// Popup Sales Lingo Management Modal
// This modal allows admins to create, edit, and manage sales lingo messages
?>

<!-- Popup Sales Lingo Management Modal -->
<div id="popupSalesLingoModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
        <div class="flex justify-between items-center p-6 border-b">
            <h2 class="text-2xl font-bold text-gray-800">
                <svg class="inline-block w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a9.863 9.863 0 01-4.255-.949L5 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                </svg>
                Popup Sales Lingo Management
            </h2>
            <button onclick="closePopupSalesLingoModal()" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="p-6 overflow-y-auto max-h-[70vh]">
            <!-- Add New Message Section -->
            <div class="mb-6 p-4 bg-green-50 rounded-lg border border-green-200">
                <h3 class="text-lg font-semibold text-green-800 mb-4">Add New Sales Message</h3>
                <form id="addSalesLingoForm" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select id="newLingoCategory" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <option value="short">Short (Overlay Messages)</option>
                                <option value="medium" selected>Medium (Bullet Points)</option>
                                <option value="long">Long (Detailed Messages)</option>
                                <option value="urgency">Urgency (Limited Stock)</option>
                                <option value="value">Value (Quality/Price)</option>
                                <option value="social">Social Proof (Reviews/Popularity)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                            <select id="newLingoPriority" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <option value="1">Low Priority</option>
                                <option value="2" selected>Medium Priority</option>
                                <option value="3">High Priority</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                        <textarea id="newLingoMessage" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2" placeholder="Enter your sales message... Feel free to use emojis! 🔥⚡💎🎨"></textarea>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add Message
                        </button>
                        <div class="flex items-center">
                            <input type="checkbox" id="newLingoActive" checked class="mr-2">
                            <label for="newLingoActive" class="text-sm text-gray-700">Active</label>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Filter Section -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg border">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Messages</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select id="filterCategory" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="">All Categories</option>
                            <option value="short">Short</option>
                            <option value="medium">Medium</option>
                            <option value="long">Long</option>
                            <option value="urgency">Urgency</option>
                            <option value="value">Value</option>
                            <option value="social">Social Proof</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select id="filterPriority" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="">All Priorities</option>
                            <option value="1">Low</option>
                            <option value="2">Medium</option>
                            <option value="3">High</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="filterStatus" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="">All Status</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button onclick="loadSalesLingoMessages()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                        Apply Filters
                    </button>
                </div>
            </div>

            <!-- Messages List -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Existing Messages</h3>
                <div id="salesLingoList" class="space-y-3">
                    <!-- Messages will be loaded here -->
                </div>
            </div>
        </div>

        <div class="px-6 py-4 border-t bg-gray-50 flex justify-between items-center">
            <div class="text-sm text-gray-600">
                <span id="totalMessagesCount">0</span> messages total
            </div>
            <div class="flex space-x-2">
                <button onclick="testSalesLingoDisplay()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md">
                    Test Display
                </button>
                <button onclick="closePopupSalesLingoModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Popup Sales Lingo Management Functions
let salesLingoMessages = [];

// Open modal
function openPopupSalesLingo() {
    document.getElementById('popupSalesLingoModal').classList.remove('hidden');
    loadSalesLingoMessages();
}

// Close modal
function closePopupSalesLingoModal() {
    document.getElementById('popupSalesLingoModal').classList.add('hidden');
}

// Load messages from database
async function loadSalesLingoMessages() {
    try {
        const category = document.getElementById('filterCategory').value;
        const priority = document.getElementById('filterPriority').value;
        const status = document.getElementById('filterStatus').value;
        
        let url = '/api/popup_sales_lingo.php?action=get_all';
        const params = [];
        if (category) params.push(`category=${encodeURIComponent(category)}`);
        if (priority) params.push(`priority=${priority}`);
        if (status) params.push(`is_active=${status}`);
        
        if (params.length > 0) {
            url += '&' + params.join('&');
        }
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            salesLingoMessages = data.messages || [];
            renderSalesLingoMessages();
            document.getElementById('totalMessagesCount').textContent = salesLingoMessages.length;
        } else {
            console.error('Error loading messages:', data.message);
        }
    } catch (error) {
        console.error('Error loading sales lingo messages:', error);
    }
}

// Render messages list
function renderSalesLingoMessages() {
    const container = document.getElementById('salesLingoList');
    
    if (salesLingoMessages.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-8">No messages found. Add some sales lingo to get started!</p>';
        return;
    }
    
    container.innerHTML = salesLingoMessages.map(message => `
        <div class="bg-white border rounded-lg p-4 shadow-sm">
            <div class="flex justify-between items-start mb-2">
                <div class="flex items-center space-x-2">
                    <span class="px-2 py-1 bg-${getCategoryColor(message.category)}-100 text-${getCategoryColor(message.category)}-800 rounded-full text-xs font-medium">
                        ${message.category}
                    </span>
                    <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">
                        Priority ${message.priority}
                    </span>
                    <span class="px-2 py-1 bg-${message.is_active ? 'green' : 'red'}-100 text-${message.is_active ? 'green' : 'red'}-800 rounded-full text-xs">
                        ${message.is_active ? 'Active' : 'Inactive'}
                    </span>
                </div>
                <div class="flex space-x-2">
                    <button onclick="editSalesLingoMessage(${message.id})" class="text-blue-600 hover:text-blue-800 text-sm">
                        Edit
                    </button>
                    <button onclick="deleteSalesLingoMessage(${message.id})" class="text-red-600 hover:text-red-800 text-sm">
                        Delete
                    </button>
                </div>
            </div>
            <p class="text-gray-800 mb-2">${message.message}</p>
            <p class="text-xs text-gray-500">
                Created: ${new Date(message.created_at).toLocaleDateString()}
                ${message.updated_at !== message.created_at ? `• Updated: ${new Date(message.updated_at).toLocaleDateString()}` : ''}
            </p>
        </div>
    `).join('');
}

// Get category color for styling
function getCategoryColor(category) {
    const colors = {
        'short': 'purple',
        'medium': 'blue',
        'long': 'indigo',
        'urgency': 'red',
        'value': 'green',
        'social': 'yellow'
    };
    return colors[category] || 'gray';
}

// Add new message
document.getElementById('addSalesLingoForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const category = document.getElementById('newLingoCategory').value;
    const message = document.getElementById('newLingoMessage').value.trim();
    const priority = parseInt(document.getElementById('newLingoPriority').value);
    const isActive = document.getElementById('newLingoActive').checked;
    
    if (!message) {
        alert('Please enter a message');
        return;
    }
    
    try {
        const response = await fetch('/api/popup_sales_lingo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'add',
                category: category,
                message: message,
                priority: priority,
                is_active: isActive
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Clear form
            document.getElementById('newLingoMessage').value = '';
            document.getElementById('newLingoCategory').selectedIndex = 1;
            document.getElementById('newLingoPriority').selectedIndex = 1;
            document.getElementById('newLingoActive').checked = true;
            
            // Reload messages
            loadSalesLingoMessages();
            
            // Show success message
            alert('Message added successfully!');
        } else {
            alert('Error adding message: ' + data.message);
        }
    } catch (error) {
        console.error('Error adding message:', error);
        alert('Error adding message: ' + error.message);
    }
});

// Edit message (simple prompt for now)
async function editSalesLingoMessage(id) {
    const message = salesLingoMessages.find(m => m.id === id);
    if (!message) return;
    
    const newMessage = prompt('Edit message:', message.message);
    if (newMessage === null || newMessage.trim() === '') return;
    
    try {
        const response = await fetch('/api/popup_sales_lingo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update',
                id: id,
                message: newMessage.trim()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadSalesLingoMessages();
            alert('Message updated successfully!');
        } else {
            alert('Error updating message: ' + data.message);
        }
    } catch (error) {
        console.error('Error updating message:', error);
        alert('Error updating message: ' + error.message);
    }
}

// Delete message
async function deleteSalesLingoMessage(id) {
    if (!confirm('Are you sure you want to delete this message?')) return;
    
    try {
        const response = await fetch('/api/popup_sales_lingo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete',
                id: id
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadSalesLingoMessages();
            alert('Message deleted successfully!');
        } else {
            alert('Error deleting message: ' + data.message);
        }
    } catch (error) {
        console.error('Error deleting message:', error);
        alert('Error deleting message: ' + error.message);
    }
}

// Test display function
function testSalesLingoDisplay() {
    alert('Sales lingo test display will be implemented. For now, try hovering over items in rooms to see the sales messages!');
}

// Close modal when clicking outside
document.getElementById('popupSalesLingoModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePopupSalesLingoModal();
    }
});
</script> 