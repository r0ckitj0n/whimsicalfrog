#!/usr/bin/env node
import { promises as fs } from 'node:fs';
import { globby } from 'globby';

/*
  Fails on legacy CSS patterns in PHP templates:
  - <link rel="stylesheet">
  - href="*.css"
  - inline style="..."
*/

const files = await globby(['**/*.php', '!vendor/**', '!dist/**']);
const offenders = [];
for (const file of files) {
  const txt = await fs.readFile(file, 'utf8');
  if (/(<link\s+[^>]*rel=["']stylesheet["'])/i.test(txt)) offenders.push([file, 'link rel="stylesheet"']);
  if (/(href=["'][^"']+\.css["'])/i.test(txt)) offenders.push([file, 'href="*.css"']);
  if (/(\sstyle=\"[^\"]+\"|\sstyle='[^']+')/i.test(txt)) offenders.push([file, 'inline style attribute']);
}

if (offenders.length) {
  console.error('Template CSS guard failed. Offending patterns found:');
  for (const [file, why] of offenders) {
    console.error(` - ${file}: ${why}`);
  }
  process.exit(1);
} else {
  console.log('Template CSS guard passed.');
}
