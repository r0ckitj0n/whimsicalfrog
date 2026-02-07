#!/usr/bin/env node
import { execFileSync } from 'node:child_process';

function runGit(args, options = {}) {
  return execFileSync('git', args, {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
    ...options
  });
}

function formatCheckpointTimestamp(date) {
  const pad = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

const args = process.argv.slice(2);

if (args.includes('--help') || args.includes('-h')) {
  console.log('Usage: npm run checkpoint -- "your commit message"');
  console.log('If no message is provided, a timestamped checkpoint message is used.');
  process.exit(0);
}

try {
  runGit(['rev-parse', '--is-inside-work-tree']);
} catch (error) {
  console.error('[checkpoint] Not inside a git repository.');
  process.exit(1);
}

const statusBefore = runGit(['status', '--porcelain']).trim();
if (!statusBefore) {
  console.log('[checkpoint] No changes to commit.');
  process.exit(0);
}

const messageFromArgs = args.join(' ').trim();
const commitMessage = messageFromArgs || `checkpoint: ${formatCheckpointTimestamp(new Date())}`;

try {
  runGit(['add', '-A']);
} catch (error) {
  console.error('[checkpoint] Failed to stage changes.');
  process.exit(1);
}

try {
  runGit(['diff', '--cached', '--quiet']);
  console.log('[checkpoint] No staged changes to commit.');
  process.exit(0);
} catch (error) {
  // Non-zero exit here means staged changes exist, which is expected.
}

try {
  execFileSync('git', ['commit', '-m', commitMessage], { stdio: 'inherit' });
  console.log(`[checkpoint] Commit created: "${commitMessage}"`);
} catch (error) {
  console.error('[checkpoint] Commit failed. Resolve issues and run again.');
  process.exit(1);
}
