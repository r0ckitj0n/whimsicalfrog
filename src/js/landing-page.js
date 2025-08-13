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

    // Runtime-injected CSS classes for positions
    const runtime = { styleEl: null, rules: new Set() };
    function ensureStyleEl() {
        if (!runtime.styleEl) {
            runtime.styleEl = document.createElement('style');
            runtime.styleEl.id = 'landing-page-runtime-styles';
            document.head.appendChild(runtime.styleEl);
        }
        return runtime.styleEl;
    }
    function roundRectVals(top, left, width, height) {
        return {
            t: Math.round(top),
            l: Math.round(left),
            w: Math.round(width),
            h: Math.round(height)
        };
    }
    function buildClassName({ t, l, w, h }) {
        return `lp-pos-t${t}-l${l}-w${w}-h${h}`;
    }
    function ensureRuleFor(className, rect) {
        if (runtime.rules.has(className)) return;
        const css = `#landingPage .${className} { top:${rect.t}px; left:${rect.l}px; width:${rect.w}px; height:${rect.h}px; }`;
        ensureStyleEl().appendChild(document.createTextNode(css));
        runtime.rules.add(className);
    }

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
            if (!element) return;
            const rect = roundRectVals((area.top * scale) + offsetY, (area.left * scale) + offsetX, area.width * scale, area.height * scale);
            const className = buildClassName(rect);
            ensureRuleFor(className, rect);

            const prev = element.dataset.lpPosClass;
            if (prev && prev !== className) {
                element.classList.remove(prev);
            }
            if (!element.classList.contains(className)) {
                element.classList.add(className);
            }
            element.dataset.lpPosClass = className;
        });
    }

    positionAreas();
    window.addEventListener('resize', positionAreas);
});
