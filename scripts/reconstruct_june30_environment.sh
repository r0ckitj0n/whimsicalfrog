#!/bin/bash

# Reconstruct June 30th Working Room Modal Environment
# Since the git commit isn't accessible locally, we'll reconstruct from backup files

set -e

CURRENT_DIR="/Users/jongraves/Documents/Websites/WhimsicalFrog"
TARGET_DIR="/Users/jongraves/Documents/Websites/WhimsicalFrog_630"
BACKUP_DIR="$CURRENT_DIR/backups/WhimsicalFrog 2025-07-02"

echo "🔧 Reconstructing June 30th working environment from backup files..."

# Function to copy essential files from current working directory
copy_essential_files() {
    echo "📁 Copying essential files from current installation..."
    
    # Core files that should work with June 30th version
    local essential_files=(
        "config.php"
        "index.php"
        "whimsicalfrog.db"
        "api/config.php"
        "api/get_room_coordinates.php"
        "api/get_item_details.php"
        "api/business_settings_helper.php"
        "includes/functions.php"
        "includes/auth.php"
        "includes/background_helpers.php"
        "includes/item_image_helpers.php"
        "components/header_template.php"
        "components/global_popup.php"
        "css/bundle.css"
        "css/global.css"
        "js/bundle.js"
        "js/global-popup.js"
        "images/"
    )
    
    for item in "${essential_files[@]}"; do
        if [[ -e "$CURRENT_DIR/$item" ]]; then
            echo "  📄 Copying $item"
            mkdir -p "$TARGET_DIR/$(dirname "$item")"
            if [[ -d "$CURRENT_DIR/$item" ]]; then
                cp -r "$CURRENT_DIR/$item" "$TARGET_DIR/$(dirname "$item")/"
            else
                cp "$CURRENT_DIR/$item" "$TARGET_DIR/$item"
            fi
        else
            echo "  ⚠️  Missing: $item"
        fi
    done
}

# Function to create June 30th specific files
create_june30_files() {
    echo "🎯 Creating June 30th specific implementation..."
    
    # Copy the June 30th room template
    if [[ -f "$BACKUP_DIR/sections/room_template.php.backup.2025-06-30_00-58-51" ]]; then
        mkdir -p "$TARGET_DIR/sections"
        cp "$BACKUP_DIR/sections/room_template.php.backup.2025-06-30_00-58-51" "$TARGET_DIR/sections/room_template.php"
        echo "  ✅ June 30th room template restored"
    else
        echo "  ❌ June 30th backup file not found!"
        exit 1
    fi
    
    # Create room_main.php that uses the June 30th template
    cat > "$TARGET_DIR/room_main.php" << 'EOF'
<?php
/**
 * Room Main Page - June 30th Working Version
 * Uses the working room template from June 30th backup
 */

// Include centralized functions
if (file_exists(__DIR__ . '/includes/functions.php')) {
    require_once __DIR__ . '/includes/functions.php';
}
if (file_exists(__DIR__ . '/includes/background_helpers.php')) {
    require_once __DIR__ . '/includes/background_helpers.php';
}
if (file_exists(__DIR__ . '/includes/auth.php')) {
    require_once __DIR__ . '/includes/auth.php';
}

// Get user authentication status for header
$user = null;
if (function_exists('getCurrentUser')) {
    $user = getCurrentUser();
}

// Include the header if it exists
if (file_exists(__DIR__ . '/components/header_template.php')) {
    include __DIR__ . '/components/header_template.php';
}

// Set room number for main room page (default to room 1)
if (!isset($_GET['page'])) {
    $_GET['page'] = 'room1';
}

// Include the June 30th working room template
include __DIR__ . '/sections/room_template.php';
?>
EOF
    echo "  ✅ Room main page created"
    
    # Create a simple index.php for testing
    cat > "$TARGET_DIR/index.php" << 'EOF'
<?php
/**
 * June 30th Working Version - Index
 */

// Simple routing
$page = $_GET['page'] ?? 'home';

switch ($page) {
    case 'room_main':
    case 'room1':
    case 'room2':
    case 'room3':
    case 'room4':
    case 'room5':
        include 'room_main.php';
        break;
    default:
        echo '<h1>June 30th Working Version</h1>';
        echo '<p><a href="?page=room_main">Test Room Main Page</a></p>';
        echo '<p><a href="?page=room1">Test Room 1</a></p>';
        echo '<p><a href="?page=room2">Test Room 2</a></p>';
        break;
}
?>
EOF
    echo "  ✅ Index page created"
}

