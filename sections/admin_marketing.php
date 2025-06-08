<?php
// Include database configuration
require_once 'api/config.php';

// Connect to database
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get customer count
    $customerStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Customer' OR roleType = 'Customer'");
    $customerCount = $customerStmt->fetchColumn();
    
    // Check if orders table exists
    $orderTableExists = false;
    $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
    if ($stmt->rowCount() > 0) {
        $orderTableExists = true;
        
        // Get orders count
        $orderStmt = $pdo->query("SELECT COUNT(*) FROM orders");
        $orderCount = $orderStmt->fetchColumn();
        
        // Get total sales
        $salesStmt = $pdo->query("SELECT SUM(totalAmount) FROM orders");
        $totalSales = $salesStmt->fetchColumn() ?: 0;
        
        // Get recent orders
        $recentOrdersStmt = $pdo->query("SELECT o.*, u.username, u.email 
                                        FROM orders o 
                                        LEFT JOIN users u ON o.userId = u.id 
                                        ORDER BY o.orderDate DESC 
                                        LIMIT 5");
        $recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get monthly sales data
        $monthlySalesStmt = $pdo->query("SELECT 
                                        MONTH(orderDate) as month, 
                                        SUM(totalAmount) as total 
                                        FROM orders 
                                        WHERE orderDate >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
                                        GROUP BY MONTH(orderDate) 
                                        ORDER BY month");
        $monthlySales = $monthlySalesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format monthly data for chart
        $monthLabels = [];
        $salesData = [];
        
        foreach ($monthlySales as $data) {
            $monthName = date("M", mktime(0, 0, 0, $data['month'], 10));
            $monthLabels[] = $monthName;
            $salesData[] = $data['total'];
        }
        
        // If no monthly sales data, provide empty arrays
        if (empty($monthLabels)) {
            $monthLabels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun"];
            $salesData = [0, 0, 0, 0, 0, 0];
        }
        
        // Get top products
        $topProductsStmt = $pdo->query("SELECT p.name, COUNT(oi.productId) as orderCount 
                                        FROM order_items oi
                                        JOIN products p ON oi.productId = p.id
                                        GROUP BY oi.productId
                                        ORDER BY orderCount DESC
                                        LIMIT 5");
        $topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Default values if orders table doesn't exist
        $orderCount = 0;
        $totalSales = 0;
        $recentOrders = [];
        $monthLabels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun"];
        $salesData = [0, 0, 0, 0, 0, 0];
        $topProducts = [];
    }
    
    // Get product count
    $productStmt = $pdo->query("SELECT COUNT(*) FROM products");
    $productCount = $productStmt->fetchColumn();
    
} catch (PDOException $e) {
    // Handle database errors
    $customerCount = 0;
    $orderCount = 0;
    $totalSales = 0;
    $productCount = 0;
    $recentOrders = [];
    $monthLabels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun"];
    $salesData = [0, 0, 0, 0, 0, 0];
    $topProducts = [];
}
?>

<div class="admin-section-header" style="display: none;">
    <h2>Marketing Dashboard</h2>
    <a href="/?page=admin" class="back-button">← Back to Admin</a>
</div>

<div class="admin-content">
    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3>Total Customers</h3>
                <p class="stat-value"><?php echo $customerCount; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-info">
                <h3>Total Orders</h3>
                <p class="stat-value"><?php echo $orderCount; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-info">
                <h3>Total Sales</h3>
                <p class="stat-value">$<?php echo number_format($totalSales, 2); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-box"></i>
            </div>
            <div class="stat-info">
                <h3>Total Products</h3>
                <p class="stat-value"><?php echo $productCount; ?></p>
            </div>
        </div>
    </div>
    
    <div class="dashboard-row">
        <div class="dashboard-column">
            <div class="dashboard-card">
                <h3>Sales Overview</h3>
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            
            <div class="dashboard-card">
                <h3>Top Products</h3>
                <ul class="top-products-list">
                    <?php if (!empty($topProducts)): ?>
                        <?php foreach ($topProducts as $product): ?>
                            <li>
                                <span class="product-name"><?php echo htmlspecialchars($product['name']); ?></span>
                                <span class="product-orders"><?php echo $product['orderCount']; ?> orders</span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="no-data">No product data available</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <div class="dashboard-column">
            <div class="dashboard-card">
                <h3>Recent Orders</h3>
                <div class="recent-orders">
                    <?php if (!empty($recentOrders)): ?>
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['id']); ?></td>
                                        <td><?php echo htmlspecialchars($order['username'] ?? $order['email'] ?? 'Unknown'); ?></td>
                                        <td>$<?php echo number_format($order['totalAmount'], 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['orderDate'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-data">No recent orders</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dashboard-card">
                <h3>Marketing Tools</h3>
                <div class="marketing-tools">
                    <a href="#" class="tool-card">
                        <div class="tool-icon"><i class="fas fa-envelope"></i></div>
                        <div class="tool-info">
                            <h4>Email Campaigns</h4>
                            <p>Create and manage email marketing campaigns</p>
                        </div>
                    </a>
                    <a href="#" class="tool-card">
                        <div class="tool-icon"><i class="fas fa-tag"></i></div>
                        <div class="tool-info">
                            <h4>Discount Codes</h4>
                            <p>Generate promotional codes for customers</p>
                        </div>
                    </a>
                    <a href="#" class="tool-card">
                        <div class="tool-icon"><i class="fas fa-share-alt"></i></div>
                        <div class="tool-info">
                            <h4>Social Media</h4>
                            <p>Manage social media integrations</p>
                        </div>
                    </a>
                    <a href="#" class="tool-card">
                        <div class="tool-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="tool-info">
                            <h4>Analytics</h4>
                            <p>View detailed sales and customer analytics</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sales Chart
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthLabels); ?>,
            datasets: [{
                label: 'Monthly Sales',
                data: <?php echo json_encode($salesData); ?>,
                backgroundColor: 'rgba(135, 172, 58, 0.2)',
                borderColor: '#87ac3a',
                borderWidth: 2,
                tension: 0.3,
                pointBackgroundColor: '#87ac3a'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value;
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});
</script>

<style>
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stat-card {
    background-color: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
}

.stat-icon {
    font-size: 2rem;
    color: #87ac3a;
    margin-right: 15px;
}

.stat-info h3 {
    margin: 0;
    font-size: 0.9rem;
    color: #666;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: bold;
    margin: 5px 0 0;
    color: #333;
}

.dashboard-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.dashboard-column {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.dashboard-card {
    background-color: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.dashboard-card h3 {
    margin-top: 0;
    color: #333;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.chart-container {
    height: 250px;
    position: relative;
}

.top-products-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.top-products-list li {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.top-products-list li:last-child {
    border-bottom: none;
}

.product-name {
    font-weight: 500;
}

.product-orders {
    color: #87ac3a;
    font-weight: 500;
}

.mini-table {
    width: 100%;
    border-collapse: collapse;
}

.mini-table th, .mini-table td {
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.mini-table th {
    font-weight: 500;
    color: #666;
}

.marketing-tools {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.tool-card {
    display: flex;
    align-items: center;
    padding: 15px;
    background-color: #f9f9f9;
    border-radius: 8px;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
}

.tool-card:hover {
    background-color: #f0f0f0;
    transform: translateY(-2px);
}

.tool-icon {
    width: 40px;
    height: 40px;
    background-color: #87ac3a;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.tool-info h4 {
    margin: 0;
    font-size: 1rem;
}

.tool-info p {
    margin: 5px 0 0;
    font-size: 0.8rem;
    color: #666;
}

.no-data {
    color: #999;
    font-style: italic;
    text-align: center;
    padding: 20px 0;
}

@media (max-width: 992px) {
    .dashboard-row {
        grid-template-columns: 1fr;
    }
    
    .marketing-tools {
        grid-template-columns: 1fr;
    }
}
</style>
