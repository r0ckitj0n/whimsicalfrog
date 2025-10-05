/**
 * Landing Page Positioning Logic
 * This script handles the dynamic positioning of clickable areas on the landing page background.
 */

function initializeLandingPage() {
    const MAX_ATTEMPTS = 60; // ~3s at 50ms
    const RETRY_MS = 50;

    const startWithEl = (landingPage) => {
        // Ensure fullscreen styling applies
        document.body.classList.add('mode-fullscreen');
        console.log('🎯 Landing page positioning script loaded');

        if (window.innerWidth < 1000) {
            console.log('🎯 Mobile layout detected, skipping JS positioning');
            const enterLink = document.querySelector('.area-1');
            if (enterLink) enterLink.setAttribute('href', '/shop');
            return;
        }

        // Original image dimensions
        const originalImageWidth = 1280;
        const originalImageHeight = 896;

        // Load server-side coordinates from data attribute
        let rawAreaCoords = [];
        try {
            let coordsAttr = landingPage.dataset.coords || '[]';
            // Decode common HTML entities to get valid JSON
            coordsAttr = coordsAttr
                .replace(/&quot;/g, '"')
                .replace(/&#34;/g, '"')
                .replace(/&apos;/g, "'")
                .replace(/&#39;/g, "'")
                .replace(/&lt;/g, '<')
                .replace(/&gt;/g, '>')
                .replace(/&amp;/g, '&');
            rawAreaCoords = JSON.parse(coordsAttr);
        } catch (e) {
            console.warn('Landing: failed to parse data-coords; will use fallback if needed. Error:', e);
            rawAreaCoords = [];
        }

        if (!Array.isArray(rawAreaCoords)) {
            console.warn('Landing coords not an array; received:', rawAreaCoords);
            rawAreaCoords = [];
        }

        // Fallback: if DB has no coordinates, seed with Area 1 pixels so the welcome sign positions correctly
        if (rawAreaCoords.length === 0) {
            console.warn('No landing coordinates found in DB. Using fallback Area 1 pixel coordinates.');
            rawAreaCoords = [{ selector: '.area-1', top: 411, left: 601, width: 125, height: 77 }];
        }

        const areaCoordinates = rawAreaCoords.map(area => {
            const selector = area.selector.startsWith('.') ? area.selector : '.' + area.selector;
            return { selector, top: area.top, left: area.left, width: area.width, height: area.height };
        });

        // Runtime-injected CSS classes for positions
        const runtime = { styleEl: null, rules: new Set() };
        function ensureStyleEl() {
            if (!runtime.styleEl) {
                runtime.styleEl = document.createElement('style');
                runtime.styleEl.id = 'landing-page-runtime-styles';
                document.head.appendChild(runtime.styleEl);
                // Ensure landing container fills viewport and is a positioned context (mirror room_main fullscreen container)
                runtime.styleEl.appendChild(document.createTextNode(`#landingPage{position:fixed;inset:0;overflow:hidden;}`));
            }
            return runtime.styleEl;
        }
        function roundRectVals(top, left, width, height) {
            return { t: Math.round(top), l: Math.round(left), w: Math.round(width), h: Math.round(height) };
        }
        function buildClassName({ t, l, w, h }) {
            return `lp-pos-t${t}-l${l}-w${w}-h${h}`;
        }
        function ensureRuleFor(className, rect) {
            if (runtime.rules.has(className)) return;
            const css = `#landingPage .${className} { position:absolute; top:${rect.t}px; left:${rect.l}px; width:${rect.w}px; height:${rect.h}px; }`;
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
                // Pillarbox: fit height
                scale = viewportHeight / originalImageHeight;
                offsetX = (viewportWidth - (originalImageWidth * scale)) / 2;
                offsetY = 0;
            } else {
                // Letterbox: fit width
                scale = viewportWidth / originalImageWidth;
                offsetY = (viewportHeight - (originalImageHeight * scale)) / 2;
                offsetX = 0;
            }

            areaCoordinates.forEach(area => {
                let element = landingPage.querySelector(area.selector);
                if (!element) {
                    // Fallback: try clickable-area prefix
                    const fallbackSel = `.clickable-area${area.selector}`;
                    element = landingPage.querySelector(fallbackSel);
                    if (!element) {
                        console.warn('Landing: element not found for selector(s):', area.selector, 'fallback:', fallbackSel);
                        return;
                    }
                }
                const rect = roundRectVals((area.top * scale) + offsetY, (area.left * scale) + offsetX, area.width * scale, area.height * scale);
                const className = buildClassName(rect);
                ensureRuleFor(className, rect);

                const prev = element.dataset.lpPosClass;
                if (prev && prev !== className) element.classList.remove(prev);
                if (!element.classList.contains(className)) element.classList.add(className);
                element.dataset.lpPosClass = className;
                if (area.selector === '.area-1') {
                    console.debug('Landing area-1 rect applied:', rect, 'scale:', { s: (window.innerWidth / 1280), vw: window.innerWidth, vh: window.innerHeight });
                }
            });
        }

        positionAreas();
        window.addEventListener('resize', positionAreas);
    };

    const tryFind = (attempt = 0) => {
        const el = document.getElementById('landingPage');
        if (el) return startWithEl(el);
        if (attempt >= MAX_ATTEMPTS) {
            console.warn('Landing: #landingPage not found after waiting. Aborting positioning.');
            return;
        }
        setTimeout(() => tryFind(attempt + 1), RETRY_MS);
    };

    tryFind(0);
}

// Ensure we run even if module loads after DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeLandingPage, { once: true });
} else {
    initializeLandingPage();
}