# Function to create missing API endpoints
create_missing_apis() {
    echo "🔧 Creating missing API endpoints..."
    
    mkdir -p "$TARGET_DIR/api"
    
    # Create get_room_coordinates.php if it doesn't exist
    if [[ ! -f "$TARGET_DIR/api/get_room_coordinates.php" ]]; then
        cat > "$TARGET_DIR/api/get_room_coordinates.php" << 'EOF'
<?php
/**
 * Get Room Coordinates API - June 30th Version
 */

header('Content-Type: application/json');

try {
    require_once 'config.php';
    
    $roomType = $_GET['room_type'] ?? '';
    
    if (empty($roomType)) {
        echo json_encode(['success' => false, 'message' => 'Room type required']);
        exit;
    }
    
    // Extract room number from room type (e.g., "room1" -> "1")
    $roomNumber = str_replace('room', '', $roomType);
    
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $stmt = $pdo->prepare("
        SELECT area_name as selector, top_coord as top, left_coord as left, 
               width, height, sku
        FROM room_maps 
        WHERE room_number = ? AND is_active = 1
        ORDER BY area_name
    ");
    $stmt->execute([$roomNumber]);
    $coordinates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format coordinates for the June 30th system
    $formattedCoords = [];
    foreach ($coordinates as $coord) {
        $formattedCoords[] = [
            'selector' => '.area-' . str_replace('area', '', $coord['selector']),
            'top' => (float)$coord['top'],
            'left' => (float)$coord['left'],
            'width' => (float)$coord['width'],
            'height' => (float)$coord['height'],
            'sku' => $coord['sku']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'coordinates' => $formattedCoords
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
EOF
        echo "  ✅ Room coordinates API created"
    fi
}

# Function to verify the environment
verify_environment() {
    echo "🔍 Verifying June 30th environment..."
    
    local required_files=(
        "index.php"
        "room_main.php"
        "sections/room_template.php"
        "api/config.php"
        "api/get_room_coordinates.php"
    )
    
    local missing_files=()
    for file in "${required_files[@]}"; do
        if [[ ! -f "$TARGET_DIR/$file" ]]; then
            missing_files+=("$file")
        fi
    done
    
    if [[ ${#missing_files[@]} -eq 0 ]]; then
        echo "  ✅ All required files present"
        return 0
    else
        echo "  ❌ Missing files:"
        for file in "${missing_files[@]}"; do
            echo "    - $file"
        done
        return 1
    fi
}

# Main execution
main() {
    echo "🚀 Starting June 30th environment reconstruction..."
    
    # Create target directory
    mkdir -p "$TARGET_DIR"
    cd "$TARGET_DIR"
    
    # Copy essential files
    copy_essential_files
    
    # Create June 30th specific files
    create_june30_files
    
    # Create missing APIs
    create_missing_apis
    
    # Verify environment
    if verify_environment; then
        echo ""
        echo "🎉 SUCCESS! June 30th working environment reconstructed!"
        echo ""
        echo "📋 Next Steps:"
        echo "1. Start the server: cd $TARGET_DIR && php -S localhost:8081"
        echo "2. Test: http://localhost:8081/?page=room_main"
        echo "3. Compare with current version at localhost:8080"
        echo ""
        echo "🔍 Key Features to Test:"
        echo "- Product icons should display at distinct coordinates"
        echo "- Room backgrounds should render properly"
        echo "- No iframe complexity - direct positioning"
        echo ""
    else
        echo "❌ Environment reconstruction incomplete. Check missing files above."
        exit 1
    fi
}

# Run the main function
main "$@"
