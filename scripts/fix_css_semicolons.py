#!/usr/bin/env python3
"""
Fix malformed CSS syntax - specifically extra semicolons at the start of CSS rule blocks
that are preventing form styles from applying properly.
"""

import os
import re
import sys

def fix_css_semicolons(file_path):
    """Fix malformed CSS syntax with extra semicolons"""
    print(f"Processing: {file_path}")
    
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
    except Exception as e:
        print(f"Error reading {file_path}: {e}")
        return False
    
    original_content = content
    fixes_made = 0
    
    # Pattern to match CSS rules with extra semicolons at the start
    # Looking for patterns like: "selector { ; property: value;"
    pattern = r'(\{)\s*;\s*\n\s*([a-zA-Z-]+\s*:)'
    
    def replace_func(match):
        nonlocal fixes_made
        fixes_made += 1
        # Replace "{ ;" with just "{"
        return match.group(1) + '\n    ' + match.group(2)
    
    content = re.sub(pattern, replace_func, content, flags=re.MULTILINE)
    
    # Additional pattern for cases where the semicolon is on its own line
    pattern2 = r'(\{)\s*\n\s*;\s*\n\s*([a-zA-Z-]+\s*:)'
    content = re.sub(pattern2, lambda m: (fixes_made := fixes_made + 1) or m.group(1) + '\n    ' + m.group(2), content, flags=re.MULTILINE)
    
    if content != original_content:
        try:
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(content)
            print(f"Fixed {fixes_made} malformed CSS syntax issues in {file_path}")
            return True
        except Exception as e:
            print(f"Error writing {file_path}: {e}")
            return False
    else:
        print(f"No malformed CSS syntax found in {file_path}")
        return True

def main():
    css_dir = "/Users/jongraves/Documents/Websites/WhimsicalFrog/css"
    
    if not os.path.exists(css_dir):
        print(f"CSS directory not found: {css_dir}")
        return 1
    
    # Process main CSS files
    css_files = [
        "main.css",
        "recovered_components.css", 
        "recovered_missing.css",
        "z-index.css"
    ]
    
    success = True
    for css_file in css_files:
        file_path = os.path.join(css_dir, css_file)
        if os.path.exists(file_path):
            if not fix_css_semicolons(file_path):
                success = False
        else:
            print(f"File not found: {file_path}")
    
    if success:
        print("\n✅ CSS syntax repair completed successfully!")
        print("The login form and other forms should now be properly styled.")
    else:
        print("\n❌ Some errors occurred during CSS syntax repair.")
        return 1
    
    return 0

if __name__ == "__main__":
    sys.exit(main())
