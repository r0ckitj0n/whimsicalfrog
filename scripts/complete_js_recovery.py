#!/usr/bin/env python3
"""
Comprehensive JavaScript Recovery Script
Analyzes all JS files across different locations and recovers missing files
"""

import os
import shutil
import hashlib
from pathlib import Path
from datetime import datetime

class JSRecoveryManager:
    def __init__(self, base_path):
        self.base_path = Path(base_path)
        self.js_dir = self.base_path / "js"
        self.recovered_dir = self.js_dir / "recovered"
        self.legacy_js_dir = self.base_path / "backups" / "legacy_js"
        
        # Locations to search for JS files
        self.search_locations = [
            self.js_dir,
            self.recovered_dir,
            self.legacy_js_dir,
            self.base_path / "backups",  # Check for any other JS files in backups
        ]
        
        self.js_files = {}  # filename -> {path, size, hash, location}
        self.duplicates = []
        self.unique_files = []
        
    def calculate_file_hash(self, filepath):
        """Calculate MD5 hash of file content"""
        try:
            with open(filepath, 'rb') as f:
                return hashlib.md5(f.read()).hexdigest()
        except:
            return None
    
    def scan_js_files(self):
        """Scan all locations for JavaScript files"""
        print("🔍 Scanning all locations for JavaScript files...")
        
        for location in self.search_locations:
            if not location.exists():
                continue
                
            location_name = str(location.relative_to(self.base_path))
            print(f"   📁 Scanning {location_name}...")
            
            # Recursively find all .js files
            for js_file in location.rglob("*.js"):
                if js_file.is_file():
                    filename = js_file.name
                    size = js_file.stat().st_size
                    file_hash = self.calculate_file_hash(js_file)
                    
                    if filename not in self.js_files:
                        self.js_files[filename] = []
                    
                    self.js_files[filename].append({
                        'path': js_file,
                        'size': size,
                        'hash': file_hash,
                        'location': location_name,
                        'modified': datetime.fromtimestamp(js_file.stat().st_mtime)
                    })
    
    def analyze_files(self):
        """Analyze files for duplicates and unique versions"""
        print("\n📊 Analyzing JavaScript files...")
        
        for filename, versions in self.js_files.items():
            if len(versions) == 1:
                # Unique file
                self.unique_files.append({
                    'filename': filename,
                    'version': versions[0]
                })
            else:
                # Multiple versions - check if they're duplicates or different
                hashes = [v['hash'] for v in versions if v['hash']]
                unique_hashes = set(hashes)
                
                if len(unique_hashes) == 1:
                    # Same content, different locations
                    print(f"   🔄 Duplicate: {filename} found in {len(versions)} locations")
                    self.duplicates.append({
                        'filename': filename,
                        'versions': versions,
                        'type': 'duplicate'
                    })
                else:
                    # Different content
                    print(f"   ⚠️  Different versions: {filename} has {len(unique_hashes)} unique versions")
                    self.duplicates.append({
                        'filename': filename,
                        'versions': versions,
                        'type': 'different'
                    })
    
    def recover_missing_files(self):
        """Recover any missing files from backups"""
        print("\n🔧 Recovering missing JavaScript files...")
        
        recovered_count = 0
        
        for filename, versions in self.js_files.items():
            # Check if file exists in main js directory
            main_js_file = self.js_dir / filename
            
            if not main_js_file.exists():
                # Find the best version to recover
                best_version = None
                
                # Prefer files from recovered directory, then legacy_js, then others
                for version in versions:
                    if 'recovered' in version['location']:
                        best_version = version
                        break
                    elif 'legacy_js' in version['location']:
                        if not best_version or 'backups' in best_version['location']:
                            best_version = version
                    elif not best_version:
                        best_version = version
                
                if best_version:
                    try:
                        shutil.copy2(best_version['path'], main_js_file)
                        print(f"   ✅ Recovered {filename} from {best_version['location']}")
                        recovered_count += 1
                    except Exception as e:
                        print(f"   ❌ Failed to recover {filename}: {e}")
        
        print(f"\n🎉 Recovered {recovered_count} JavaScript files!")
    
    def update_newer_versions(self):
        """Update files in main js directory if newer versions exist in backups"""
        print("\n🔄 Checking for newer versions of existing files...")
        
        updated_count = 0
        
        for filename, versions in self.js_files.items():
            main_js_file = self.js_dir / filename
            
            if main_js_file.exists():
                # Find the main version
                main_version = None
                backup_versions = []
                
                for version in versions:
                    if version['path'] == main_js_file:
                        main_version = version
                    else:
                        backup_versions.append(version)
                
                if main_version and backup_versions:
                    # Find newer versions
                    newer_versions = [v for v in backup_versions if v['modified'] > main_version['modified']]
                    
                    if newer_versions:
                        # Use the newest version
                        newest = max(newer_versions, key=lambda x: x['modified'])
                        
                        # Only update if content is different
                        if newest['hash'] != main_version['hash']:
                            try:
                                shutil.copy2(newest['path'], main_js_file)
                                print(f"   ⬆️  Updated {filename} with newer version from {newest['location']}")
                                updated_count += 1
                            except Exception as e:
                                print(f"   ❌ Failed to update {filename}: {e}")
        
        print(f"\n🔄 Updated {updated_count} JavaScript files with newer versions!")
    
    def generate_report(self):
        """Generate a comprehensive recovery report"""
        report_path = self.base_path / "logs" / f"js_recovery_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.txt"
        report_path.parent.mkdir(exist_ok=True)
        
        with open(report_path, 'w') as f:
            f.write("JavaScript Recovery Report\n")
            f.write("=" * 50 + "\n\n")
            f.write(f"Generated: {datetime.now()}\n\n")
            
            f.write(f"Total unique filenames found: {len(self.js_files)}\n")
            f.write(f"Files with multiple versions: {len(self.duplicates)}\n")
            f.write(f"Unique files: {len(self.unique_files)}\n\n")
            
            f.write("SEARCH LOCATIONS:\n")
            for location in self.search_locations:
                if location.exists():
                    js_count = len(list(location.rglob("*.js")))
                    f.write(f"  - {location.relative_to(self.base_path)}: {js_count} files\n")
            
            f.write("\nFILES WITH MULTIPLE VERSIONS:\n")
            for dup in self.duplicates:
                f.write(f"\n{dup['filename']} ({dup['type']}):\n")
                for version in dup['versions']:
                    f.write(f"  - {version['location']}: {version['size']} bytes, modified {version['modified']}\n")
            
            f.write("\nUNIQUE FILES:\n")
            for unique in self.unique_files:
                f.write(f"  - {unique['filename']}: {unique['version']['location']}\n")
        
        print(f"\n📋 Recovery report saved to: {report_path}")
    
    def run_recovery(self):
        """Run the complete recovery process"""
        print("🚀 Starting JavaScript Recovery Process...")
        print("=" * 50)
        
        self.scan_js_files()
        self.analyze_files()
        self.recover_missing_files()
        self.update_newer_versions()
        self.generate_report()
        
        print("\n✅ JavaScript recovery process completed!")
        print(f"📁 Main JS directory: {self.js_dir}")
        print(f"📊 Total files processed: {len(self.js_files)}")

if __name__ == "__main__":
    # Run from WhimsicalFrog root directory
    base_path = "/Users/jongraves/Documents/Websites/WhimsicalFrog"
    
    recovery_manager = JSRecoveryManager(base_path)
    recovery_manager.run_recovery()
