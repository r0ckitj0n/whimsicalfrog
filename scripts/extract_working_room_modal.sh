#!/bin/bash

# Extract Working Room Modal Implementation from Git History
# This script searches for and extracts the June 30th working room modal version

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORKING_DIR="/Users/jongraves/Documents/Websites/WhimsicalFrog_630"
CURRENT_DIR="/Users/jongraves/Documents/Websites/WhimsicalFrog"

echo "🔍 Searching for working room modal implementation from June 30th..."

# Function to check if a commit has working room modal files
check_commit_for_room_modal() {
    local commit_hash="$1"
    local commit_date="$2"
    
    echo "  📅 Checking commit $commit_hash ($commit_date)"
    
    # Check if key room modal files exist in this commit
    local has_room_modal=false
    local has_positioning=false
    local has_background=false
    
    # Check for room modal manager
    if git show "$commit_hash:js/room-modal-manager.js" >/dev/null 2>&1; then
        has_room_modal=true
        echo "    ✅ Found room-modal-manager.js"
    fi
    
    # Check for room positioning/coordinate files
    if git show "$commit_hash:js/room-coordinate-manager.js" >/dev/null 2>&1; then
        has_positioning=true
        echo "    ✅ Found room-coordinate-manager.js"
    fi
    
    # Check for room CSS
    if git show "$commit_hash:css/room-iframe.css" >/dev/null 2>&1 || git show "$commit_hash:css/rooms.css" >/dev/null 2>&1; then
        has_background=true
        echo "    ✅ Found room CSS files"
    fi
    
    # Check for room API
    if git show "$commit_hash:api/load_room_content.php" >/dev/null 2>&1; then
        echo "    ✅ Found room API"
    fi
    
    if [[ "$has_room_modal" == true && "$has_positioning" == true ]]; then
        echo "    🎯 This commit looks promising for room modal functionality!"
        return 0
    fi
    
    return 1
}

# Function to extract files from a specific commit
extract_commit_files() {
    local commit_hash="$1"
    local extract_dir="$2"
    
    echo "📦 Extracting files from commit $commit_hash to $extract_dir"
    
    mkdir -p "$extract_dir"
    cd "$CURRENT_DIR"
    
    # List of files to extract
    local files_to_extract=(
        "js/room-modal-manager.js"
        "js/room-coordinate-manager.js"
        "js/room-event-manager.js"
        "js/room-css-manager.js"
        "js/main-room.js"
        "css/room-iframe.css"
        "css/rooms.css"
        "css/global.css"
        "css/bundle.css"
        "api/load_room_content.php"
        "api/get_background.php"
        "room_main.php"
        "sections/room_main.php"
        "sections/main_room.php"
        "sections/room_template.php"
        "includes/functions.php"
        "includes/background_helpers.php"
    )
    
    for file in "${files_to_extract[@]}"; do
        if git show "$commit_hash:$file" >/dev/null 2>&1; then
            echo "  📄 Extracting $file"
            mkdir -p "$extract_dir/$(dirname "$file")"
            git show "$commit_hash:$file" > "$extract_dir/$file"
        else
            echo "  ⚠️  File $file not found in commit $commit_hash"
        fi
    done
    
    # Create a commit info file
    cat > "$extract_dir/COMMIT_INFO.md" << EOF
# Extracted Commit Information

**Commit Hash:** $commit_hash
**Date:** $(git show -s --format=%ci "$commit_hash")
**Author:** $(git show -s --format=%an "$commit_hash")
**Message:** $(git show -s --format=%s "$commit_hash")

## Files Extracted
$(for file in "${files_to_extract[@]}"; do
    if [[ -f "$extract_dir/$file" ]]; then
        echo "- ✅ $file"
    else
        echo "- ❌ $file (not found)"
    fi
done)

## How to Test
1. Copy the database from current installation
2. Set up a local server pointing to this directory
3. Test room modal functionality
4. Compare with current broken implementation
EOF
    
    echo "✅ Extraction complete!"
}

