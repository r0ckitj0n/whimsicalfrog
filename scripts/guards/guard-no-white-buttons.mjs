import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT_DIR = path.resolve(__dirname, '../../');

const SCAN_DIRS = ['sections', 'components', 'partials'];
const EXTENSIONS = ['.php', '.html', '.js'];

const BUTTON_TAG_REGEX = /<button\s+([^>]*?)>/gi;

let hasErrors = false;

function checkAttributes(tagContent, lineNum) {
    // Extract class
    const classMatch = tagContent.match(/class=["']([^"']*)["']/i);
    const classes = classMatch ? classMatch[1].split(/\s+/) : [];
    
    // Extract style
    const styleMatch = tagContent.match(/style=["']([^"']*)["']/i);
    const style = styleMatch ? styleMatch[1].toLowerCase() : '';

    const isBtnIcon = classes.includes('btn-icon') || classes.some(c => c.startsWith('btn-icon--'));
    const hasBgWhite = classes.includes('bg-white') || 
                       style.includes('background-color: white') || 
                       style.includes('background: white') || 
                       style.includes('background-color: #fff') || 
                       style.includes('background: #fff');
    
    const hasBgTransparent = classes.includes('bg-transparent') || 
                             style.includes('background-color: transparent') || 
                             style.includes('background: transparent');

    if (isBtnIcon) {
        if (hasBgWhite) {
            return `Line ${lineNum}: Icon button (btn-icon) has white background. It should be transparent.`;
        }
    } else {
        if (hasBgWhite) {
             return `Line ${lineNum}: Button has white background. Use a branded button class (e.g. btn-primary).`;
        }
        if (hasBgTransparent) {
             return `Line ${lineNum}: Non-icon button has transparent background. This is not allowed.`;
        }
    }
    return null;
}

function scanFile(filePath) {
    try {
        const content = fs.readFileSync(filePath, 'utf8');
        const fileErrors = [];

        let match;
        while ((match = BUTTON_TAG_REGEX.exec(content)) !== null) {
            const tagContent = match[1];
            // Calculate line number
            const lines = content.substring(0, match.index).split('\n');
            const lineNum = lines.length;
            
            const error = checkAttributes(tagContent, lineNum);
            if (error) {
                fileErrors.push(error);
            }
        }

        if (fileErrors.length > 0) {
            console.log(`\nFile: ${path.relative(ROOT_DIR, filePath)}`);
            fileErrors.forEach(err => console.log(err));
            hasErrors = true;
        }
    } catch (err) {
        console.error(`Error reading ${filePath}:`, err);
    }
}

function walkDir(dir) {
    const files = fs.readdirSync(dir);
    files.forEach(file => {
        const fullPath = path.join(dir, file);
        const stat = fs.statSync(fullPath);
        if (stat.isDirectory()) {
            walkDir(fullPath);
        } else {
            const ext = path.extname(file);
            if (EXTENSIONS.includes(ext)) {
                scanFile(fullPath);
            }
        }
    });
}

console.log('Scanning for button style violations...');

SCAN_DIRS.forEach(dir => {
    const fullDir = path.join(ROOT_DIR, dir);
    if (fs.existsSync(fullDir)) {
        walkDir(fullDir);
    }
});

if (hasErrors) {
    console.error('\n❌ Found button style violations! See above for details.');
    process.exit(1);
} else {
    console.log('\n✅ All buttons comply with style rules.');
    process.exit(0);
}
