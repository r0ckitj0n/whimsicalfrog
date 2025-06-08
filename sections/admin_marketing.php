<?php
// Admin Marketing Page
// This page provides marketing tools and analytics for the Whimsical Frog site

// Prevent direct access
if (!defined('INCLUDED_FROM_INDEX')) {
    header('Location: /?page=admin');
    exit;
}

// Verify admin privileges
if (!isset($isAdmin) || !$isAdmin) {
    echo '<div class="text-center py-12"><h1 class="text-2xl font-bold text-red-600">Access Denied</h1></div>';
    exit;
}

// Sample data for demonstration purposes
$customerStats = [
    'total' => 125,
    'new_this_month' => 18,
    'returning' => 72,
    'conversion_rate' => 3.2
];

$salesStats = [
    'total_sales' => 1850.75,
    'avg_order' => 74.03,
    'popular_product' => 'Custom Tumbler (30oz)',
    'popular_category' => 'Tumblers'
];

$marketingCampaigns = [
    [
        'name' => 'Summer Sale',
        'status' => 'Active',
        'start_date' => '2025-06-01',
        'end_date' => '2025-06-30',
        'discount' => '15%',
        'performance' => 'Good'
    ],
    [
        'name' => 'New Customer Discount',
        'status' => 'Active',
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
        'discount' => '10%',
        'performance' => 'Excellent'
    ],
    [
        'name' => 'Holiday Special',
        'status' => 'Scheduled',
        'start_date' => '2025-11-15',
        'end_date' => '2025-12-25',
        'discount' => '20%',
        'performance' => 'Pending'
    ]
];
?>

