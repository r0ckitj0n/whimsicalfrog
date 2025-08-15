#!/usr/bin/env node
// Convert console.log(...) to logger.debug(...) across src JS files
// - Adds an import for src/core/logger.js if missing, with the correct relative path
// - Leaves console.warn/error untouched
import fs from 'fs';
import path from 'path';
import { glob } from 'glob';
import recast from 'recast';
import * as babelParser from '@babel/parser';

const parser = {
  parse(source) {
    return babelParser.parse(source, {
      sourceType: 'module',
      plugins: [
        'jsx',
        'classProperties',
        'optionalChaining',
        'nullishCoalescingOperator',
        'dynamicImport',
        'importMeta',
        'topLevelAwait',
      ],
    });
  },
};

const projectRoot = process.cwd();
const srcDir = path.join(projectRoot, 'src');
const loggerAbs = path.join(srcDir, 'core', 'logger.js');

function toPosix(p) { return p.split(path.sep).join('/'); }

function ensureLoggerImport(ast, fileAbsPath) {
  const b = recast.types.builders;
  let hasLoggerImport = false;
  recast.types.visit(ast, {
    visitImportDeclaration(pathNode) {
      const imp = pathNode.value;
      if (imp && imp.source && typeof imp.source.value === 'string') {
        const src = imp.source.value;
        // Resolve to absolute to compare to loggerAbs when possible
        const resolved = path.resolve(path.dirname(fileAbsPath), src);
        if (resolved === loggerAbs || /(^|\/)core\/logger\.js$/.test(src)) {
          hasLoggerImport = true;
          return false; // stop visiting imports
        }
      }
      this.traverse(pathNode);
    }
  });
  if (hasLoggerImport) return false;

  // Compute relative import from file to logger.js
  let rel = toPosix(path.relative(path.dirname(fileAbsPath), loggerAbs));
  if (!rel.startsWith('.')) rel = './' + rel;

  const importDecl = b.importDeclaration([
    b.importDefaultSpecifier(b.identifier('logger')),
  ], b.stringLiteral(rel));

  const program = ast.program || ast;
  program.body.unshift(importDecl);
  return true;
}

function transformConsoleLogs(ast) {
  let changed = 0;
  recast.types.visit(ast, {
    visitCallExpression(p) {
      const n = p.value;
      const callee = n.callee;
      if (!callee) { this.traverse(p); return; }

      const isMember = callee.type === 'MemberExpression' || callee.type === 'OptionalMemberExpression';
      if (isMember) {
        const obj = callee.object;
        const prop = callee.property;
        let method = null;
        if (!callee.computed && prop && prop.type === 'Identifier') {
          method = prop.name;
        } else if (callee.computed && prop && (prop.type === 'Literal' || prop.type === 'StringLiteral')) {
          method = prop.value;
        }
        if (obj && obj.type === 'Identifier' && obj.name === 'console' && method) {
          const map = { log: 'debug', info: 'info', debug: 'debug' };
          if (map[method]) {
            // Replace console.<method>(...) with logger.<mapped>(...)
            callee.object = { type: 'Identifier', name: 'logger' };
            callee.property = { type: 'Identifier', name: map[method] };
            callee.computed = false;
            changed++;
          }
        }
      }
      this.traverse(p);
    }
  });
  return changed > 0;
}

async function run() {
  const cliFiles = process.argv.slice(2).map(p => path.isAbsolute(p) ? p : path.join(projectRoot, p));
  let files;
  if (cliFiles.length > 0) {
    // Only operate on files inside src/** and with .js extension
    files = cliFiles
      .filter(p => p.endsWith('.js'))
      .filter(p => toPosix(p).includes('/src/'))
      .filter(p => !toPosix(p).endsWith('/src/core/logger.js'));
  } else {
    files = await glob('src/**/*.js', {
      cwd: projectRoot,
      absolute: true,
      ignore: [
        'src/core/logger.js',
      ],
    });
  }
  let totalChanged = 0;
  for (const abs of files) {
    const code = fs.readFileSync(abs, 'utf8');
    const ast = recast.parse(code, { parser });
    const changedCalls = transformConsoleLogs(ast);
    let addedImport = false;
    if (changedCalls) {
      addedImport = ensureLoggerImport(ast, abs);
      const output = recast.print(ast).code;
      if (output !== code) {
        fs.writeFileSync(abs, output, 'utf8');
        totalChanged++;
        const rel = path.relative(projectRoot, abs);
        console.log(`[convert-logs] Updated ${rel}${addedImport ? ' (+import)' : ''}`);
      }
    }
  }
  console.log(`[convert-logs] Done. Files updated: ${totalChanged}`);
}

run().catch(err => {
  console.error('[convert-logs] Failed:', err);
  process.exit(1);
});
