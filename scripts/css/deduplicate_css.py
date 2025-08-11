#!/usr/bin/env python3
"""
CSS Deduplication Script
Removes duplicate CSS blocks while preserving the first occurrence of each unique rule.
"""

import re
import sys
from collections import OrderedDict

def parse_css_blocks(css_content):
    """Parse CSS content into blocks and return deduplicated version."""
    
    # More precise pattern that handles nested braces and comments better
    block_pattern = r'([^{}]+?)\s*\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}'
    blocks = re.findall(block_pattern, css_content, re.DOTALL)
    
    seen_blocks = OrderedDict()
    duplicate_count = 0
    
    for selector, rules in blocks:
        selector = selector.strip()
        rules = rules.strip()
        
        # Skip malformed blocks or empty ones
        if not selector or not rules:
            continue
            
        # Validate CSS properties - skip blocks with invalid syntax
        if re.search(r'\b(px-\d+|py-\d+|rounded-\w+|text-\w+)\s*;', rules):
            print(f"Skipping malformed block: {selector[:30]}...")
            continue
            
        # Create a normalized key for comparison
        normalized_rules = re.sub(r'\s+', ' ', rules)
        normalized_key = f"{selector}|{normalized_rules}"
        
        if normalized_key not in seen_blocks:
            seen_blocks[normalized_key] = (selector, rules)
        else:
            duplicate_count += 1
            print(f"Duplicate found: {selector[:50]}...")
    
    print(f"Removed {duplicate_count} duplicate blocks")
    
    # Reconstruct CSS
    deduplicated_css = ""
    for selector, rules in seen_blocks.values():
        deduplicated_css += f"{selector} {{\n{rules}\n}}\n\n"
    
    return deduplicated_css

def deduplicate_css_file(input_file, output_file):
    """Deduplicate CSS file and save result."""
    
    try:
        with open(input_file, 'r', encoding='utf-8') as f:
            css_content = f.read()
        
        print(f"Original file size: {len(css_content)} characters")
        
        # Extract CSS custom properties (variables) first
        variables_pattern = r':root\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}'
        variables = re.findall(variables_pattern, css_content, re.DOTALL)
        
        # Remove variables from content for block processing
        css_without_variables = re.sub(variables_pattern, '', css_content, flags=re.DOTALL)
        
        # Process CSS blocks
        deduplicated = parse_css_blocks(css_without_variables)
        
        # Reconstruct with variables at the top
        final_css = ""
        for var_block in variables:
            final_css += var_block + "\n\n"
        final_css += deduplicated
        
        print(f"Deduplicated file size: {len(final_css)} characters")
        print(f"Reduction: {len(css_content) - len(final_css)} characters ({(len(css_content) - len(final_css)) / len(css_content) * 100:.1f}%)")
        
        with open(output_file, 'w', encoding='utf-8') as f:
            f.write(final_css)
        
        return True
        
    except Exception as e:
        print(f"Error: {e}")
        return False

if __name__ == "__main__":
    input_file = "/Users/jongraves/Documents/Websites/WhimsicalFrog/css/main.css"
    output_file = "/Users/jongraves/Documents/Websites/WhimsicalFrog/css/main_deduplicated.css"
    
    if deduplicate_css_file(input_file, output_file):
        print(f"Deduplication complete! Output saved to {output_file}")
    else:
        print("Deduplication failed!")
