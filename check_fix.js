// Simple script to check if the viewport width fix is working
const puppeteer = require('puppeteer');

(async () => {
  try {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();
    await page.setViewport({ width: 1600, height: 900 });
    
    await page.goto('http://localhost:8000/?page=shop');
    await page.waitForSelector('#productsGrid', { timeout: 5000 });
    
    const result = await page.evaluate(() => {
      const viewport = window.innerWidth;
      const shopPage = document.getElementById('shopPage');
      const grid = document.getElementById('productsGrid');
      
      if (!shopPage || !grid) {
        return { error: 'Elements not found' };
      }
      
      const shopWidth = shopPage.offsetWidth;
      const gridWidth = grid.offsetWidth;
      const gridStyles = getComputedStyle(grid);
      
      return {
        viewportWidth: viewport,
        shopPageWidth: shopWidth,
        gridWidth: gridWidth,
        gridMaxWidth: gridStyles.maxWidth,
        shopPageUtilization: Math.round((shopWidth / viewport) * 100),
        gridUtilization: Math.round((gridWidth / viewport) * 100),
        isFixWorking: gridWidth >= viewport * 0.95 && gridStyles.maxWidth === 'none'
      };
    });
    
    console.log('=== VIEWPORT WIDTH FIX VERIFICATION ===');
    console.log(`Viewport Width: ${result.viewportWidth}px`);
    console.log(`Shop Page Width: ${result.shopPageWidth}px (${result.shopPageUtilization}%)`);
    console.log(`Grid Width: ${result.gridWidth}px (${result.gridUtilization}%)`);
    console.log(`Grid Max-Width: ${result.gridMaxWidth}`);
    console.log(`Fix Status: ${result.isFixWorking ? '✅ SUCCESS' : '❌ FAILED'}`);
    
    await browser.close();
  } catch (error) {
    console.error('Error:', error.message);
  }
})();
