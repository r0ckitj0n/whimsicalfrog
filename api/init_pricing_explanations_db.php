<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Create PDO connection
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    // Create pricing_explanations table
    $createTable = "
    CREATE TABLE IF NOT EXISTS pricing_explanations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        keyword VARCHAR(100) NOT NULL UNIQUE,
        title VARCHAR(200) NOT NULL,
        explanation TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createTable);
    
    // Insert default explanations
    $explanations = [
        [
            'keyword' => 'market_research',
            'title' => 'Market Research Pricing',
            'explanation' => 'Analysis of current market prices for similar products to ensure competitive positioning. This involves studying competitor prices, market trends, and customer expectations to set optimal pricing that attracts customers while maintaining profitability.'
        ],
        [
            'keyword' => 'competitive_analysis',
            'title' => 'Competitive Analysis',
            'explanation' => 'Evaluation of competitor pricing strategies to identify optimal pricing opportunities. This includes analyzing direct and indirect competitors, their pricing models, value propositions, and market positioning to find competitive advantages.'
        ],
        [
            'keyword' => 'value_based',
            'title' => 'Value-Based Pricing',
            'explanation' => 'Pricing strategy based on the perceived value to customers rather than cost-plus margins. This approach focuses on what customers are willing to pay based on the benefits and value they receive from the product or service.'
        ],
        [
            'keyword' => 'psychological_pricing',
            'title' => 'Psychological Pricing',
            'explanation' => 'Pricing techniques that influence customer perception and buying behavior. Examples include charm pricing ($9.99 vs $10.00), bundling strategies, and anchor pricing to make products appear more attractive to customers.'
        ],
        [
            'keyword' => 'cost_plus',
            'title' => 'Cost-Plus Pricing',
            'explanation' => 'Traditional pricing method that adds a fixed markup percentage to the base cost of production. This ensures profitability by covering all costs and adding a predetermined profit margin, commonly used in manufacturing and retail.'
        ],
        [
            'keyword' => 'premium_pricing',
            'title' => 'Premium Pricing',
            'explanation' => 'Higher pricing strategy for products positioned as high-quality, luxury, or exclusive items. This approach targets customers who associate higher prices with superior quality and are willing to pay more for perceived prestige or enhanced features.'
        ],
        [
            'keyword' => 'penetration_pricing',
            'title' => 'Penetration Pricing',
            'explanation' => 'Lower initial pricing strategy to gain market share and attract customers quickly. This approach sacrifices short-term profits to build customer base, increase market penetration, and potentially achieve economies of scale.'
        ],
        [
            'keyword' => 'demand_based',
            'title' => 'Demand-Based Pricing',
            'explanation' => 'Pricing adjustments based on seasonal trends, market demand patterns, and supply availability. This dynamic approach allows prices to fluctuate based on market conditions, peak seasons, and customer demand cycles.'
        ],
        [
            'keyword' => 'skimming_pricing',
            'title' => 'Price Skimming',
            'explanation' => 'Strategy of setting high initial prices for new or innovative products, then gradually lowering them over time. This captures maximum revenue from early adopters willing to pay premium prices before targeting price-sensitive customers.'
        ],
        [
            'keyword' => 'bundle_pricing',
            'title' => 'Bundle Pricing',
            'explanation' => 'Offering multiple products or services together at a discounted rate compared to individual purchases. This strategy increases average order value, moves slow-selling items, and provides perceived value to customers.'
        ]
    ];
    
    $insertStmt = $pdo->prepare("
        INSERT INTO pricing_explanations (keyword, title, explanation) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        title = VALUES(title), 
        explanation = VALUES(explanation)
    ");
    
    foreach ($explanations as $explanation) {
        $insertStmt->execute([
            $explanation['keyword'],
            $explanation['title'],
            $explanation['explanation']
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Pricing explanations database initialized successfully',
        'explanations_count' => count($explanations)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 