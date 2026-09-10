import { useEffect } from 'react';

interface ScaleParams {
    bodyRef: React.RefObject<HTMLDivElement | null>;
    originalWidth: number;
    originalHeight: number;
    content?: string;
    scaleMode?: 'contain' | 'cover' | 'fill';
}

/**
 * Hook for handling coordinate scaling in room views.
 * Standardized version using React Ref.
 * 
 * Scale modes:
 * - 'fill': Independent X/Y scaling to fill 100% (matches background-size: 100% 100%)
 * - 'contain': Uniform scaling to fit entirely (matches background-size: contain)
 * - 'cover': Uniform scaling to fill with clipping (matches background-size: cover)
 */
export const useRoomScaling = ({ bodyRef, originalWidth, originalHeight, content, scaleMode = 'fill' }: ScaleParams) => {
    useEffect(() => {
        const body = bodyRef.current;
        if (!body) return;

        let retryCount = 0;
        const maxRetries = 30; // Increased retries for slower transitions

        const handleScale = () => {
            const rect = body.getBoundingClientRect();

            // If the modal is still opening or hidden, we might get 0 dimensions.
            // We should retry if we expect content but haven't found it yet.
            if (rect.width < 100 || rect.height < 100) {
                if (retryCount < maxRetries) {
                    retryCount++;
                    requestAnimationFrame(handleScale);
                }
                return;
            }

            const containerAspect = rect.width / rect.height;
            const originalAspect = originalWidth / originalHeight;

            let scaleX: number;
            let scaleY: number;
            let offsetX = 0;
            let offsetY = 0;

            if (scaleMode === 'fill') {
                // FILL: Independent X/Y scaling to fill 100% width and 100% height
                // This matches background-size: 100% 100%
                scaleX = rect.width / originalWidth;
                scaleY = rect.height / originalHeight;
            } else if (scaleMode === 'cover') {
                // COVER: Uniform scaling to fill, may clip edges
                const scale = Math.max(rect.width / originalWidth, rect.height / originalHeight);
                scaleX = scale;
                scaleY = scale;
                offsetX = (rect.width - (originalWidth * scale)) / 2;
                offsetY = (rect.height - (originalHeight * scale)) / 2;
            } else {
                // CONTAIN: Uniform scaling to fit entirely
                let scale: number;
                if (containerAspect > originalAspect) {
                    scale = rect.height / originalHeight;
                    offsetX = (rect.width - (originalWidth * scale)) / 2;
                } else {
                    scale = rect.width / originalWidth;
                    offsetY = (rect.height - (originalHeight * scale)) / 2;
                }
                scaleX = scale;
                scaleY = scale;
            }

            const items = body.querySelectorAll('.room-item');

            if (items.length === 0 && content && content.includes('room-item')) {
                if (retryCount < maxRetries) {
                    retryCount++;
                    requestAnimationFrame(handleScale);
                }
                return;
            }

            // Reset retry count once we successfully process items or confirm none are needed
            retryCount = 0;

            items.forEach((item, idx) => {
                const el = item as HTMLElement;

                let oTop, oLeft, oWidth, oHeight;
                if (el.dataset.originalTop) {
                    oTop = parseFloat(el.dataset.originalTop || '0');
                    oLeft = parseFloat(el.dataset.originalLeft || '0');
                    oWidth = parseFloat(el.dataset.originalWidth || '80');
                    oHeight = parseFloat(el.dataset.originalHeight || '80');
                } else {
                    const attrTop = el.getAttribute('data-top');
                    const attrLeft = el.getAttribute('data-left');
                    const attrWidth = el.getAttribute('data-width');
                    const attrHeight = el.getAttribute('data-height');

                    if (attrTop !== null && attrLeft !== null) {
                        oTop = parseFloat(attrTop) || 0;
                        oLeft = parseFloat(attrLeft) || 0;
                        oWidth = parseFloat(attrWidth || '80');
                        oHeight = parseFloat(attrHeight || '80');
                    } else {
                        // Fallback to checking style attributes directly set by PHP or CSS
                        oTop = parseFloat(el.style.top) || 0;
                        oLeft = parseFloat(el.style.left) || 0;
                        oWidth = parseFloat(el.style.width) || 80;
                        oHeight = parseFloat(el.style.height) || 80;
                    }

                    // Store original values if we found valid ones
                    if (oTop !== 0 || oLeft !== 0) {
                        el.dataset.originalTop = String(oTop);
                        el.dataset.originalLeft = String(oLeft);
                        el.dataset.originalWidth = String(oWidth);
                        el.dataset.originalHeight = String(oHeight);
                    }
                }

                const COORDINATE_FACTOR = 1.0;
                const sTop = (oTop * COORDINATE_FACTOR * scaleY) + offsetY;
                const sLeft = (oLeft * COORDINATE_FACTOR * scaleX) + offsetX;
                const sWidth = oWidth * COORDINATE_FACTOR * scaleX;
                const sHeight = oHeight * COORDINATE_FACTOR * scaleY;

                el.style.setProperty('position', 'absolute', 'important');
                el.style.setProperty('top', sTop.toFixed(2) + 'px', 'important');
                el.style.setProperty('left', sLeft.toFixed(2) + 'px', 'important');
                el.style.setProperty('width', sWidth.toFixed(2) + 'px', 'important');
                el.style.setProperty('height', sHeight.toFixed(2) + 'px', 'important');
                el.style.setProperty('display', 'flex', 'important'); // Use flex for centering child img
                el.style.setProperty('visibility', 'visible', 'important');
                el.style.setProperty('opacity', '1', 'important');
                el.style.setProperty('border', 'none', 'important');
                el.style.setProperty('box-shadow', 'none', 'important');
                el.style.setProperty('backdrop-filter', 'none', 'important');
                el.style.setProperty('pointer-events', 'auto', 'important');
                // Nav plaques (area-1 / area-2) must stack above wide index rows so
                // page-turn hits are not stolen by overlapping TOC hitboxes.
                const isNavPlaque = el.classList.contains('area-1') || el.classList.contains('area-2');
                el.style.setProperty('z-index', isNavPlaque ? '1100' : '1000', 'important');

                // Catalog shortcuts use wide/short hitboxes with square (or tall)
                // images. If height is `auto`, width:100% makes the image a huge
                // square that covers Next/Previous and steals clicks.
                if (el.classList.contains('room-item-shortcut')) {
                    el.style.setProperty('overflow', 'hidden', 'important');
                    const img = el.querySelector('img');
                    if (img) {
                        img.style.setProperty('pointer-events', 'none', 'important');
                        img.style.setProperty('width', '100%', 'important');
                        img.style.setProperty('height', '100%', 'important');
                        img.style.setProperty('object-fit', 'contain', 'important');
                        img.style.setProperty('transform', 'none', 'important');
                        img.style.setProperty('max-width', '100%', 'important');
                        img.style.setProperty('max-height', '100%', 'important');
                    }
                }

                el.classList.add('positioned');
            });
        };

        // Use MutationObserver to detect when items are added to the DOM
        const observer = new MutationObserver(() => {
            handleScale();
        });

        observer.observe(body, { childList: true, subtree: true });

        const resizeObserver = new ResizeObserver(() => {
            requestAnimationFrame(handleScale);
        });
        resizeObserver.observe(body);

        window.addEventListener('resize', handleScale);
        handleScale();

        return () => {
            window.removeEventListener('resize', handleScale);
            resizeObserver.disconnect();
            observer.disconnect();
        };
    }, [bodyRef, originalWidth, originalHeight, content]);
};

export default useRoomScaling;
