#!/usr/bin/env python3
"""
JavaScript Recovery Script for WhimsicalFrog
Recovers and consolidates JavaScript files from backups, similar to CSS recovery
"""

import os
import shutil
import glob
from pathlib import Path

def recover_javascript():
    """Recover JavaScript files from backups and consolidate"""
    
    project_root = Path("/Users/jongraves/Documents/Websites/WhimsicalFrog")
    js_dir = project_root / "js"
    backup_dir = project_root / "backups"
    
    # Key JavaScript files to recover from backups
    key_js_files = [
        "backups/legacy_js/central-functions.js",
        "backups/legacy_js/wf-unified.js", 
        "backups/legacy_js/bundle.js",
        "backups/legacy_js/whimsical-frog-core.js",
        "backups/legacy_js/sales-checker.js",
        "backups/legacy_js/search.js",
        "backups/legacy_js/utils.js",
        "backups/legacy_js/room-coordinate-manager.js",
        "backups/legacy_js/room-css-manager.js",
        "backups/legacy_js/room-event-manager.js",
        "backups/legacy_js/room-modal-manager.js"
    ]
    
    # Create recovery directory
    recovery_dir = js_dir / "recovered"
    recovery_dir.mkdir(exist_ok=True)
    
    print("=== WhimsicalFrog JavaScript Recovery ===")
    print(f"Project root: {project_root}")
    print(f"Recovery directory: {recovery_dir}")
    
    recovered_files = []
    
    # Recover individual key files
    for js_file_path in key_js_files:
        full_path = project_root / js_file_path
        if full_path.exists():
            filename = full_path.name
            dest_path = recovery_dir / filename
            
            # Copy and track
            shutil.copy2(full_path, dest_path)
            file_size = dest_path.stat().st_size
            recovered_files.append((filename, file_size))
            print(f"Recovered: {filename} ({file_size:,} bytes)")
    
    # Search for additional JS files in backup directories
    backup_patterns = [
        "backups/legacy_bigfiles/**/*.js",
        "backups/**/js/**/*.js",
        "~Archives/**/*.js" if (project_root / "~Archives").exists() else None
    ]
    
    additional_files = []
    for pattern in backup_patterns:
        if pattern:
            for js_file in glob.glob(str(project_root / pattern), recursive=True):
                js_path = Path(js_file)
                if js_path.exists() and js_path.name not in [f[0] for f in recovered_files]:
                    dest_path = recovery_dir / f"additional_{js_path.name}"
                    shutil.copy2(js_path, dest_path)
                    file_size = dest_path.stat().st_size
                    additional_files.append((js_path.name, file_size))
                    print(f"Additional: {js_path.name} ({file_size:,} bytes)")
    
    # Summary
    total_files = len(recovered_files) + len(additional_files)
    total_size = sum(f[1] for f in recovered_files) + sum(f[1] for f in additional_files)
    
    print(f"\n=== Recovery Summary ===")
    print(f"Key files recovered: {len(recovered_files)}")
    print(f"Additional files recovered: {len(additional_files)}")
    print(f"Total files: {total_files}")
    print(f"Total size: {total_size:,} bytes ({total_size/1024:.1f} KB)")
    
    # Next steps guidance
    print(f"\n=== Next Steps ===")
    print(f"1. Review recovered files in: {recovery_dir}")
    print(f"2. Consolidate critical functions into unified modules")
    print(f"3. Update Vite app.js to import recovered functionality")
    print(f"4. Test and deduplicate JavaScript")
    
    return total_files, total_size

if __name__ == "__main__":
    recover_javascript()