# Function to create comparison setup
create_comparison_setup() {
    local working_dir="$1"
    
    echo "🔧 Setting up comparison environment..."
    
    # Copy essential non-code files
    if [[ -f "$CURRENT_DIR/config.php" ]]; then
        cp "$CURRENT_DIR/config.php" "$working_dir/"
        echo "  📄 Copied config.php"
    fi
    
    # Copy images directory
    if [[ -d "$CURRENT_DIR/images" ]]; then
        cp -r "$CURRENT_DIR/images" "$working_dir/"
        echo "  🖼️  Copied images directory"
    fi
    
    # Create a simple index.php for testing
    cat > "$working_dir/index.php" << 'EOF'
<?php
// Simple index for testing working room modal
require_once 'config.php';

$page = $_GET['page'] ?? 'room_main';

switch ($page) {
    case 'room_main':
        if (file_exists('room_main.php')) {
            include 'room_main.php';
        } elseif (file_exists('sections/room_main.php')) {
            include 'sections/room_main.php';
        } else {
            echo '<h1>Room Main Page</h1><p>Room main file not found</p>';
        }
        break;
    default:
        echo '<h1>Working Room Modal Test</h1>';
        echo '<p><a href="?page=room_main">Test Room Main Page</a></p>';
        break;
}
?>
EOF
    
    # Create a test script
    cat > "$working_dir/test_room_modal.html" << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <title>Working Room Modal Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test-info { background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .test-btn { background: #87ac3a; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>🎯 Working Room Modal Test Environment</h1>
    
    <div class="test-info">
        <h3>This is the extracted working version from June 30th</h3>
        <p>Use this to compare with the current broken implementation.</p>
    </div>
    
    <h3>Test Links:</h3>
    <ul>
        <li><a href="index.php?page=room_main">Room Main Page</a></li>
        <li><a href="api/load_room_content.php?room_number=1&modal=1">Room 1 API Test</a></li>
    </ul>
    
    <h3>Comparison Steps:</h3>
    <ol>
        <li>Test room modal functionality here</li>
        <li>Note how positioning and backgrounds work</li>
        <li>Compare with current implementation</li>
        <li>Identify differences in code structure</li>
    </ol>
</body>
</html>
EOF
    
    echo "✅ Comparison environment ready at $working_dir"
}

# Main execution
main() {
    echo "🚀 Starting extraction of working room modal implementation..."
    
    cd "$CURRENT_DIR"
    
    # Search for commits around June 30th
    echo "🔍 Searching git history for June 30th commits..."
    
    # Get commits from June 30th (adjust year if needed)
    local june_30_commits=$(git log --since="2025-06-30 00:00:00" --until="2025-07-01 00:00:00" --pretty=format:"%H %ci" --reverse)
    
    if [[ -z "$june_30_commits" ]]; then
        echo "⚠️  No commits found for June 30th 2025. Searching broader range..."
        june_30_commits=$(git log --since="2025-06-29 00:00:00" --until="2025-07-02 00:00:00" --pretty=format:"%H %ci" --reverse)
    fi
    
    if [[ -z "$june_30_commits" ]]; then
        echo "❌ No commits found in the date range. Searching for any room modal related commits..."
        june_30_commits=$(git log --grep="room" --grep="modal" --grep="position" --pretty=format:"%H %ci" --reverse | head -10)
    fi
    
    local best_commit=""
    local best_commit_date=""
    
    # Check each commit
    while IFS= read -r line; do
        if [[ -n "$line" ]]; then
            local commit_hash=$(echo "$line" | cut -d' ' -f1)
            local commit_date=$(echo "$line" | cut -d' ' -f2-)
            
            if check_commit_for_room_modal "$commit_hash" "$commit_date"; then
                best_commit="$commit_hash"
                best_commit_date="$commit_date"
                echo "🎯 Found promising commit: $commit_hash ($commit_date)"
                
                # If this is close to 10:30pm on June 30th, prioritize it
                if [[ "$commit_date" == *"2025-06-30"* ]] && [[ "$commit_date" == *"22:"* || "$commit_date" == *"23:"* ]]; then
                    echo "🏆 This commit is from June 30th evening - likely the working version!"
                    break
                fi
            fi
        fi
    done <<< "$june_30_commits"
    
    if [[ -z "$best_commit" ]]; then
        echo "❌ No suitable commit found with room modal files."
        echo "💡 Try running: git log --oneline --grep='room' --grep='modal' to find relevant commits manually"
        exit 1
    fi
    
    echo "🎯 Using commit: $best_commit ($best_commit_date)"
    
    # Extract the files
    extract_commit_files "$best_commit" "$WORKING_DIR"
    
    # Set up comparison environment
    create_comparison_setup "$WORKING_DIR"
    
    echo ""
    echo "🎉 SUCCESS! Working room modal extracted to: $WORKING_DIR"
    echo ""
    echo "📋 Next Steps:"
    echo "1. cd $WORKING_DIR"
    echo "2. Start a local server: php -S localhost:8081"
    echo "3. Open http://localhost:8081/test_room_modal.html"
    echo "4. Test the room modal functionality"
    echo "5. Compare with current implementation at localhost:8080"
    echo ""
    echo "🔍 Key files to compare:"
    echo "- js/room-modal-manager.js"
    echo "- css/room-iframe.css or css/rooms.css"
    echo "- api/load_room_content.php"
    echo ""
}

# Run the main function
main "$@"
