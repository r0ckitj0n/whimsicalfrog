/**
 * Landing Page Positioning Logic
 * This script handles the dynamic positioning of clickable areas on the landing page background.
 */

document.addEventListener('DOMContentLoaded', function() {
    const landingPage = document.getElementById('landingPage');
    if (!landingPage) {
        return; // Exit if the landing page element is not found
    }

    // Ensure fullscreen styling applies
    document.body.classList.add('mode-fullscreen');
    console.log('🎯 Landing page positioning script loaded');

    if (window.innerWidth < 1000) {
        console.log('🎯 Mobile layout detected, skipping JS positioning');
        const enterLink = document.querySelector('.area-1');
        if (enterLink) {
            enterLink.setAttribute('href', '/shop');
        }
        return;
    }

    // Original image dimensions
    const originalImageWidth = 1280;
    const originalImageHeight = 896;

    // Load server-side coordinates from data attribute
    let rawAreaCoords = [];
    try {
        rawAreaCoords = JSON.parse(landingPage.dataset.coords || '[]');
    } catch (e) {
        console.error('Error parsing landing page coordinates:', e);
        return;
    }

    const areaCoordinates = rawAreaCoords.map(area => {
        const selector = area.selector.startsWith('.') ? area.selector : '.' + area.selector;
        return {
            selector,
            top: area.top,
            left: area.left,
            width: area.width,
            height: area.height
        };
    });

    function positionAreas() {
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        const viewportRatio = viewportWidth / viewportHeight;
        const imageRatio = originalImageWidth / originalImageHeight;

        let scale, offsetX, offsetY;

        if (viewportRatio > imageRatio) {
            scale = viewportHeight / originalImageHeight;
            offsetX = (viewportWidth - (originalImageWidth * scale)) / 2;
            offsetY = 0;
        } else {
            scale = viewportWidth / originalImageWidth;
            offsetY = (viewportHeight - (originalImageHeight * scale)) / 2;
            offsetX = 0;
        }

        areaCoordinates.forEach(area => {
            const element = document.querySelector(area.selector);
            if (element) {
                element.style.top = `${(area.top * scale) + offsetY}px`;
                element.style.left = `${(area.left * scale) + offsetX}px`;
                element.style.width = `${area.width * scale}px`;
                element.style.height = `${area.height * scale}px`;
            }
        });
    }

    positionAreas();
    window.addEventListener('resize', positionAreas);
});
