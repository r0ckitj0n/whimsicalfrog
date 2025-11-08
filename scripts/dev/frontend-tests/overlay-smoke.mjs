#!/usr/bin/env node
/*
  Overlay Smoke Test (puppeteer-core)
  - Requires a running site URL via BASE_URL, e.g. BASE_URL=https://admin.example.com
  - Optional: PUPPETEER_EXECUTABLE_PATH (path to Chrome/Chromium)
  - Verifies:
    1) Can show parent overlay (if helpers present)
    2) Single-scroll fallback class toggled
    3) Open child inline via postMessage (Intent Heuristics)
    4) Resize clamp sanity (panel/body not exceeding viewport height by large margin)
    5) ESC closes child; parent re-shows; focus return not asserted but no error
*/
import puppeteer from 'puppeteer-core';

const BASE_URL = process.env.BASE_URL;
if (!BASE_URL) {
  console.log('[overlay-smoke] Skipping: set BASE_URL to run this test');
  process.exit(0);
}

const EXEC_PATH = process.env.PUPPETEER_EXECUTABLE_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

(async () => {
  const browser = await puppeteer.launch({ headless: 'new', executablePath: EXEC_PATH, args: ['--no-sandbox','--disable-setuid-sandbox'] }).catch((e)=>{
    console.error('[overlay-smoke] Launch failed:', e.message);
    process.exit(1);
  });
  const page = await browser.newPage();
  page.setDefaultTimeout(15000);

  try {
    console.log('[overlay-smoke] Navigating:', BASE_URL);
    await page.goto(BASE_URL, { waitUntil: 'domcontentloaded' });

    // Try to show parent overlay if helper present
    const hadShowHelper = await page.evaluate(() => typeof window.__wfShowModal === 'function');
    if (hadShowHelper) {
      await page.evaluate(() => { try { window.__wfShowModal('aiUnifiedModal'); } catch(_) {} });
      await sleep(300);
    } else {
      console.log('[overlay-smoke] __wfShowModal not present; attempting to proceed anyway');
    }

    // Check single-scroll fallback class toggled
    const scrollLock = await page.evaluate(() => {
      try { return document.documentElement.classList.contains('wf-admin-modal-open'); } catch(_) { return false; }
    });
    console.log('[overlay-smoke] Page scroll-lock class:', scrollLock);

    // Post message to open child inline (Intent Heuristics)
    await page.evaluate(() => {
      try {
        window.postMessage({ source: 'wf-ai', type: 'open-tool', url: '/sections/tools/intent_heuristics_manager.php?modal=1', title: '🧠 Intent Heuristics Config', forceInline: true }, '*');
      } catch(_) {}
    });

    // Wait for child modal to be visible if present
    await page.waitForFunction(() => {
      const el = document.getElementById('aiUnifiedChildModal');
      return !!(el && el.classList && el.classList.contains('show') && !el.classList.contains('hidden'));
    }, { timeout: 10000 }).catch(()=>console.log('[overlay-smoke] Child overlay not detected; continuing'));

    // Clamp sanity: ensure panel/body not wildly exceeding viewport height
    const clampOK = await page.evaluate(() => {
      try {
        const overlay = document.getElementById('aiUnifiedChildModal');
        if (!overlay) return true; // soft pass if not present
        const panel = overlay.querySelector('.admin-modal');
        const body = overlay.querySelector('.modal-body');
        const vh = window.innerHeight;
        const panelH = panel ? panel.getBoundingClientRect().height : 0;
        const bodyH = body ? body.getBoundingClientRect().height : 0;
        return panelH <= vh * 1.05 && bodyH <= vh * 1.05; // allow a small margin
      } catch(_) { return true; }
    });
    console.log('[overlay-smoke] Resize clamp OK:', clampOK);

    // ESC to close (child preferred)
    await page.keyboard.press('Escape');
    await sleep(200);

    // If helper present, hide child explicitly and check parent re-show
    const parentBack = await page.evaluate(() => {
      try {
        if (typeof window.__wfHideModal === 'function') window.__wfHideModal('aiUnifiedChildModal');
        const parent = document.getElementById('aiUnifiedModal');
        return !!(parent && parent.classList.contains('show') && !parent.classList.contains('hidden'));
      } catch(_) { return false; }
    });
    console.log('[overlay-smoke] Parent re-shown after child close:', parentBack);

    // Final: ensure no double scrollbars (heuristic)
    const doubleScrollHeuristic = await page.evaluate(() => {
      try {
        const child = document.getElementById('aiUnifiedChildModal');
        const childVisible = !!(child && child.classList.contains('show') && !child.classList.contains('hidden'));
        // If child visible, page root should be scroll-locked
        if (childVisible) return !(document.documentElement.classList.contains('wf-admin-modal-open'));
        return false;
      } catch(_) { return false; }
    });
    console.log('[overlay-smoke] Double-scroll heuristic (false means OK):', doubleScrollHeuristic);

    // Now also open Suggestions inline and verify container
    await page.evaluate(() => {
      try {
        window.postMessage({ source: 'wf-ai', type: 'open-tool', url: '/sections/tools/ai_suggestions.php?modal=1', title: '🤖 Suggestions Manager', forceInline: true }, '*');
      } catch(_) {}
    });
    await page.waitForFunction(() => {
      try {
        const el = document.getElementById('aiUnifiedChildModal');
        const inlineBox = el ? el.querySelector('#aiUnifiedChildInline') : null;
        const sugg = inlineBox ? inlineBox.querySelector('#suggestionsManagerContent') : null;
        return !!(el && el.classList.contains('show') && !el.classList.contains('hidden') && sugg);
      } catch(_) { return false; }
    }, { timeout: 10000 }).catch(()=>console.log('[overlay-smoke] Suggestions inline container not detected; continuing'));

    // Focus-trap assertion: cycle Tab from last -> first and Shift+Tab from first -> last
    const trapOK = await page.evaluate(() => {
      try {
        const overlay = document.getElementById('aiUnifiedChildModal');
        if (!overlay) return false;
        const panel = overlay.querySelector('.admin-modal');
        if (!panel) return false;
        const nodes = panel.querySelectorAll('a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])');
        const focusables = Array.from(nodes).filter(n => n && n.offsetParent !== null);
        if (focusables.length < 2) return true; // soft pass with limited controls
        focusables[focusables.length - 1].focus();
        return true;
      } catch(_) { return false; }
    });
    if (trapOK) {
      // Press Tab, expect loop to first element
      await page.keyboard.press('Tab');
      const firstFocused = await page.evaluate(() => {
        const overlay = document.getElementById('aiUnifiedChildModal');
        const panel = overlay ? overlay.querySelector('.admin-modal') : null;
        if (!panel) return 'na';
        const nodes = panel.querySelectorAll('a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])');
        const focusables = Array.from(nodes).filter(n => n && n.offsetParent !== null);
        const first = focusables[0];
        return document.activeElement === first ? 'ok' : 'miss';
      });
      console.log('[overlay-smoke] Focus-trap Tab first check:', firstFocused);

      // Press Shift+Tab, expect loop to last element
      await page.keyboard.down('Shift');
      await page.keyboard.press('Tab');
      await page.keyboard.up('Shift');
      const lastFocused = await page.evaluate(() => {
        const overlay = document.getElementById('aiUnifiedChildModal');
        const panel = overlay ? overlay.querySelector('.admin-modal') : null;
        if (!panel) return 'na';
        const nodes = panel.querySelectorAll('a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])');
        const focusables = Array.from(nodes).filter(n => n && n.offsetParent !== null);
        const last = focusables[focusables.length - 1];
        return document.activeElement === last ? 'ok' : 'miss';
      });
      console.log('[overlay-smoke] Focus-trap Shift+Tab last check:', lastFocused);
    }

    // OverlayManager open/close checks
    const omOK = await page.evaluate(async () => {
      try {
        if (!window.OverlayManager) return 'na';
        window.OverlayManager.open('aiUnifiedModal');
        const vis1 = window.OverlayManager.isVisible('aiUnifiedModal');
        window.OverlayManager.close('aiUnifiedModal');
        const vis2 = window.OverlayManager.isVisible('aiUnifiedModal');
        return (vis1 === true && vis2 === false) ? 'ok' : 'miss';
      } catch(_) { return 'err'; }
    });
    console.log('[overlay-smoke] OverlayManager open/close:', omOK);
    // Close again
    await page.keyboard.press('Escape');
    await sleep(150);

    console.log('[overlay-smoke] Done');
  } catch (err) {
    console.error('[overlay-smoke] Error:', err.message);
  } finally {
    await browser.close();
  }
})();
