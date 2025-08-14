const fs = require('fs');
const path = require('path');
const postcss = require('postcss');
const selectorParser = require('postcss-selector-parser');

// --- Configuration ---
// Recursively scan all CSS files under src/styles (Vite-managed source CSS)
const repoRoot = path.resolve(__dirname, '../../');
const stylesDir = path.join(repoRoot, 'src', 'styles');

function collectCssFiles(dir) {
    const entries = fs.readdirSync(dir, { withFileTypes: true });
    const files = [];
    for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            files.push(...collectCssFiles(fullPath));
        } else if (entry.isFile() && fullPath.endsWith('.css')) {
            files.push(fullPath);
        }
    }
    return files;
}

const cssFiles = fs.existsSync(stylesDir) ? collectCssFiles(stylesDir) : [];
// Map key: `${contextKey}||${selector}` -> locations
const selectors = new Map();

console.log('Starting CSS duplicate selector analysis...');

// --- Main Analysis Function ---
async function analyzeFiles() {
    for (const absPath of cssFiles) {
        const relPath = path.relative(repoRoot, absPath);
        if (!fs.existsSync(absPath)) {
            console.warn(`⚠️  Warning: File not found, skipping: ${absPath}`);
            continue;
        }

        console.log(`🔍 Analyzing ${relPath}...`);
        const css = fs.readFileSync(absPath, 'utf8');

        try {
            await postcss().process(css, { from: absPath }).then(result => {
                result.root.walkRules(rule => {
                    const perRuleSeen = new Set();
                    // Build at-rule context and exclude @keyframes rules
                    let parent = rule.parent;
                    const context = [];
                    let inKeyframes = false;
                    while (parent && parent.type !== 'root') {
                        if (parent.type === 'atrule') {
                            const name = String(parent.name || '').toLowerCase();
                            if (name === 'keyframes' || name.endsWith('keyframes')) {
                                inKeyframes = true;
                            }
                            context.push({ type: 'atrule', name: parent.name, params: parent.params || '' });
                        }
                        parent = parent.parent;
                    }
                    if (inKeyframes) return; // ignore keyframe step rules

                    const contextKey = context
                        .map(at => `@${at.name} ${at.params}`.trim())
                        .reverse()
                        .join(' > ');

                    const parser = selectorParser(selectorsAST => {
                        // Only iterate top-level selectors to avoid counting nested selector fragments
                        // inside pseudos like :has(), :is(), :not(), or :nth-child() arguments.
                        selectorsAST.each(selectorNode => {
                            const selectorText = selectorNode.toString().trim();
                            if (!selectorText) return;
                            const key = `${contextKey}||${selectorText}`;
                            if (perRuleSeen.has(key)) return; // avoid counting duplicates within the same rule
                            perRuleSeen.add(key);
                            const location = {
                                file: relPath,
                                line: rule.source && rule.source.start ? rule.source.start.line : 0
                            };

                            if (!selectors.has(key)) {
                                selectors.set(key, []);
                            }
                            selectors.get(key).push(location);
                        });
                    });
                    parser.processSync(rule.selector);
                });
            });
        } catch (error) {
            console.error(`❌ Error processing ${file}:`, error);
        }
    }

    generateReport();
}

// --- Report Generation ---
function generateReport() {
    console.log('\n--- CSS Duplicate Selector Report ---');
    let duplicateCount = 0;

    for (const [key, locations] of selectors.entries()) {
        if (locations.length > 1) {
            duplicateCount++;
            const [contextKey, selector] = key.split('||');
            const ctx = contextKey || '(global)';
            console.log(`\n[${duplicateCount}] Selector: "${selector}"`);
            console.log(`   Context: ${ctx}`);
            console.log(`   Found ${locations.length} times:`);
            locations.forEach(loc => {
                console.log(`   - In ${loc.file} on line ${loc.line}`);
            });
        }
    }

    if (duplicateCount === 0) {
        console.log('\n✅ No duplicate selectors found across the specified files. Great job!');
    } else {
        console.log(`\n--- End of Report ---`);
        console.log(`Found ${duplicateCount} duplicate selectors.`);
    }
}

// --- Run Analysis ---
analyzeFiles().catch(err => {
    console.error('An unexpected error occurred:', err);
});