<section id="adminMarketingPage" class="py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-merienda text-[#556B2F]">Marketing Dashboard</h1>
        <a href="/?page=admin" class="bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md transition-colors">
            ← Back to Admin
        </a>
    </div>
    
    <!-- Customer Analytics Section -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-merienda text-[#556B2F] mb-4">Customer Analytics</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-700">Total Customers</h3>
                <p class="text-2xl font-bold text-[#6B8E23]"><?php echo $customerStats['total']; ?></p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-700">New This Month</h3>
                <p class="text-2xl font-bold text-[#6B8E23]"><?php echo $customerStats['new_this_month']; ?></p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-700">Returning Customers</h3>
                <p class="text-2xl font-bold text-[#6B8E23]"><?php echo $customerStats['returning']; ?></p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-700">Conversion Rate</h3>
                <p class="text-2xl font-bold text-[#6B8E23]"><?php echo $customerStats['conversion_rate']; ?>%</p>
            </div>
        </div>
        
        <div class="flex justify-center mb-4">
            <div class="bg-gray-100 p-4 rounded-lg w-full max-w-3xl">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Customer Growth</h3>
                <div class="h-64 bg-white p-4 rounded border border-gray-200 flex items-center justify-center">
                    <p class="text-gray-500 italic">Analytics chart will appear here. Integration with Google Analytics coming soon.</p>
                </div>
            </div>
        </div>
        
        <div class="text-center">
            <button class="bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md transition-colors">
                Download Customer Report
            </button>
        </div>
    </div>
    
    <!-- Promotional Tools Section -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-merienda text-[#556B2F] mb-4">Promotional Tools</h2>
        
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-3">Active Campaigns</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Campaign Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Range</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($marketingCampaigns as $campaign): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $campaign['name']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($campaign['status'] === 'Active'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            <?php echo $campaign['status']; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $campaign['start_date']; ?> to <?php echo $campaign['end_date']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $campaign['discount']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $campaign['performance']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="#" class="text-indigo-600 hover:text-indigo-900 mr-3">View</a>
                                    <a href="#" class="text-green-600 hover:text-green-900">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="flex justify-center">
            <button class="bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md transition-colors mr-4">
                Create New Promotion
            </button>
            <button class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-md transition-colors">
                Generate Coupon Codes
            </button>
        </div>
    </div>
    
    <!-- Email Marketing Section -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-merienda text-[#556B2F] mb-4">Email Marketing</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-700 mb-3">Email Campaigns</h3>
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <p class="mb-4">Create and manage email marketing campaigns to engage with your customers.</p>
                    <div class="flex space-x-2">
                        <button class="bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md transition-colors">
                            New Campaign
                        </button>
                        <button class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-md transition-colors">
                            View Templates
                        </button>
                    </div>
                </div>
            </div>
            
            <div>
                <h3 class="text-lg font-semibold text-gray-700 mb-3">Mailing Lists</h3>
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <p class="mb-2">Current Subscribers: <strong>87</strong></p>
                    <p class="mb-4">Open Rate: <strong>32%</strong></p>
                    <div class="flex space-x-2">
                        <button class="bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md transition-colors">
                            Manage Lists
                        </button>
                        <button class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-md transition-colors">
                            Import Contacts
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-4">
            <h3 class="text-lg font-semibold text-gray-700 mb-3">Scheduled Emails</h3>
            <p class="italic text-gray-500 text-center py-4">No emails currently scheduled.</p>
        </div>
        
        <div class="text-center">
            <p class="text-sm text-gray-500 mb-2">Email marketing integration coming soon!</p>
            <button class="bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md transition-colors">
                Connect Email Service
            </button>
        </div>
    </div>
    
    <!-- Social Media Integration Section -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-merienda text-[#556B2F] mb-4">Social Media Integration</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-center">
                <svg class="w-8 h-8 mx-auto mb-2 text-blue-600" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                <h3 class="font-semibold">Facebook</h3>
                <p class="text-sm text-gray-500 mb-2">Not Connected</p>
                <button class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm py-1 px-2 rounded">
                    Connect
                </button>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-center">
                <svg class="w-8 h-8 mx-auto mb-2 text-pink-600" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.899 1.382-.419.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.899-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678c-3.405 0-6.162 2.76-6.162 6.162 0 3.405 2.76 6.162 6.162 6.162 3.405 0 6.162-2.76 6.162-6.162 0-3.405-2.76-6.162-6.162-6.162zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405c0 .795-.646 1.44-1.44 1.44-.795 0-1.44-.646-1.44-1.44 0-.794.646-1.439 1.44-1.439.793-.001 1.44.645 1.44 1.439z"/>
                </svg>
                <h3 class="font-semibold">Instagram</h3>
                <p class="text-sm text-gray-500 mb-2">Not Connected</p>
                <button class="w-full bg-pink-600 hover:bg-pink-700 text-white text-sm py-1 px-2 rounded">
                    Connect
                </button>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-center">
                <svg class="w-8 h-8 mx-auto mb-2 text-blue-400" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                </svg>
                <h3 class="font-semibold">Twitter</h3>
                <p class="text-sm text-gray-500 mb-2">Not Connected</p>
                <button class="w-full bg-blue-400 hover:bg-blue-500 text-white text-sm py-1 px-2 rounded">
                    Connect
                </button>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-center">
                <svg class="w-8 h-8 mx-auto mb-2 text-red-600" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                </svg>
                <h3 class="font-semibold">YouTube</h3>
                <p class="text-sm text-gray-500 mb-2">Not Connected</p>
                <button class="w-full bg-red-600 hover:bg-red-700 text-white text-sm py-1 px-2 rounded">
                    Connect
                </button>
            </div>
        </div>
        
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-3">Social Media Content Calendar</h3>
            <p class="italic text-gray-500 text-center py-4">Connect your social media accounts to manage posts from here.</p>
        </div>
        
        <div class="text-center">
            <p class="text-sm text-gray-500 mb-2">Social media integration coming soon!</p>
            <button class="bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md transition-colors">
                Schedule Social Posts
            </button>
        </div>
    </div>
</section>
