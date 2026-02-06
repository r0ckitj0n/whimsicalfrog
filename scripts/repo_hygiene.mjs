import { promises as fs } from 'fs';
import path from 'path';
import { exec } from 'child_process';
import { promisify } from 'util';

const execAsync = promisify(exec);

const targetExtensions = ['.php', '.ts', '.tsx', '.js', '.cjs', '.xjs', '.css', '.json', '.log', '.mjs', '.py', '.md'];
const excludeDirs = ['backups', 'node_modules', 'dist', '.agent', '.git', 'logs'];
const excludeFiles = ['all_files.txt', 'orphans_to_archive.json', 'repo_hygiene.mjs', 'orphan_progress.json', 'dump_db_schema.php', 'fetch_db_strings.php', 'db_strings.json', 'package.json', 'package-lock.json', 'composer.json', 'composer.lock', '.cursorrules', 'autostart.log', 'orphan_whitelist.json'];

const PROGRESS_FILE = 'orphan_progress.json';
const RESULTS_FILE = 'orphans_to_archive.json';
const DB_STRINGS_FILE = 'db_strings.json';
const WHITELIST_FILE = 'scripts/orphan_whitelist.json';

async function getFiles(dir) {
    const entries = await fs.readdir(dir, { withFileTypes: true });
    let files = [];
    for (const entry of entries) {
        if (excludeDirs.includes(entry.name)) continue;
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            files = files.concat(await getFiles(fullPath));
        } else if (targetExtensions.includes(path.extname(entry.name).toLowerCase())) {
            if (!excludeFiles.includes(entry.name)) {
                files.push(fullPath);
            }
        }
    }
    return files;
}

let dbReferences = new Set();
let whitelist = new Set();

async function initContext() {
    console.log('Loading context (DB strings & Whitelist)...');
    const projectRoot = process.cwd();

    // DB Strings
    try {
        const data = await fs.readFile(path.join(projectRoot, DB_STRINGS_FILE), 'utf8');
        dbReferences = new Set(JSON.parse(data));
        console.log('Loaded ' + dbReferences.size + ' unique strings from database.');
    } catch (e) {
        console.warn('DB strings not found. Run fetch_db_strings.php for full accuracy.');
    }

    // Whitelist
    try {
        const data = await fs.readFile(path.join(projectRoot, WHITELIST_FILE), 'utf8');
        const list = JSON.parse(data);
        whitelist = new Set(list.map(item => item.file));
        console.log('Loaded ' + whitelist.size + ' whitelisted files.');
    } catch (e) {
        console.warn('Whitelist not found at ' + WHITELIST_FILE);
    }
}

