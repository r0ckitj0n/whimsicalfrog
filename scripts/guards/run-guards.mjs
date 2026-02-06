#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';

const repoRoot = process.cwd();
const registryPath = path.join(repoRoot, 'scripts/guards/registry.json');

function die(msg, code = 1) {
  console.error(`[guard-runner] ${msg}`);
  process.exit(code);
}

if (!fs.existsSync(registryPath)) die(`registry not found at ${registryPath}`);

let registry;
try {
  registry = JSON.parse(fs.readFileSync(registryPath, 'utf8'));
} catch (err) {
  die(`failed to parse registry: ${err.message}`);
}

const guards = registry?.guards || {};
const modes = registry?.modes || {};

const argv = process.argv.slice(2);
const dashIndex = argv.indexOf('--');
let extraArgs = [];
let primaryArgs = argv;
if (dashIndex !== -1) {
  extraArgs = argv.slice(dashIndex + 1);
  primaryArgs = argv.slice(0, dashIndex);
}

let mode;
const args = [];
for (let i = 0; i < primaryArgs.length; i += 1) {
  const arg = primaryArgs[i];
  if (arg.startsWith('--mode=')) {
    mode = arg.replace('--mode=', '');
    continue;
  }
  if (arg === '--mode') {
    mode = primaryArgs[i + 1];
    i += 1; // skip next token (already consumed as mode value)
    continue;
  }
  args.push(arg);
}

function resolveGuards() {
  if (mode) {
    if (!modes[mode]) die(`unknown mode "${mode}"`);
    return modes[mode];
  }
  if (!args.length) die('provide guard names or --mode=<name>');
  return args;
}

const guardNames = resolveGuards();
const results = [];

function runGuard(name) {
  const meta = guards[name];
  if (!meta) die(`unknown guard "${name}"`);
  const cmd = meta.command;
  if (!Array.isArray(cmd) || cmd.length === 0) die(`guard "${name}" has no command array`);
  const forwarded = meta.allowExtraArgs ? extraArgs : [];
  return new Promise((resolve, reject) => {
    console.log(`\n[guard-runner] ▶ ${name}`);
    const child = spawn(cmd[0], [...cmd.slice(1), ...forwarded], {
      cwd: repoRoot,
      stdio: 'inherit',
    });
    child.on('exit', code => {
      if (code === 0) {
        console.log(`[guard-runner] ✔ ${name}`);
        resolve();
      } else {
        reject(new Error(`guard "${name}" failed with exit code ${code}`));
      }
    });
    child.on('error', err => reject(err));
  });
}

(async () => {
  for (const name of guardNames) {
    await runGuard(name);
    results.push(name);
  }
  console.log(`\n[guard-runner] Completed ${results.length} guard(s).`);
})().catch(err => {
  console.error(`[guard-runner] ❌ ${err.message || err}`);
  process.exit(1);
});
