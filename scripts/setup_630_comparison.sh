#!/bin/bash

# Setup June 30th Working Room Modal Comparison Environment
# This script clones the repository and sets up a working comparison installation

set -e

# Configuration
CURRENT_DIR="/Users/jongraves/Documents/Websites/WhimsicalFrog"
TARGET_DIR="/Users/jongraves/Documents/Websites/WhimsicalFrog_630"
CURRENT_PORT="8080"
COMPARISON_PORT="8081"

echo "🚀 Setting up June 30th working room modal comparison environment..."
echo "📁 Current installation: $CURRENT_DIR (port $CURRENT_PORT)"
echo "📁 Comparison installation: $TARGET_DIR (port $COMPARISON_PORT)"

# Function to find the git remote URL
get_git_remote() {
    cd "$CURRENT_DIR"
    local remote_url=$(git remote get-url origin 2>/dev/null || echo "")
    if [[ -z "$remote_url" ]]; then
        echo "⚠️  No git remote found. Using local repository."
        return 1
    fi
    echo "$remote_url"
    return 0
}

# Function to find the best June 30th commit
find_june_30_commit() {
    cd "$CURRENT_DIR"
    
    echo "🔍 Searching for June 30th commits with room modal functionality..."
    
    # Search for commits around June 30th, 2025
    local commits=$(git log --since="2025-06-30 20:00:00" --until="2025-07-01 02:00:00" --pretty=format:"%H|%ci|%s" 2>/dev/null || echo "")
    
    if [[ -z "$commits" ]]; then
        echo "⚠️  No commits found for June 30th 2025. Searching broader range..."
        commits=$(git log --since="2025-06-29 00:00:00" --until="2025-07-02 00:00:00" --pretty=format:"%H|%ci|%s" 2>/dev/null || echo "")
    fi
    
    if [[ -z "$commits" ]]; then
        echo "⚠️  No commits found in date range. Searching for room-related commits..."
        commits=$(git log --grep="room" --grep="modal" --grep="position" --pretty=format:"%H|%ci|%s" | head -10)
    fi
    
    local best_commit=""
    local best_score=0
    
    # Analyze each commit
    while IFS='|' read -r hash date message; do
        if [[ -n "$hash" ]]; then
            local score=0
            echo "  📅 Checking: $hash ($date) - $message"
            
            # Score based on date proximity to June 30th 10:30pm
            if [[ "$date" == *"2025-06-30"* ]]; then
                score=$((score + 10))
                if [[ "$date" == *"22:"* || "$date" == *"23:"* ]]; then
                    score=$((score + 5))
                fi
            fi
            
            # Score based on commit message keywords
            if [[ "$message" == *"room"* ]]; then score=$((score + 3)); fi
            if [[ "$message" == *"modal"* ]]; then score=$((score + 3)); fi
            if [[ "$message" == *"position"* ]]; then score=$((score + 2)); fi
            if [[ "$message" == *"fix"* ]]; then score=$((score + 2)); fi
            if [[ "$message" == *"working"* ]]; then score=$((score + 3)); fi
            
            # Check if commit has key files
            if git show "$hash:js/room-modal-manager.js" >/dev/null 2>&1; then
                score=$((score + 5))
            fi
            if git show "$hash:api/load_room_content.php" >/dev/null 2>&1; then
                score=$((score + 3))
            fi
            
            echo "    Score: $score"
            
            if [[ $score -gt $best_score ]]; then
                best_commit="$hash"
                best_score=$score
                echo "    🎯 New best candidate!"
            fi
        fi
    done <<< "$commits"
    
    if [[ -z "$best_commit" ]]; then
        echo "❌ No suitable commit found. Using latest commit."
        best_commit=$(git rev-parse HEAD)
    fi
    
    echo "🏆 Selected commit: $best_commit (score: $best_score)"
    echo "$best_commit"
}

# Function to clone and setup the repository
setup_comparison_repo() {
    local target_commit="$1"
    
    echo "📦 Setting up comparison repository..."
    
    # Remove existing directory if it exists
    if [[ -d "$TARGET_DIR" ]]; then
        echo "🗑️  Removing existing directory: $TARGET_DIR"
        rm -rf "$TARGET_DIR"
    fi
    
    # Create parent directory
    mkdir -p "$(dirname "$TARGET_DIR")"
    
    # Try to get remote URL for cloning
    local remote_url
    if remote_url=$(get_git_remote); then
        echo "📡 Cloning from remote: $remote_url"
        git clone "$remote_url" "$TARGET_DIR"
    else
        echo "📁 Copying from local repository..."
        cp -r "$CURRENT_DIR" "$TARGET_DIR"
    fi
    
    # Checkout the target commit
    cd "$TARGET_DIR"
    echo "🔄 Checking out commit: $target_commit"
    git checkout "$target_commit" 2>/dev/null || {
        echo "⚠️  Could not checkout specific commit. Using current state."
    }
    
    # Get commit info
    local commit_info=$(git show --format="%H|%ci|%an|%s" -s "$target_commit" 2>/dev/null || echo "unknown|unknown|unknown|unknown")
    IFS='|' read -r hash date author message <<< "$commit_info"
    
    echo "✅ Repository setup complete!"
    echo "   📍 Commit: $hash"
    echo "   📅 Date: $date"
    echo "   👤 Author: $author"
    echo "   💬 Message: $message"
}

