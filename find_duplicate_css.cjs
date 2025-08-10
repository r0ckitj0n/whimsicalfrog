const fs = require('fs');
const path = require('path');
const postcss = require('postcss');
const selectorParser = require('postcss-selector-parser');

// --- Configuration ---
// List of CSS files to analyze
const cssFiles = [
    'css/components.css',
    'css/pages.css',
    'css/utilities.css',
    'css/backgrounds.css',
    'css/modals.css',
    'css/standard-classes.css'
];

const projectRoot = __dirname;
const selectors = new Map();

console.log('Starting CSS duplicate selector analysis...');

// --- Main Analysis Function ---
async function analyzeFiles() {
    for (const file of cssFiles) {
        const filePath = path.join(projectRoot, file);
        if (!fs.existsSync(filePath)) {
            console.warn(`⚠️  Warning: File not found, skipping: ${filePath}`);
            continue;
        }

        console.log(`🔍 Analyzing ${file}...`);
        const css = fs.readFileSync(filePath, 'utf8');

        try {
            await postcss().process(css, { from: filePath }).then(result => {
                result.root.walkRules(rule => {
                    const parser = selectorParser(selectorsAST => {
                        selectorsAST.walk(selectorNode => {
                            if (selectorNode.type === 'selector') {
                                const selectorText = selectorNode.toString().trim();
                                const location = {
                                    file: file,
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
