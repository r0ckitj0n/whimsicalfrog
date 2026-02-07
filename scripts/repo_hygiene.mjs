import { promises as fs } from 'fs';
import path from 'path';
import { execFile } from 'child_process';
import { promisify } from 'util';

const execFileAsync = promisify(execFile);

const targetExtensions = ['.php', '.ts', '.tsx', '.js', '.cjs', '.xjs', '.css', '.json', '.log', '.mjs', '.py', '.md'];
const excludeDirs = ['backups', 'documentation', 'node_modules', 'scripts', 'vendor', 'dist', '.agent', '.git', 'logs'];
const excludeFiles = ['all_files.txt', 'orphans_to_archive.json', 'repo_hygiene.mjs', 'orphan_progress.json', 'dump_db_schema.php', 'fetch_db_strings.php', 'db_strings.json', 'package.json', 'package-lock.json', 'composer.json', 'composer.lock', '.cursorrules', 'autostart.log', 'orphan_whitelist.json'];

const LOCAL_STATE_DIR = path.join('.local', 'state', 'repo_hygiene');
const PROGRESS_FILE = path.join(LOCAL_STATE_DIR, 'orphan_progress.json');
const RESULTS_FILE = path.join(LOCAL_STATE_DIR, 'orphans_to_archive.json');
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

async function getChangedFiles(projectRoot) {
    const commands = [
        ['diff', '--name-only', '--diff-filter=ACMR'],
        ['diff', '--cached', '--name-only', '--diff-filter=ACMR'],
        ['ls-files', '--others', '--exclude-standard']
    ];

    const changed = new Set();
    for (const args of commands) {
        try {
            const { stdout } = await execFileAsync('git', args, { cwd: projectRoot });
            for (const line of stdout.split('\n')) {
                const rel = line.trim();
                if (rel) changed.add(rel);
            }
        } catch (e) {
            // keep going; one command failing should not block the quick scan
        }
    }

    const result = [];
    for (const relPath of changed) {
        const fullPath = path.join(projectRoot, relPath);
        try {
            const stat = await fs.stat(fullPath);
            if (!stat.isFile()) continue;
        } catch (e) {
            continue;
        }

        const ext = path.extname(relPath).toLowerCase();
        if (!targetExtensions.includes(ext)) continue;
        if (excludeFiles.includes(path.basename(relPath))) continue;
        if (excludeDirs.some((dir) => relPath === dir || relPath.startsWith(dir + '/'))) continue;

        result.push(fullPath);
    }
    return result;
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

    const findReference = async (pattern, mode) => {
        const args = ['-n', '--hidden', '--no-messages'];
        if (mode === 'fixed' || mode === 'word') args.push('-F');
        if (mode === 'word') args.push('-w');
        for (const dir of excludeDirs) args.push('--glob', '!' + dir + '/**');
        for (const file of excludeFiles) args.push('--glob', '!**/' + file);
        args.push('--glob', '!**/' + fileName);
        args.push(pattern, '.');

        try {
            const { stdout } = await execFileAsync('rg', args, { cwd: projectRoot });
            const lines = stdout.split('\n').map((line) => line.trim()).filter(Boolean);
            const firstHit = lines.find((line) => !line.startsWith(relPath + ':'));
            if (!firstHit) return null;
            return firstHit.replace(/"/g, "'");
        } catch (e) {
            if (typeof e?.code === 'number' && e.code === 1) return null; // no matches
            return null;
        }
    };

    // 1. Exact filename search
    {
        const hit = await findReference(fileName, 'fixed');
        if (hit) return { type: 'filesystem_exact', details: hit };
    }

    // 2. Whole-word search for baseName
    if (baseName.length >= 4) {
        const hit = await findReference(baseName, 'word');
        if (hit) return { type: 'filesystem_word', details: hit };
    }

    // 3. Fallback for short names: check with path segments
    if (relPath.includes('/')) {
        const pathRef = relPath.substring(0, relPath.lastIndexOf('.'));
        const hit = await findReference(pathRef, 'fixed');
        if (hit) return { type: 'filesystem_path', details: hit };
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

function isLikelyActionableOrphan(relPath) {
    if (relPath.startsWith('documentation/')) return false;
    if (relPath.startsWith('reports/')) return false;
    return true;
}

function buildTopDirectorySummary(paths, maxItems = 10) {
    const counts = new Map();
    for (const relPath of paths) {
        const top = relPath.includes('/') ? relPath.split('/')[0] : '.';
        counts.set(top, (counts.get(top) || 0) + 1);
    }
    return Array.from(counts.entries())
        .sort((a, b) => b[1] - a[1])
        .slice(0, maxItems);
}

(async () => {
    const projectRoot = process.cwd();
    const args = new Set(process.argv.slice(2));
    const changedOnly = args.has('--changed') || args.has('--quick');
    await initContext();

    const allFiles = changedOnly ? await getChangedFiles(projectRoot) : await getFiles(projectRoot);
    if (changedOnly) {
        console.log('Quick mode enabled (--changed): checking ' + allFiles.length + ' changed files...');
    } else {
        console.log('Checking ' + allFiles.length + ' files with broad matching (logs excluded)...');
    }

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

    const actionableOrphans = orphans.filter(isLikelyActionableOrphan);
    const topDirs = buildTopDirectorySummary(orphans);

    const finalState = {
        processed: Array.from(processedSet),
        orphans: orphans,
        actionable_orphans: actionableOrphans,
        top_orphan_dirs: topDirs,
        findings: findings
    };
    await fs.mkdir(path.join(projectRoot, LOCAL_STATE_DIR), { recursive: true });
    await fs.writeFile(path.join(projectRoot, PROGRESS_FILE), JSON.stringify(finalState, null, 2));
    await fs.writeFile(path.join(projectRoot, RESULTS_FILE), JSON.stringify(orphans, null, 2));

    console.log('\n--- Final Orphan List ---');
    console.log('Total Orphans: ' + orphans.length);
    console.log('Likely Actionable Orphans: ' + actionableOrphans.length);
    if (topDirs.length > 0) {
        console.log('Top orphan directories:');
        for (const [dir, count] of topDirs) {
            console.log('  - ' + dir + ': ' + count);
        }
    }
    if (actionableOrphans.length > 0) {
        console.log('\nActionable sample (up to 20):');
        for (const orphan of actionableOrphans.slice(0, 20)) {
            console.log('  * ' + orphan);
        }
    }
})();