async function isReferenced(filePath, projectRoot) {
    const fileName = path.basename(filePath);
    const relPath = path.relative(projectRoot, filePath);
    const ext = path.extname(filePath).toLowerCase();
    const baseName = path.basename(filePath, path.extname(filePath));

    // Check Whitelist
    if (whitelist.has(relPath)) {
        return { type: 'whitelisted', details: 'Explicitly whitelisted in orphan_whitelist.json' };
    }

    // Check DB references
    for (let ref of dbReferences) {
        if (typeof ref === 'string' && (ref.includes(fileName) || ref.includes(relPath) || (ext === '.php' && ref.includes(baseName)))) {
            return { type: 'database', details: 'Referenced in DB string: "' + ref.substring(0, 100).replace(/"/g, "'") + '..."' };
        }
    }

    // 1. Exact filename search
    const escapedFileName = fileName.replace(/\./g, '\\.');
    const grepExact = 'grep -rnI "' + escapedFileName + '" . --exclude-dir={' + excludeDirs.join(',') + '} --exclude={' + excludeFiles.join(',') + ',"' + fileName + '"} | head -n 1';
    try {
        const { stdout } = await execAsync(grepExact, { cwd: projectRoot });
        if (stdout.trim().length > 0) return { type: 'filesystem_exact', details: stdout.trim().replace(/"/g, "'") };
    } catch (e) { }

    // 2. Whole-word search for baseName
    if (baseName.length >= 4) {
        const grepWord = 'grep -rnw "' + baseName + '" . --exclude-dir={' + excludeDirs.join(',') + '} --exclude={' + excludeFiles.join(',') + ',"' + fileName + '"} | head -n 1';
        try {
            const { stdout } = await execAsync(grepWord, { cwd: projectRoot });
            if (stdout.trim().length > 0) return { type: 'filesystem_word', details: stdout.trim().replace(/"/g, "'") };
        } catch (e) { }
    }

    // 3. Fallback for short names: check with path segments
    if (relPath.includes('/')) {
        const pathRef = relPath.substring(0, relPath.lastIndexOf('.'));
        const grepPath = 'grep -rnI "' + pathRef + '" . --exclude-dir={' + excludeDirs.join(',') + '} --exclude={' + excludeFiles.join(',') + ',"' + fileName + '"} | head -n 1';
        try {
            const { stdout } = await execAsync(grepPath, { cwd: projectRoot });
            if (stdout.trim().length > 0) return { type: 'filesystem_path', details: stdout.trim().replace(/"/g, "'") };
        } catch (e) { }
    }

    return null;
}

function isDynamicRuntimeEntrypoint(relPath) {
    if (relPath === 'AGENTS.md') return true;
    if (relPath.startsWith('documentation/')) return true;
    if (relPath.startsWith('reports/')) return true;
    if (relPath.startsWith('api/') && relPath.endsWith('.php')) return true;
    if (relPath.startsWith('includes/') && relPath.endsWith('.php')) return true;
    return false;
}

(async () => {
    const projectRoot = process.cwd();
    await initContext();

    const allFiles = await getFiles(projectRoot);
    console.log('Checking ' + allFiles.length + ' files with broad matching (logs excluded)...');

    const knownEntryPoints = [
        'index.html', 'router.php', 'vite.config.ts', 'pm2.config.cjs',
        'postcss.config.cjs', 'tailwind.config.cjs', 'stylelint.config.cjs',
        'package.json', 'composer.json', 'api_bootstrap.php', 'bootstrap.php',
        'src/entries/main.tsx', 'src/entries/App.tsx', 'vite-proxy.php',
        'README.md', 'TODO.md', 'KNOWLEDGE_CATALOG.md', 'netlify.toml', '.htaccess', '.env', '.user.ini'
    ];

    const processedSet = new Set();
    const orphans = [];
    const findings = {};

    for (const file of allFiles) {
        const relPath = path.relative(projectRoot, file);
        if (knownEntryPoints.some(entry => relPath === entry || relPath.endsWith('/' + entry))) {
            processedSet.add(relPath);
            findings[relPath] = { type: 'entry_point', details: 'Known entry point' };
            continue;
        }
        if (isDynamicRuntimeEntrypoint(relPath)) {
            processedSet.add(relPath);
            findings[relPath] = { type: 'runtime_entry_point', details: 'Dynamic runtime endpoint/helper path' };
            continue;
        }

        const ref = await isReferenced(file, projectRoot);
        if (ref) {
            findings[relPath] = ref;
            processedSet.add(relPath);
        } else {
            orphans.push(relPath);
            findings[relPath] = { type: 'none', details: 'No references found' };
            console.log('ORPHAN: ' + relPath);
            processedSet.add(relPath);
        }

        if (processedSet.size % 20 === 0) {
            process.stdout.write('.');
            if (processedSet.size % 100 === 0) console.log(' ' + processedSet.size + '/' + allFiles.length);
        }
    }

    const finalState = {
        processed: Array.from(processedSet),
        orphans: orphans,
        findings: findings
    };
    await fs.writeFile(path.join(projectRoot, PROGRESS_FILE), JSON.stringify(finalState, null, 2));
    await fs.writeFile(path.join(projectRoot, RESULTS_FILE), JSON.stringify(orphans, null, 2));

    console.log('\n--- Final Orphan List ---');
    console.log('Total Orphans: ' + orphans.length);
})();
