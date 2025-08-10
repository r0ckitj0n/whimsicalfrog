#!/usr/bin/env python3

"""
Comprehensive CSS Validator and Repair Tool
Systematically validates and repairs all CSS syntax issues in the recovered files.
"""

import os
import re
import sys

def comprehensive_css_fix(file_path):
    """Comprehensive CSS syntax validation and repair."""
    print(f"Processing: {file_path}")
    
    if not os.path.exists(file_path):
        print(f"File not found: {file_path}")
        return False
    
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
        
        original_content = content
        fixes_made = 0
        
        # 1. Fix malformed compound properties (property: otherproperty: value)
        patterns = [
            (r'(\s*overflow:\s*)display:\s*([^;]+);', r'\1hidden;', 'overflow/display'),
            (r'(\s*visibility:\s*)display:\s*([^;]+);', r'\1hidden;', 'visibility/display'),
            (r'(\s*display:\s*)inline-display:\s*([^;]+);', r'\1inline-\2;', 'display/inline-display'), 
            (r'(\s*background:\s*)background-([^:]+):\s*([^;]+);', r'\1\3;', 'background duplication'),
            (r'(\s*border:\s*)border-([^:]+):\s*([^;]+);', r'\1\3;', 'border duplication'),
            (r'(\s*margin:\s*)margin-([^:]+):\s*([^;]+);', r'\1\3;', 'margin duplication'),
            (r'(\s*padding:\s*)padding-([^:]+):\s*([^;]+);', r'\1\3;', 'padding duplication'),
        ]
        
        for pattern, replacement, desc in patterns:
            matches = re.findall(pattern, content)
            if matches:
                content = re.sub(pattern, replacement, content)
                fixes_made += len(matches)
                print(f"  Fixed {len(matches)} {desc} properties")
        
        # 2. Fix selectors with extra semicolons
        pattern_sel = r'([.#][\w-]+(?:\s*[.#][\w-]+)*\s*)\{;'
        matches_sel = re.findall(pattern_sel, content)
        if matches_sel:
            content = re.sub(pattern_sel, r'\1{', content)
            fixes_made += len(matches_sel)
            print(f"  Fixed {len(matches_sel)} selectors with extra semicolons")
        
        # 3. Fix missing semicolons at end of declarations
        pattern_semi = r'([^;}])\n(\s*[a-zA-Z-]+\s*:)'
        matches_semi = re.findall(pattern_semi, content)
        if matches_semi:
            content = re.sub(pattern_semi, r'\1;\n\2', content)
            fixes_made += len(matches_semi)
            print(f"  Fixed {len(matches_semi)} missing semicolons")
        
        # 4. Fix orphaned properties (properties not within rules)
        lines = content.split('\n')
        fixed_lines = []
        in_rule = False
        brace_count = 0
        
        for line in lines:
            stripped = line.strip()
            
            # Count braces to track if we're inside a rule
            brace_count += stripped.count('{') - stripped.count('}')
            in_rule = brace_count > 0
            
            # If this looks like a CSS property but we're not in a rule, comment it out
            if not in_rule and re.match(r'^\s*[a-zA-Z-]+\s*:\s*[^;]+;?\s*$', stripped) and not stripped.startswith('/*'):
                fixed_lines.append(f"/* ORPHANED PROPERTY: {line} */")
                fixes_made += 1
            else:
                fixed_lines.append(line)
        
        if fixes_made > 0:
            content = '\n'.join(fixed_lines)
        
        # 5. Fix duplicate property declarations within the same rule
        # This is more complex and requires parsing rules
        rule_pattern = r'([^{}]+)\{([^{}]+)\}'
        
        def fix_rule_duplicates(match):
            selector = match.group(1).strip()
            declarations = match.group(2).strip()
            
            # Parse declarations
            decl_lines = [d.strip() for d in declarations.split('\n') if d.strip()]
            seen_props = {}
            fixed_decls = []
            local_fixes = 0
            
            for decl in decl_lines:
                if ':' in decl and not decl.startswith('/*'):
                    prop_match = re.match(r'([a-zA-Z-]+)\s*:\s*(.+?)(?:;|$)', decl)
                    if prop_match:
                        prop_name = prop_match.group(1).strip()
                        prop_value = prop_match.group(2).strip()
                        
                        if prop_name in seen_props:
                            # Keep the last declaration, comment out earlier ones
                            for i, (existing_decl, existing_prop) in enumerate(fixed_decls):
                                if existing_prop == prop_name:
                                    fixed_decls[i] = (f"/* DUPLICATE: {existing_decl} */", None)
                                    local_fixes += 1
                        
                        seen_props[prop_name] = prop_value
                        # Ensure semicolon
                        if not decl.endswith(';'):
                            decl = decl + ';'
                        fixed_decls.append((decl, prop_name))
                    else:
                        fixed_decls.append((decl, None))
                else:
                    fixed_decls.append((decl, None))
            
            nonlocal fixes_made
            fixes_made += local_fixes
            
            fixed_declarations = '\n    '.join([d[0] for d in fixed_decls if d[0]])
            return f"{selector} {{\n    {fixed_declarations}\n}}"
        
        original_fixes = fixes_made
        content = re.sub(rule_pattern, fix_rule_duplicates, content, flags=re.DOTALL)
        duplicate_fixes = fixes_made - original_fixes
        if duplicate_fixes > 0:
            print(f"  Fixed {duplicate_fixes} duplicate properties within rules")
        
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
    """Main function to comprehensively validate and fix CSS."""
    base_dir = "/Users/jongraves/Documents/Websites/WhimsicalFrog"
    
    css_files = [
        "css/main.css",
        "css/recovered_components.css", 
        "css/recovered_missing.css",
        "css/z-index.css"
    ]
    
    print("=== Comprehensive CSS Validator and Repair Tool ===")
    print("Performing deep syntax validation and repair...")
    print()
    
    total_files_fixed = 0
    
    for css_file in css_files:
        file_path = os.path.join(base_dir, css_file)
        if comprehensive_css_fix(file_path):
            total_files_fixed += 1
        print()
    
    print(f"=== Complete ===")
    print(f"Files processed: {len(css_files)}")
    print(f"Files with fixes: {total_files_fixed}")
    
    if total_files_fixed > 0:
        print("\nComprehensive CSS repairs completed. Attempting Vite build...")
    else:
        print("\nNo CSS syntax errors found.")

if __name__ == "__main__":
    main()
