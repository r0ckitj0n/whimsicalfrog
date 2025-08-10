const fs = require('fs');
const path = require('path');
const postcss = require('postcss');

// --- Configuration ---
const cssFilesToProcess = [
    'css/backgrounds.css',
    'css/utilities.css',
    'css/standard-classes.css',
    'css/components.css',
    'css/modals.css',
    'css/pages.css',
];

const outputFile = 'css/main.bundle.css';
const projectRoot = __dirname;

console.log('🚀 Starting CSS consolidation and deduplication process...');

// --- Main Function ---
async function consolidateCss() {
    const selectorMap = new Map();
    let totalRules = 0;

    // Step 1: Read all files and aggregate rules
    for (const file of cssFilesToProcess) {
        const filePath = path.join(projectRoot, file);
        if (!fs.existsSync(filePath)) {
            console.warn(`⚠️ Warning: File not found, skipping: ${filePath}`);
            continue;
        }

        console.log(`🔍 Reading and parsing ${file}...`);
        const css = fs.readFileSync(filePath, 'utf8');
        const root = postcss.parse(css, { from: filePath });

        root.walkRules(rule => {
            totalRules++;
            const selector = rule.selector.trim();

            if (!selectorMap.has(selector)) {
                selectorMap.set(selector, []);
            }

            // Store the declarations (key-value pairs) of the rule
            const declarations = [];
            rule.walkDecls(decl => {
                declarations.push({ prop: decl.prop, value: decl.value, important: decl.important });
            });
            selectorMap.get(selector).push(declarations);
        });
    }

    console.log(`
✅ Parsed a total of ${totalRules} rules from ${cssFilesToProcess.length} files.`);

    // Step 2: Merge the rules
    console.log('🧠 Merging duplicate selectors and resolving conflicts...');
    const finalRules = new Map();

    for (const [selector, ruleSets] of selectorMap.entries()) {
        const mergedDecls = new Map();
        for (const declSet of ruleSets) {
            for (const decl of declSet) {
                // The last rule in the cascade wins. By iterating in order, we naturally handle overrides.
                mergedDecls.set(decl.prop, { value: decl.value, important: decl.important });
            }
        }
        finalRules.set(selector, mergedDecls);
    }

    console.log(`✨ Reduced to ${finalRules.size} unique selectors.`);

    // Step 3: Generate the new CSS file
    console.log(`✍️ Generating new consolidated file: ${outputFile}...`);
    const newRoot = postcss.root();

    for (const [selector, declsMap] of finalRules.entries()) {
        const rule = postcss.rule({ selector });
        for (const [prop, { value, important }] of declsMap.entries()) {
            rule.append(postcss.decl({ prop, value, important }));
        }
        newRoot.append(rule);
    }

    const finalCss = newRoot.toString();
    fs.writeFileSync(path.join(projectRoot, outputFile), finalCss);

    console.log(`\n🎉 Success! All CSS has been consolidated into ${outputFile}.`);
    console.log('Next steps: Update js/main.js to import only this new file.');
}

// --- Run the script ---
consolidateCss().catch(err => {
    console.error('❌ An unexpected error occurred during CSS consolidation:', err);
});