# Function to configure the comparison environment
configure_comparison_env() {
    cd "$TARGET_DIR"
    
    echo "🔧 Configuring comparison environment..."
    
    # Copy database and config from current installation
    if [[ -f "$CURRENT_DIR/config.php" ]]; then
        cp "$CURRENT_DIR/config.php" "$TARGET_DIR/"
        echo "  📄 Copied config.php"
    fi
    
    # Copy database file if it exists
    if [[ -f "$CURRENT_DIR/whimsicalfrog.db" ]]; then
        cp "$CURRENT_DIR/whimsicalfrog.db" "$TARGET_DIR/"
        echo "  🗄️  Copied database"
    fi
    
    # Create a startup script for the comparison server
    cat > "$TARGET_DIR/start_comparison_server.sh" << EOF
#!/bin/bash
echo "🚀 Starting June 30th comparison server on port $COMPARISON_PORT..."
echo "🌐 Access at: http://localhost:$COMPARISON_PORT"
echo "🛑 Press Ctrl+C to stop"
cd "\$(dirname "\$0")"
php -S localhost:$COMPARISON_PORT
EOF
    chmod +x "$TARGET_DIR/start_comparison_server.sh"
    
    # Create a comparison test page
    cat > "$TARGET_DIR/comparison_test.html" << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <title>June 30th Working Room Modal - Comparison Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .header { background: #87ac3a; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .comparison-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .test-btn { background: #87ac3a; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .test-btn:hover { background: #6b8e23; }
        .status-good { color: #28a745; font-weight: bold; }
        .status-bad { color: #dc3545; font-weight: bold; }
        .comparison-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .comparison-table th, .comparison-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .comparison-table th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎯 June 30th Working Room Modal - Comparison Environment</h1>
        <p>This is the extracted working version for comparison with the current broken implementation.</p>
    </div>

    <div class="comparison-box">
        <h2>🧪 Test Room Modal Functionality</h2>
        <p>Use these links to test the room modal system in this working version:</p>
        
        <a href="/?page=room_main" class="test-btn">🏠 Room Main Page</a>
        <a href="/api/load_room_content.php?room_number=1&modal=1" class="test-btn">🔧 Room 1 API Test</a>
        <a href="/api/load_room_content.php?room_number=2&modal=1" class="test-btn">🔧 Room 2 API Test</a>
    </div>

    <div class="comparison-box">
        <h2>📊 Comparison Checklist</h2>
        <p>When testing, verify these key functionalities work correctly:</p>
        
        <table class="comparison-table">
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>Expected Behavior</th>
                    <th>Working Version</th>
                    <th>Current Version</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Product Icon Positioning</strong></td>
                    <td>Icons display at distinct database coordinates</td>
                    <td class="status-good">✅ Should work</td>
                    <td class="status-bad">❌ Currently broken</td>
                </tr>
                <tr>
                    <td><strong>Background Display</strong></td>
                    <td>Room background image fills modal properly</td>
                    <td class="status-good">✅ Should work</td>
                    <td class="status-bad">❌ Currently broken</td>
                </tr>
                <tr>
                    <td><strong>Modal Opening</strong></td>
                    <td>Modal opens smoothly when clicking room doors</td>
                    <td class="status-good">✅ Should work</td>
                    <td>❓ Test both versions</td>
                </tr>
                <tr>
                    <td><strong>Icon Interactions</strong></td>
                    <td>Clicking icons shows product details</td>
                    <td class="status-good">✅ Should work</td>
                    <td>❓ Test both versions</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="comparison-box">
        <h2>🔍 Key Files to Compare</h2>
        <p>After testing, examine these files to identify differences:</p>
        <ul>
            <li><code>js/room-modal-manager.js</code> - Modal management logic</li>
            <li><code>css/room-iframe.css</code> or <code>css/rooms.css</code> - Room styling</li>
            <li><code>api/load_room_content.php</code> - Content loading API</li>
            <li><code>room_main.php</code> - Main room page</li>
        </ul>
    </div>

    <div class="comparison-box">
        <h2>🌐 Server Information</h2>
        <p><strong>Working Version (June 30th):</strong> <a href="http://localhost:8081" target="_blank">http://localhost:8081</a></p>
        <p><strong>Current Version (Broken):</strong> <a href="http://localhost:8080" target="_blank">http://localhost:8080</a></p>
        <p><em>Make sure both servers are running for side-by-side comparison.</em></p>
    </div>
</body>
</html>
EOF
    
    echo "✅ Comparison environment configured!"
}

# Main execution
main() {
    echo "🎯 Starting setup of June 30th working room modal comparison..."
    
    # Verify current directory exists
    if [[ ! -d "$CURRENT_DIR" ]]; then
        echo "❌ Current directory not found: $CURRENT_DIR"
        exit 1
    fi
    
    # Find the best commit
    local target_commit
    target_commit=$(find_june_30_commit)
    
    # Setup the comparison repository
    setup_comparison_repo "$target_commit"
    
    # Configure the environment
    configure_comparison_env
    
    echo ""
    echo "🎉 SUCCESS! June 30th comparison environment ready!"
    echo ""
    echo "📋 Next Steps:"
    echo "1. Start the comparison server:"
    echo "   cd $TARGET_DIR"
    echo "   ./start_comparison_server.sh"
    echo ""
    echo "2. Open comparison test page:"
    echo "   http://localhost:$COMPARISON_PORT/comparison_test.html"
    echo ""
    echo "3. Test room modal functionality in both versions:"
    echo "   - Working version: http://localhost:$COMPARISON_PORT"
    echo "   - Current version: http://localhost:$CURRENT_PORT"
    echo ""
    echo "4. Compare the implementations to identify what changed!"
    echo ""
}

# Run the main function
main "$@"
