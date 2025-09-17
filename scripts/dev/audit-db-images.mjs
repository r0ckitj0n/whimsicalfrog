#!/usr/bin/env node
/**
 * Audit DB-backed item images via existing APIs (read-only)
 *
 * - Fetches all items from /api/get_items.php
 * - For each item.sku, fetches /api/get_item_images.php?sku=...
 * - Verifies that reported image paths exist on disk
 * - Optionally HEAD-checks via BASE_URL if provided
 *
 * This script is intended for local use (not CI), since it depends on the PHP dev server.
 */

import fs from 'fs/promises';
import path from 'path';
import http from 'http';
import https from 'https';

const ROOT = process.cwd();
const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';

function toLocalPath(imagePath) {
  const rel = imagePath.replace(/^\/*/, '');
  return path.join(ROOT, rel);
}

async function fileExists(p) {
  try { await fs.access(p); return true; } catch { return false; }
}

async function head(url) {
  return new Promise((resolve) => {
    const client = url.startsWith('https') ? https : http;
    const req = client.request(url, { method: 'HEAD' }, res => {
      resolve(res.statusCode || 0);
    });
    req.on('error', () => resolve(0));
    req.end();
  });
}

async function fetchJson(url) {
  const res = await fetch(url, { method: 'GET' });
  if (!res.ok) throw new Error(`Fetch failed: ${url} -> ${res.status} ${res.statusText}`);
  return res.json();
}

(async () => {
  const issues = [];

  // 1) Fetch all items
  let items = [];
  try {
    items = await fetchJson(`${BASE_URL}/api/get_items.php`);
  } catch (e) {
    console.error('Failed to fetch items from API. Ensure the PHP dev server is running (BASE_URL).');
    console.error(String(e));
    process.exit(2);
  }

  // Normalize unique SKUs
  const skus = Array.from(new Set(items.map(i => i.sku).filter(Boolean)));

  for (const sku of skus) {
    let data;
    try {
      data = await fetchJson(`${BASE_URL}/api/get_item_images.php?sku=${encodeURIComponent(sku)}`);
    } catch (e) {
      issues.push(`[api] get_item_images failed for ${sku}: ${String(e)}`);
      continue;
    }

    if (!data || !data.success || !Array.isArray(data.images)) {
      issues.push(`[api] get_item_images bad response for ${sku}`);
      continue;
    }

    for (const img of data.images) {
      const imgPath = (img.image_path || '').replace(/^\/*/, '');
      if (!imgPath) {
        issues.push(`[data] ${sku} has image with empty image_path`);
        continue;
      }
      const local = toLocalPath(imgPath);
      const exists = await fileExists(local);
      if (!exists) {
        issues.push(`[missing-file] ${sku} -> ${imgPath}`);
      }
      // Optional HTTP check
      const url = `${BASE_URL}/${imgPath}`;
      const code = await head(url);
      if (code !== 200) {
        issues.push(`[http-${code || 'ERR'}] ${sku} -> ${url}`);
      }
    }
  }

  if (issues.length > 0) {
    console.error('Image DB audit found issues:');
    for (const i of issues) console.error('- ' + i);
    process.exit(1);
  }
  console.log(`Image DB audit passed. Checked ${skus.length} SKUs.`);
})();
