#!/usr/bin/env python3
"""
Comprehensive Settings Recovery Script for WhimsicalFrog
Systematically inventories, deduplicates, and recovers all unique CSS, JS, and z-index settings
"""

import re
import json
import hashlib
from pathlib import Path
from collections import defaultdict
import sys

class SettingsInventory:
    def __init__(self):
        self.css_rules = {}  # hash -> {rule, selectors, properties, source}
        self.css_variables = {}  # name -> {value, source, hash}
        self.js_functions = {}  # name -> {code, source, hash}
        self.js_modules = {}  # name -> {code, source, hash}
        self.js_configs = {}  # name -> {config, source, hash}
        self.z_index_rules = {}  # selector -> {value, source, hash}
        self.media_queries = {}  # query -> {rules, source, hash}
        self.keyframes = {}  # name -> {rules, source, hash}
        
    def hash_content(self, content):
        """Generate hash for content deduplication"""
        return hashlib.md5(content.encode('utf-8')).hexdigest()[:12]

class SettingsExtractor:
    def __init__(self):
        self.base_dir = Path("/Users/jongraves/Documents/Websites/WhimsicalFrog")
        self.backup_dir = Path("/Users/jongraves/Documents/Websites/WhimsicalFrog - Backups")
        self.current_inventory = SettingsInventory()
        self.backup_inventory = SettingsInventory()
        self._skip_dirnames = {
            'node_modules', '.git', 'dist', 'build', 'coverage', '.next', '.cache', 'vendor'
        }
        
    def should_skip(self, path: Path) -> bool:
        """Return True if this path should be skipped based on directory parts."""
        return any(part in self._skip_dirnames for part in path.parts)

    def extract_css_rules(self, css_content, source_file):
        """Extract all CSS rules and properties"""
        rules = {}
        
        # Remove comments
        css_content = re.sub(r'/\*.*?\*/', '', css_content, flags=re.DOTALL)
        
        # Extract CSS rules
        rule_pattern = r'([^{}]+)\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}'
        matches = re.findall(rule_pattern, css_content, re.MULTILINE | re.DOTALL)
        
        for selector, declarations in matches:
            selector = selector.strip()
            declarations = declarations.strip()
            
            if selector and declarations:
                # Extract individual properties
                properties = {}
                prop_pattern = r'([^:]+):\s*([^;]+);?'
                prop_matches = re.findall(prop_pattern, declarations)
                
                for prop_name, prop_value in prop_matches:
                    prop_name = prop_name.strip()
                    prop_value = prop_value.strip()
                    if prop_name and prop_value:
                        properties[prop_name] = prop_value
                
                if properties:
                    rule_content = f"{selector} {{ {declarations} }}"
                    rule_hash = self.current_inventory.hash_content(rule_content)
                    
                    rules[rule_hash] = {
                        'selector': selector,
                        'properties': properties,
                        'raw_declarations': declarations,
                        'source': str(source_file),
                        'rule_content': rule_content
                    }
        
        return rules
    
    def extract_css_variables(self, css_content, source_file):
        """Extract CSS custom properties (variables)"""
        variables = {}
        
        # Find CSS variables in :root or other selectors
        var_pattern = r'(--[^:]+):\s*([^;]+);'
        matches = re.findall(var_pattern, css_content)
        
        for var_name, var_value in matches:
            var_name = var_name.strip()
            var_value = var_value.strip()
            
            if var_name and var_value:
                var_hash = self.current_inventory.hash_content(f"{var_name}: {var_value}")
                variables[var_name] = {
                    'value': var_value,
                    'source': str(source_file),
                    'hash': var_hash
                }
        
        return variables
    
    def extract_z_index_rules(self, css_content, source_file):
        """Extract z-index specific rules"""
        z_rules = {}
        
        # Find z-index declarations with their selectors
        z_pattern = r'([^{]+)\{[^{}]*z-index\s*:\s*([^;]+);[^{}]*\}'
        matches = re.findall(z_pattern, css_content, re.MULTILINE)
        
        for selector, z_value in matches:
            selector = selector.strip()
            z_value = z_value.strip()
            
            if selector and z_value:
                rule_hash = self.current_inventory.hash_content(f"{selector} z-index: {z_value}")
                z_rules[selector] = {
                    'value': z_value,
                    'source': str(source_file),
                    'hash': rule_hash
                }
        
        return z_rules
    
    def extract_media_queries(self, css_content, source_file):
        """Extract media queries"""
        media_queries = {}
        
        # Extract @media rules
        media_pattern = r'@media\s*([^{]+)\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}'
        matches = re.findall(media_pattern, css_content, re.MULTILINE | re.DOTALL)
        
        for query, rules in matches:
            query = query.strip()
            rules = rules.strip()
            
            if query and rules:
                media_hash = self.current_inventory.hash_content(f"@media {query} {{ {rules} }}")
                media_queries[query] = {
                    'rules': rules,
                    'source': str(source_file),
                    'hash': media_hash
                }
        
        return media_queries
    
    def extract_keyframes(self, css_content, source_file):
        """Extract @keyframes animations"""
        keyframes = {}
        
        # Extract @keyframes rules
        keyframe_pattern = r'@keyframes\s+([^{]+)\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}'
        matches = re.findall(keyframe_pattern, css_content, re.MULTILINE | re.DOTALL)
        
        for name, rules in matches:
            name = name.strip()
            rules = rules.strip()
            
            if name and rules:
                keyframe_hash = self.current_inventory.hash_content(f"@keyframes {name} {{ {rules} }}")
                keyframes[name] = {
                    'rules': rules,
                    'source': str(source_file),
                    'hash': keyframe_hash
                }
        
        return keyframes
    
    def extract_js_functions(self, js_content, source_file):
        """Extract JavaScript functions"""
        functions = {}
        
        # Extract function declarations
        func_patterns = [
            r'function\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\([^)]*\)\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}',
            r'const\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*=\s*(?:async\s+)?(?:function\s*)?\([^)]*\)\s*=>\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}',
            r'let\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*=\s*(?:async\s+)?(?:function\s*)?\([^)]*\)\s*=>\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}',
            r'var\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*=\s*(?:async\s+)?(?:function\s*)?\([^)]*\)\s*=>\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}'
        ]
        
        for pattern in func_patterns:
            matches = re.findall(pattern, js_content, re.MULTILINE | re.DOTALL)
            for match in matches:
                if isinstance(match, tuple):
                    func_name = match[0]
                else:
                    func_name = match
                
                if func_name:
                    # Extract the full function code
                    func_pattern = rf'{re.escape(func_name)}.*?\{{.*?\}}'
                    full_match = re.search(func_pattern, js_content, re.DOTALL)
                    if full_match:
                        func_code = full_match.group(0)
                        func_hash = self.current_inventory.hash_content(func_code)
                        functions[func_name] = {
                            'code': func_code,
                            'source': str(source_file),
                            'hash': func_hash
                        }
        
        return functions
    
    def extract_js_configs(self, js_content, source_file):
        """Extract JavaScript configuration objects"""
        configs = {}
        
        # Extract configuration objects
        config_patterns = [
            r'const\s+([a-zA-Z_$][a-zA-Z0-9_$]*(?:Config|Settings|Options))\s*=\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}',
            r'let\s+([a-zA-Z_$][a-zA-Z0-9_$]*(?:Config|Settings|Options))\s*=\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}',
            r'var\s+([a-zA-Z_$][a-zA-Z0-9_$]*(?:Config|Settings|Options))\s*=\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}'
        ]
        
        for pattern in config_patterns:
            matches = re.findall(pattern, js_content, re.MULTILINE | re.DOTALL)
            for config_name in matches:
                if config_name:
                    # Extract the full config object
                    config_pattern = rf'{re.escape(config_name)}\s*=\s*\{{.*?\}}'
                    full_match = re.search(config_pattern, js_content, re.DOTALL)
                    if full_match:
                        config_code = full_match.group(0)
                        config_hash = self.current_inventory.hash_content(config_code)
                        configs[config_name] = {
                            'config': config_code,
                            'source': str(source_file),
                            'hash': config_hash
                        }
        
        return configs
    
    def process_css_file(self, file_path, inventory):
        """Process a single CSS file"""
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                css_content = f.read()
            
            # Extract various CSS components
            css_rules = self.extract_css_rules(css_content, file_path)
            css_variables = self.extract_css_variables(css_content, file_path)
            z_index_rules = self.extract_z_index_rules(css_content, file_path)
            media_queries = self.extract_media_queries(css_content, file_path)
            keyframes = self.extract_keyframes(css_content, file_path)
            
            # Add to inventory
            inventory.css_rules.update(css_rules)
            inventory.css_variables.update(css_variables)
            inventory.z_index_rules.update(z_index_rules)
            inventory.media_queries.update(media_queries)
            inventory.keyframes.update(keyframes)
            
            return len(css_rules), len(css_variables), len(z_index_rules)
            
        except Exception as e:
            print(f"❌ Error processing CSS file {file_path}: {e}")
            return 0, 0, 0
    
    def process_js_file(self, file_path, inventory):
        """Process a single JavaScript file"""
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                js_content = f.read()
            
            # Extract JavaScript components
            js_functions = self.extract_js_functions(js_content, file_path)
            js_configs = self.extract_js_configs(js_content, file_path)
            
            # Add to inventory
            inventory.js_functions.update(js_functions)
            inventory.js_configs.update(js_configs)
            
            return len(js_functions), len(js_configs)
            
        except Exception as e:
            print(f"❌ Error processing JS file {file_path}: {e}")
            return 0, 0
    
    def scan_directory(self, directory, inventory, description):
        """Scan a directory for CSS and JS files"""
        print(f"\n🔍 Scanning {description}: {directory}")
        
        if not directory.exists():
            print(f"❌ Directory not found: {directory}")
            return
        
        css_files = [p for p in directory.rglob("*.css") if p.is_file() and not self.should_skip(p)]
        js_files = [p for p in directory.rglob("*.js") if p.is_file() and not self.should_skip(p)]
        
        print(f"   Found {len(css_files)} CSS files, {len(js_files)} JS files")
        
        total_css_rules = 0
        total_css_vars = 0
        total_z_index = 0
        total_js_funcs = 0
        total_js_configs = 0
        
        # Process CSS files
        for css_file in css_files:
            rules, vars, z_indexes = self.process_css_file(css_file, inventory)
            total_css_rules += rules
            total_css_vars += vars
            total_z_index += z_indexes
        
        # Process JS files
        for js_file in js_files:
            funcs, configs = self.process_js_file(js_file, inventory)
            total_js_funcs += funcs
            total_js_configs += configs
        
        print(f"   ✅ Extracted: {total_css_rules} CSS rules, {total_css_vars} variables, {total_z_index} z-index rules")
        print(f"   ✅ Extracted: {total_js_funcs} JS functions, {total_js_configs} JS configs")
    
    def find_missing_settings(self):
        """Compare current vs backup inventory to find missing settings"""
        missing = {
            'css_rules': {},
            'css_variables': {},
            'z_index_rules': {},
            'media_queries': {},
            'keyframes': {},
            'js_functions': {},
            'js_configs': {}
        }
        
        # Find missing CSS rules
        current_rule_hashes = set(self.current_inventory.css_rules.keys())
        backup_rule_hashes = set(self.backup_inventory.css_rules.keys())
        missing_rule_hashes = backup_rule_hashes - current_rule_hashes
        
        for rule_hash in missing_rule_hashes:
            missing['css_rules'][rule_hash] = self.backup_inventory.css_rules[rule_hash]
        
        # Find missing CSS variables
        current_vars = set(self.current_inventory.css_variables.keys())
        backup_vars = set(self.backup_inventory.css_variables.keys())
        missing_vars = backup_vars - current_vars
        
        for var_name in missing_vars:
            missing['css_variables'][var_name] = self.backup_inventory.css_variables[var_name]
        
        # Find missing z-index rules
        current_z_selectors = set(self.current_inventory.z_index_rules.keys())
        backup_z_selectors = set(self.backup_inventory.z_index_rules.keys())
        missing_z_selectors = backup_z_selectors - current_z_selectors
        
        for selector in missing_z_selectors:
            missing['z_index_rules'][selector] = self.backup_inventory.z_index_rules[selector]
        
        # Find missing media queries
        current_media = set(self.current_inventory.media_queries.keys())
        backup_media = set(self.backup_inventory.media_queries.keys())
        missing_media = backup_media - current_media
        
        for query in missing_media:
            missing['media_queries'][query] = self.backup_inventory.media_queries[query]
        
        # Find missing keyframes
        current_keyframes = set(self.current_inventory.keyframes.keys())
        backup_keyframes = set(self.backup_inventory.keyframes.keys())
        missing_keyframes_names = backup_keyframes - current_keyframes
        
        for name in missing_keyframes_names:
            missing['keyframes'][name] = self.backup_inventory.keyframes[name]
        
        # Find missing JS functions
        current_funcs = set(self.current_inventory.js_functions.keys())
        backup_funcs = set(self.backup_inventory.js_functions.keys())
        missing_funcs = backup_funcs - current_funcs
        
        for func_name in missing_funcs:
            missing['js_functions'][func_name] = self.backup_inventory.js_functions[func_name]
        
        # Find missing JS configs
        current_configs = set(self.current_inventory.js_configs.keys())
        backup_configs = set(self.backup_inventory.js_configs.keys())
        missing_configs = backup_configs - current_configs
        
        for config_name in missing_configs:
            missing['js_configs'][config_name] = self.backup_inventory.js_configs[config_name]
        
        return missing
    
    def generate_missing_css_file(self, missing_settings):
        """Generate CSS file with missing settings"""
        output_file = self.base_dir / "src" / "styles" / "recovered" / "comprehensive-missing-styles.css"
        # Ensure output directory exists
        output_file.parent.mkdir(parents=True, exist_ok=True)

        with open(output_file, 'w') as f:
            f.write("/* COMPREHENSIVE MISSING STYLES - GENERATED FROM BACKUP ANALYSIS */\n\n")
            
            # Missing CSS Variables
            if missing_settings['css_variables']:
                f.write("/* === MISSING CSS VARIABLES === */\n")
                f.write(":root {\n")
                for var_name, var_data in sorted(missing_settings['css_variables'].items()):
                    f.write(f"  {var_name}: {var_data['value']}; /* from {var_data['source']} */\n")
                f.write("}\n\n")
            
            # Missing CSS Rules
            if missing_settings['css_rules']:
                f.write("/* === MISSING CSS RULES === */\n")
                for rule_hash, rule_data in missing_settings['css_rules'].items():
                    f.write(f"/* Source: {rule_data['source']} */\n")
                    f.write(f"{rule_data['rule_content']}\n\n")
            
            # Missing Z-Index Rules
            if missing_settings['z_index_rules']:
                f.write("/* === MISSING Z-INDEX RULES === */\n")
                for selector, z_data in missing_settings['z_index_rules'].items():
                    f.write(f"/* Source: {z_data['source']} */\n")
                    f.write(f"{selector} {{\n  z-index: {z_data['value']};\n}}\n\n")
            
            # Missing Media Queries
            if missing_settings['media_queries']:
                f.write("/* === MISSING MEDIA QUERIES === */\n")
                for query, media_data in missing_settings['media_queries'].items():
                    f.write(f"/* Source: {media_data['source']} */\n")
                    f.write(f"@media {query} {{\n{media_data['rules']}\n}}\n\n")
            
            # Missing Keyframes
            if missing_settings['keyframes']:
                f.write("/* === MISSING KEYFRAMES === */\n")
                for name, keyframe_data in missing_settings['keyframes'].items():
                    f.write(f"/* Source: {keyframe_data['source']} */\n")
                    f.write(f"@keyframes {name} {{\n{keyframe_data['rules']}\n}}\n\n")
        
        return output_file
    
    def generate_missing_js_file(self, missing_settings):
        """Generate JavaScript file with missing settings"""
        output_file = self.base_dir / "src" / "recovered" / "comprehensive-missing-functions.js"
        # Ensure output directory exists
        output_file.parent.mkdir(parents=True, exist_ok=True)

        with open(output_file, 'w') as f:
            f.write("/* COMPREHENSIVE MISSING JAVASCRIPT - GENERATED FROM BACKUP ANALYSIS */\n\n")
            
            # Missing JS Functions
            if missing_settings['js_functions']:
                f.write("/* === MISSING JAVASCRIPT FUNCTIONS === */\n\n")
                for func_name, func_data in missing_settings['js_functions'].items():
                    f.write(f"/* Source: {func_data['source']} */\n")
                    f.write(f"{func_data['code']}\n\n")
            
            # Missing JS Configs
            if missing_settings['js_configs']:
                f.write("/* === MISSING JAVASCRIPT CONFIGURATIONS === */\n\n")
                for config_name, config_data in missing_settings['js_configs'].items():
                    f.write(f"/* Source: {config_data['source']} */\n")
                    f.write(f"{config_data['config']}\n\n")
        
        return output_file
    
    def generate_analysis_report(self, missing_settings):
        """Generate comprehensive analysis report"""
        report_file = self.base_dir / "logs" / "analysis-report.json"
        # Ensure logs directory exists
        report_file.parent.mkdir(parents=True, exist_ok=True)
        
        report = {
            'analysis_timestamp': str(Path().resolve()),
            'directories_scanned': {
                'current': str(self.base_dir),
                'backup': str(self.backup_dir)
            },
            'current_inventory_summary': {
                'css_rules': len(self.current_inventory.css_rules),
                'css_variables': len(self.current_inventory.css_variables),
                'z_index_rules': len(self.current_inventory.z_index_rules),
                'media_queries': len(self.current_inventory.media_queries),
                'keyframes': len(self.current_inventory.keyframes),
                'js_functions': len(self.current_inventory.js_functions),
                'js_configs': len(self.current_inventory.js_configs)
            },
            'backup_inventory_summary': {
                'css_rules': len(self.backup_inventory.css_rules),
                'css_variables': len(self.backup_inventory.css_variables),
                'z_index_rules': len(self.backup_inventory.z_index_rules),
                'media_queries': len(self.backup_inventory.media_queries),
                'keyframes': len(self.backup_inventory.keyframes),
                'js_functions': len(self.backup_inventory.js_functions),
                'js_configs': len(self.backup_inventory.js_configs)
            },
            'missing_settings_summary': {
                'css_rules': len(missing_settings['css_rules']),
                'css_variables': len(missing_settings['css_variables']),
                'z_index_rules': len(missing_settings['z_index_rules']),
                'media_queries': len(missing_settings['media_queries']),
                'keyframes': len(missing_settings['keyframes']),
                'js_functions': len(missing_settings['js_functions']),
                'js_configs': len(missing_settings['js_configs'])
            },
            'missing_details': {
                'css_variables': list(missing_settings['css_variables'].keys()),
                'z_index_selectors': list(missing_settings['z_index_rules'].keys()),
                'media_queries': list(missing_settings['media_queries'].keys()),
                'keyframes': list(missing_settings['keyframes'].keys()),
                'js_functions': list(missing_settings['js_functions'].keys()),
                'js_configs': list(missing_settings['js_configs'].keys())
            }
        }
        
        with open(report_file, 'w') as f:
            json.dump(report, f, indent=2)
        
        return report_file
    
    def run_comprehensive_analysis(self):
        """Run the complete comprehensive analysis"""
        print("🚀 COMPREHENSIVE SETTINGS RECOVERY ANALYSIS")
        print("=" * 60)
        
        # Scan current project
        print("\n📊 PHASE 1: CURRENT PROJECT INVENTORY")
        self.scan_directory(self.base_dir / "src" / "styles", self.current_inventory, "Current CSS")
        self.scan_directory(self.base_dir / "src", self.current_inventory, "Current JS")
        
        # Scan backup locations
        print("\n📊 PHASE 2: BACKUP INVENTORY")
        self.scan_directory(self.backup_dir, self.backup_inventory, "Backup Directory")
        
        # Find missing settings
        print("\n📊 PHASE 3: GAP ANALYSIS")
        print("🔍 Comparing current vs backup inventories...")
        missing_settings = self.find_missing_settings()
        
        # Generate output files
        print("\n📊 PHASE 4: GENERATING RECOVERY FILES")
        css_file = self.generate_missing_css_file(missing_settings)
        js_file = self.generate_missing_js_file(missing_settings)
        report_file = self.generate_analysis_report(missing_settings)
        
        # Summary
        print("\n🎉 COMPREHENSIVE ANALYSIS COMPLETE!")
        print("=" * 60)
        print(f"📄 Missing CSS file: {css_file}")
        print(f"📄 Missing JS file: {js_file}")
        print(f"📄 Analysis report: {report_file}")
        print("\nMISSING SETTINGS SUMMARY:")
        print(f"  • {len(missing_settings['css_rules'])} CSS rules")
        print(f"  • {len(missing_settings['css_variables'])} CSS variables")
        print(f"  • {len(missing_settings['z_index_rules'])} Z-index rules")
        print(f"  • {len(missing_settings['media_queries'])} Media queries")
        print(f"  • {len(missing_settings['keyframes'])} Keyframe animations")
        print(f"  • {len(missing_settings['js_functions'])} JavaScript functions")
        print(f"  • {len(missing_settings['js_configs'])} JavaScript configurations")
        
        return missing_settings, css_file, js_file, report_file

def main():
    try:
        extractor = SettingsExtractor()
        missing_settings, css_file, js_file, report_file = extractor.run_comprehensive_analysis()
        
        print(f"\n✅ SUCCESS: Analysis complete!")
        print(f"   Import {css_file.name} and {js_file.name} to restore missing functionality.")
        
    except Exception as e:
        print(f"❌ FATAL ERROR: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
