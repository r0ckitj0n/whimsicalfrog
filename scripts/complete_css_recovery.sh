#!/bin/bash

# Complete CSS Recovery Script
# Efficiently process remaining backup CSS files and merge into main.css
# This avoids token limits by automating the file processing

echo "=== Complete CSS Recovery Script ==="
echo "Processing remaining backup CSS files..."

# Set paths
BACKUP_DIR="/Users/jongraves/Documents/Websites/WhimsicalFrog/backups"
CSS_DIR="/Users/jongraves/Documents/Websites/WhimsicalFrog/css"
MAIN_CSS="$CSS_DIR/main.css"

# Get current line count
INITIAL_LINES=$(wc -l < "$MAIN_CSS")
echo "Initial main.css size: $INITIAL_LINES lines"

# Add separator comment
echo -e "\n\n/* ===== REMAINING BACKUP CSS FILES RECOVERY ===== */" >> "$MAIN_CSS"

# Process remaining backup CSS files that haven't been fully extracted yet
REMAINING_FILES=(
    "admin.css"
    "room-iframe.css" 
    "room-modal.css"
    "main.bundle.css"
)

for file in "${REMAINING_FILES[@]}"; do
    if [ -f "$BACKUP_DIR/$file" ]; then
        echo "Processing $file..."
        echo -e "\n\n/* ===== FROM BACKUP: $file ===== */" >> "$MAIN_CSS"
        
        # Skip database-generated content if it's in the file
        if [[ "$file" != *"generated"* ]]; then
            cat "$BACKUP_DIR/$file" >> "$MAIN_CSS"
            echo "✓ Merged $file"
        else
            echo "⚠ Skipped $file (database-generated)"
        fi
    else
        echo "⚠ File not found: $file"
    fi
done

# Get final line count
FINAL_LINES=$(wc -l < "$MAIN_CSS")
ADDED_LINES=$((FINAL_LINES - INITIAL_LINES))

echo "=== Recovery Complete ==="
echo "Final main.css size: $FINAL_LINES lines"
echo "Added lines: $ADDED_LINES"
echo "Recovery script completed successfully!"
