#!/usr/bin/env python3
"""
Comprehensive Settings Extractor for WhimsicalFrog
Extracts unique CSS, JavaScript, and z-index values from backup files
and integrates them with the current Vite system.
"""

import os
import re
import json
import hashlib
from pathlib import Path
from collections import defaultdict, Counter
import logging
from datetime import datetime

# Setup logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

class SettingsExtractor:
    def __init__(self, base_dir, backup_dir):
        self.base_dir = Path(base_dir)
        self.backup_dir = Path(backup_dir)
        self.unique_css_rules = {}
        self.unique_js_functions = {}
        self.z_index_values = Counter()
        self.css_selectors = set()
        self.js_modules = {}
        self.media_queries = set()
        self.keyframes = {}
        self.css_variables = {}
        
    def calculate_content_hash(self, content):
        """Calculate SHA256 hash of content for deduplication"""
        return hashlib.sha256(content.encode('utf-8')).hexdigest()[:8]
    
    def extract_css_rules(self, file_path):
        """Extract unique CSS rules, z-index values, selectors, media queries"""
        try:
            with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
            
            # Extract z-index values
            z_index_pattern = r'z-index\s*:\s*(-?\d+)'
            z_indexes = re.findall(z_index_pattern, content, re.IGNORECASE)
            for z_idx in z_indexes:
                self.z_index_values[int(z_idx)] += 1
            
            # Extract CSS custom properties (variables)
            css_var_pattern = r'(--[\w-]+)\s*:\s*([^;]+);'
            css_vars = re.findall(css_var_pattern, content)
            for var_name, var_value in css_vars:
                if var_name not in self.css_variables:
                    self.css_variables[var_name] = var_value.strip()
            
            # Extract complete CSS rules (selector + declarations)
            css_rule_pattern = r'([^{}]+)\{([^{}]+)\}'
            rules = re.findall(css_rule_pattern, content, re.MULTILINE | re.DOTALL)
            
            for selector, declarations in rules:
                selector = selector.strip()
                declarations = declarations.strip()
                
                if selector and declarations:
                    # Skip comment blocks
                    if '/*' in selector or '*/' in selector:
                        continue
                    
                    self.css_selectors.add(selector)
                    rule_hash = self.calculate_content_hash(f"{selector}{declarations}")
                    
                    if rule_hash not in self.unique_css_rules:
                        self.unique_css_rules[rule_hash] = {
                            'selector': selector,
                            'declarations': declarations,
                            'source': str(file_path),
                            'specificity': self.calculate_css_specificity(selector)
                        }
            
            # Extract media queries
            media_pattern = r'@media[^{]+\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}'
            media_queries = re.findall(media_pattern, content, re.MULTILINE | re.DOTALL)
            for mq in media_queries:
                self.media_queries.add(mq.strip())
            
            # Extract keyframes
            keyframe_pattern = r'@keyframes\s+([\w-]+)\s*\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}'
            keyframes = re.findall(keyframe_pattern, content, re.MULTILINE | re.DOTALL)
            for name, frames in keyframes:
                if name not in self.keyframes:
                    self.keyframes[name] = frames.strip()
                    
        except Exception as e:
            logger.warning(f"Error processing CSS file {file_path}: {e}")
    
    def calculate_css_specificity(self, selector):
        """Calculate CSS specificity for conflict resolution"""
        # Simple specificity calculation (IDs, classes, elements)
        ids = len(re.findall(r'#[\w-]+', selector))
        classes = len(re.findall(r'\.[\w-]+', selector))
        elements = len(re.findall(r'\b[a-z]+\b', selector))
        
        return (ids * 100) + (classes * 10) + elements
    
    def extract_js_functions(self, file_path):
        """Extract unique JavaScript functions, modules, and configurations"""
        try:
            with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
            
            # Extract function declarations
            func_pattern = r'function\s+([\w$]+)\s*\([^)]*\)\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}'
            functions = re.findall(func_pattern, content, re.MULTILINE | re.DOTALL)
            
            # Extract arrow functions and methods
            arrow_pattern = r'(const|let|var)\s+([\w$]+)\s*=\s*\([^)]*\)\s*=>\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}'
            arrow_funcs = re.findall(arrow_pattern, content, re.MULTILINE | re.DOTALL)
            
            # Extract object methods
            method_pattern = r'([\w$]+)\s*:\s*function\s*\([^)]*\)\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}'
            methods = re.findall(method_pattern, content, re.MULTILINE | re.DOTALL)
            
            # Extract module exports
            export_pattern = r'export\s+(?:default\s+)?(?:function\s+)?([\w$]+)'
            exports = re.findall(export_pattern, content)
            
            # Extract configuration objects
            config_pattern = r'(const|let|var)\s+([\w$]+(?:Config|Settings|Options))\s*=\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}'
            configs = re.findall(config_pattern, content, re.MULTILINE | re.DOTALL)
            
            # Store all extracted JavaScript elements
            all_js_elements = []
            all_js_elements.extend([(f[0] if isinstance(f, tuple) else f, 'function') for f in functions])
            all_js_elements.extend([(f[1], 'arrow_function') for f in arrow_funcs])
            all_js_elements.extend([(m, 'method') for m in methods])
            all_js_elements.extend([(e, 'export') for e in exports])
            all_js_elements.extend([(c[1], 'config') for c in configs])
            
            for element_name, element_type in all_js_elements:
                element_hash = self.calculate_content_hash(f"{element_name}_{element_type}")
                
                if element_hash not in self.unique_js_functions:
                    self.unique_js_functions[element_hash] = {
                        'name': element_name,
                        'type': element_type,
                        'source': str(file_path)
                    }
                    
        except Exception as e:
            logger.warning(f"Error processing JS file {file_path}: {e}")
    
    def scan_directory(self, directory, extensions):
        """Recursively scan directory for files with given extensions"""
        files_found = []
        try:
            for root, dirs, files in os.walk(directory):
                for file in files:
                    if any(file.lower().endswith(ext) for ext in extensions):
                        files_found.append(Path(root) / file)
        except Exception as e:
            logger.warning(f"Error scanning directory {directory}: {e}")
        
        return files_found
    
    def process_all_backups(self):
        """Process all backup directories and extract unique settings"""
        logger.info("Starting comprehensive backup analysis...")
        
        # Define backup locations to scan
        backup_locations = [
            self.backup_dir,
            self.backup_dir / "css",
            self.backup_dir / "css 2", 
            self.backup_dir / "css 3",
            self.backup_dir / "js",
            self.backup_dir / "js 2",
            self.backup_dir / "js 3",
            self.base_dir / "backups",
            self.base_dir / "backups" / "legacy_js"
        ]
        
        # Add numbered backup directories
        for i in range(2, 10):  # Check for numbered backups up to 9
            backup_locations.extend([
                self.backup_dir / f"WhimsicalFrog 2025-07-{i:02d}",
                self.backup_dir / f"WhimsicalFrog_630{chr(ord('a') + i - 2)}"
            ])
        
        css_files_processed = 0
        js_files_processed = 0
        
        for location in backup_locations:
            if not location.exists():
                continue
                
            logger.info(f"Scanning: {location}")
            
            # Process CSS files
            css_files = self.scan_directory(location, ['.css'])
            for css_file in css_files:
                self.extract_css_rules(css_file)
                css_files_processed += 1
            
            # Process JS files  
            js_files = self.scan_directory(location, ['.js'])
            for js_file in js_files:
                self.extract_js_functions(js_file)
                js_files_processed += 1
        
        logger.info(f"Processed {css_files_processed} CSS files and {js_files_processed} JS files")
        logger.info(f"Found {len(self.unique_css_rules)} unique CSS rules")
        logger.info(f"Found {len(self.unique_js_functions)} unique JS functions")
        logger.info(f"Found {len(self.z_index_values)} unique z-index values")
    
    def generate_consolidated_css(self):
        """Generate consolidated CSS file with unique rules"""
        output_path = self.base_dir / "css" / "recovered-unique.css"
        
        with open(output_path, 'w', encoding='utf-8') as f:
            f.write("/* Recovered Unique CSS Rules - Generated by extract_unique_settings.py */\n")
            f.write(f"/* Generated: {datetime.now().isoformat()} */\n\n")
            
            # Write CSS variables first
            if self.css_variables:
                f.write("/* CSS Custom Properties (Variables) */\n")
                f.write(":root {\n")
                for var_name, var_value in sorted(self.css_variables.items()):
                    f.write(f"  {var_name}: {var_value};\n")
                f.write("}\n\n")
            
            # Write keyframes
            if self.keyframes:
                f.write("/* Keyframe Animations */\n")
                for name, frames in self.keyframes.items():
                    f.write(f"@keyframes {name} {{\n{frames}\n}}\n\n")
            
            # Write media queries
            if self.media_queries:
                f.write("/* Media Queries */\n")
                for mq in sorted(self.media_queries):
                    f.write(f"{mq}\n\n")
            
            # Sort CSS rules by specificity (highest first)
            sorted_rules = sorted(self.unique_css_rules.values(), 
                                key=lambda x: x['specificity'], reverse=True)
            
            # Write regular CSS rules
            f.write("/* CSS Rules (sorted by specificity) */\n")
            for rule in sorted_rules:
                f.write(f"/* Source: {Path(rule['source']).name} */\n")
                f.write(f"{rule['selector']} {{\n")
                
                # Format declarations properly
                declarations = rule['declarations'].split(';')
                for decl in declarations:
                    decl = decl.strip()
                    if decl:
                        f.write(f"  {decl};\n")
                
                f.write("}\n\n")
        
        logger.info(f"Generated consolidated CSS: {output_path}")
        return output_path
    
    def generate_consolidated_js(self):
        """Generate consolidated JavaScript modules"""
        output_path = self.base_dir / "src" / "recovered-unique.js"
        
        with open(output_path, 'w', encoding='utf-8') as f:
            f.write("/* Recovered Unique JavaScript Functions - Generated by extract_unique_settings.py */\n")
            f.write(f"/* Generated: {datetime.now().isoformat()} */\n\n")
            
            # Group by type
            by_type = defaultdict(list)
            for func_info in self.unique_js_functions.values():
                by_type[func_info['type']].append(func_info)
            
            for func_type, functions in by_type.items():
                f.write(f"/* {func_type.upper()} FUNCTIONS */\n")
                for func in functions:
                    f.write(f"// Source: {Path(func['source']).name}\n")
                    f.write(f"// Function: {func['name']}\n")
                    f.write("// TODO: Extract full function implementation from source\n\n")
        
        logger.info(f"Generated JavaScript reference: {output_path}")
        return output_path
    
    def generate_z_index_map(self):
        """Generate z-index usage map and recommendations"""
        output_path = self.base_dir / "css" / "z-index-map.css"
        
        with open(output_path, 'w', encoding='utf-8') as f:
            f.write("/* Z-Index Usage Map - Generated by extract_unique_settings.py */\n")
            f.write(f"/* Generated: {datetime.now().isoformat()} */\n\n")
            
            f.write("/*\nZ-INDEX USAGE ANALYSIS:\n")
            
            # Sort z-index values by usage frequency
            sorted_z_indexes = sorted(self.z_index_values.items(), 
                                    key=lambda x: x[1], reverse=True)
            
            for z_value, count in sorted_z_indexes:
                f.write(f"z-index: {z_value} - Used {count} times\n")
            
            f.write("\nRECOMMENDED Z-INDEX HIERARCHY:\n")
            
            # Generate recommended hierarchy
            common_layers = [
                ("tooltip", 10000),
                ("modal-overlay", 9000), 
                ("modal-content", 9001),
                ("dropdown", 8000),
                ("header", 1000),
                ("navigation", 900),
                ("content", 1),
                ("background", -1)
            ]
            
            for layer_name, suggested_value in common_layers:
                f.write(f".{layer_name} {{ z-index: {suggested_value}; }}\n")
            
            f.write("*/\n\n")
            
            # Add CSS custom properties for z-index management
            f.write(":root {\n")
            for layer_name, suggested_value in common_layers:
                f.write(f"  --z-{layer_name}: {suggested_value};\n")
            f.write("}\n")
        
        logger.info(f"Generated z-index map: {output_path}")
        return output_path
    
    def generate_report(self):
        """Generate comprehensive extraction report"""
        report_path = self.base_dir / "logs" / f"settings_extraction_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
        
        report = {
            "extraction_date": datetime.now().isoformat(),
            "summary": {
                "unique_css_rules": len(self.unique_css_rules),
                "unique_js_functions": len(self.unique_js_functions),
                "z_index_values": len(self.z_index_values),
                "css_selectors": len(self.css_selectors),
                "media_queries": len(self.media_queries),
                "keyframes": len(self.keyframes),
                "css_variables": len(self.css_variables)
            },
            "z_index_usage": dict(self.z_index_values),
            "css_variables": self.css_variables,
            "keyframes": list(self.keyframes.keys()),
            "js_function_types": {
                func_type: len([f for f in self.unique_js_functions.values() if f['type'] == func_type])
                for func_type in set(f['type'] for f in self.unique_js_functions.values())
            }
        }
        
        with open(report_path, 'w', encoding='utf-8') as f:
            json.dump(report, f, indent=2)
        
        logger.info(f"Generated extraction report: {report_path}")
        return report_path

