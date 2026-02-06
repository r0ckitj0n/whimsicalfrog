import fs from 'fs';
import path from 'path';

const orphansFile = 'orphans_to_archive.json';
if (!fs.existsSync(orphansFile)) {
    console.error(`Error: ${orphansFile} not found. Run scripts/repo_hygiene.mjs first.`);
    process.exit(1);
}

const orphans = JSON.parse(fs.readFileSync(orphansFile, 'utf8'));
const backupDir = process.argv[2];

if (!backupDir) {
    console.error('Usage: node archive_orphans.mjs <backupDir>');
    process.exit(1);
}

// Files to EXPLICITLY preserve (Double-guard)
const preserve = [
    'src/types/api.ts',
    'src/types/global.d.ts',
    'src/core/types/socialMedia.ts',
    'scripts/repo_hygiene.mjs',
    'scripts/orphan_whitelist.json'
];

console.log(`Archiving ${orphans.length} files to ${backupDir}...`);

for (const orphan of orphans) {
    if (preserve.includes(orphan)) {
        console.log('Preserving: ' + orphan);
        continue;
    }

    const srcPath = path.join(process.cwd(), orphan);
    const destPath = path.join(process.cwd(), backupDir, orphan);

    if (fs.existsSync(srcPath)) {
        fs.mkdirSync(path.dirname(destPath), { recursive: true });
        fs.renameSync(srcPath, destPath);
        console.log('Moved: ' + orphan);
    } else {
        console.warn('File not found: ' + orphan);
    }
}

console.log('Archival complete.');
