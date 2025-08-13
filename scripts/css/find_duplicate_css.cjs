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
                    const parser = selectorParser(selectorsAST => {
                        selectorsAST.walk(selectorNode => {
                            if (selectorNode.type === 'selector') {
                                const selectorText = selectorNode.toString().trim();
                                const location = {
                                    file: relPath,
                                    line: rule.source.start.line
                                };

                                if (!selectors.has(selectorText)) {
                                    selectors.set(selectorText, []);
                                }
                                selectors.get(selectorText).push(location);
                            }
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

    for (const [selector, locations] of selectors.entries()) {
        if (locations.length > 1) {
            duplicateCount++;
            console.log(`\n[${duplicateCount}] Selector: "${selector}"`);
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
