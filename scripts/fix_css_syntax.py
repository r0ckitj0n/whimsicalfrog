#!/usr/bin/env python3

"""
CSS Syntax Fix Script
Systematically fixes malformed CSS properties in main.css and other CSS files.
"""

import os
import re
import sys

def fix_css_syntax_errors(file_path):
    """Fix common CSS syntax errors in a file."""
    print(f"Processing: {file_path}")
    
    if not os.path.exists(file_path):
        print(f"File not found: {file_path}")
        return False
    
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
        
        original_content = content
        fixes_made = 0
        
        # Fix malformed overflow properties: "overflow: display: none;" -> "overflow: hidden;"
        pattern = r'(\s*overflow:\s*)display:\s*none;'
        matches = re.findall(pattern, content)
        if matches:
            content = re.sub(pattern, r'\1hidden;', content)
            fixes_made += len(matches)
            print(f"  Fixed {len(matches)} malformed overflow properties")
        
        # Fix malformed visibility properties: "visibility: display: none;" -> "visibility: hidden;"
        pattern_vis = r'(\s*visibility:\s*)display:\s*none;'
        matches_vis = re.findall(pattern_vis, content)
        if matches_vis:
            content = re.sub(pattern_vis, r'\1hidden;', content)
            fixes_made += len(matches_vis)
            print(f"  Fixed {len(matches_vis)} malformed visibility properties")
        
        # Fix selectors with extra semicolons: ".class {;" -> ".class {"
        pattern_sel = r'([.#][\w-]+\s*)\{;'
        matches_sel = re.findall(pattern_sel, content)
        if matches_sel:
            content = re.sub(pattern_sel, r'\1{', content)
            fixes_made += len(matches_sel)
            print(f"  Fixed {len(matches_sel)} selectors with extra semicolons")
        
        # Fix other potential duplicated properties pattern: "property: property: value;"
        pattern2 = r'(\s*)([\w-]+):\s*\2:\s*([^;]+);'
        matches2 = re.findall(pattern2, content)
        if matches2:
            content = re.sub(pattern2, r'\1\2: \3;', content)
            fixes_made += len(matches2)
            print(f"  Fixed {len(matches2)} duplicated property declarations")
        
        # Fix missing semicolons at end of property values
        pattern3 = r'([^;}])\n(\s*[a-zA-Z-]+\s*:)'
        matches3 = re.findall(pattern3, content)
        if matches3:
            content = re.sub(pattern3, r'\1;\n\2', content)
            fixes_made += len(matches3)
            print(f"  Fixed {len(matches3)} missing semicolons")
        
        # Fix malformed display properties mixed with other properties
        pattern4 = r'(\s*)([\w-]+):\s*display:\s*([^;]+);'
        matches4 = re.findall(pattern4, content)
        if matches4:
            for match in matches4:
                if match[1] != 'display':  # Don't fix actual display properties
                    old_prop = f"{match[0]}{match[1]}: display: {match[2]};"
                    if match[1] == 'visibility':
                        new_prop = f"{match[0]}{match[1]}: hidden;"
                    else:
                        new_prop = f"{match[0]}{match[1]}: {match[2]};"
                    content = content.replace(old_prop, new_prop)
                    fixes_made += 1
            if len([m for m in matches4 if m[1] != 'display']) > 0:
                print(f"  Fixed {len([m for m in matches4 if m[1] != 'display'])} malformed mixed properties")
        
        # Write fixed content back if changes were made
        if fixes_made > 0:
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(content)
            print(f"  Total fixes applied: {fixes_made}")
            return True
        else:
            print("  No syntax errors found")
            return False
            
    except Exception as e:
        print(f"Error processing {file_path}: {e}")
        return False

def main():
    """Main function to fix CSS syntax in all CSS files."""
    base_dir = "/Users/jongraves/Documents/Websites/WhimsicalFrog"
    
    css_files = [
        "css/main.css",
        "css/recovered_components.css", 
        "css/recovered_missing.css",
        "css/z-index.css"
    ]
    
    print("=== CSS Syntax Fix Script ===")
    print("Fixing malformed CSS properties and syntax errors...")
    print()
    
    total_files_fixed = 0
    
    for css_file in css_files:
        file_path = os.path.join(base_dir, css_file)
        if fix_css_syntax_errors(file_path):
            total_files_fixed += 1
        print()
    
    print(f"=== Complete ===")
    print(f"Files processed: {len(css_files)}")
    print(f"Files with fixes: {total_files_fixed}")
    
    if total_files_fixed > 0:
        print("\nCSS syntax errors have been fixed. You can now rebuild with Vite.")
    else:
        print("\nNo CSS syntax errors found.")

if __name__ == "__main__":
    main()