def main():
    """Main execution function"""
    base_dir = "/Users/jongraves/Documents/Websites/WhimsicalFrog"
    backup_dir = "/Users/jongraves/Documents/Websites/WhimsicalFrog - Backups"
    
    extractor = SettingsExtractor(base_dir, backup_dir)
    
    # Process all backup files
    extractor.process_all_backups()
    
    # Generate consolidated outputs
    css_file = extractor.generate_consolidated_css()
    js_file = extractor.generate_consolidated_js()
    z_index_file = extractor.generate_z_index_map()
    report_file = extractor.generate_report()
    
    print("\n" + "="*60)
    print("WHIMSICALFROG SETTINGS EXTRACTION COMPLETE")
    print("="*60)
    print(f"✅ Unique CSS Rules: {len(extractor.unique_css_rules)}")
    print(f"✅ Unique JS Functions: {len(extractor.unique_js_functions)}")
    print(f"✅ Z-Index Values: {len(extractor.z_index_values)}")
    print(f"✅ CSS Variables: {len(extractor.css_variables)}")
    print(f"✅ Keyframe Animations: {len(extractor.keyframes)}")
    print(f"✅ Media Queries: {len(extractor.media_queries)}")
    print("\nGenerated Files:")
    print(f"📄 {css_file}")
    print(f"📄 {js_file}")
    print(f"📄 {z_index_file}")
    print(f"📄 {report_file}")
    print("="*60)

if __name__ == "__main__":
    main()
