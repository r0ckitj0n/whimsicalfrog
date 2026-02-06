/**
 * Image Error Handler
 * Handles fallback to PNG if WebP fails, and eventually to a placeholder.
 */

export function handleImageError(img: HTMLImageElement, sku: string | null = null): void {
    const currentState = img.dataset.errorHandled || 'none';
    const currentSrc = img.src;
    if (currentState === 'final') return;

    if (sku) {
        if (currentSrc.includes(`${sku}A.webp`)) {
            img.src = `images/items/${sku}A.png`;
            img.dataset.errorHandled = 'png-tried';
            return;
        }
        if (currentSrc.includes(`${sku}A.png`)) {
            setPlaceholder(img);
            return;
        }
    }
    setPlaceholder(img);
}

export function handleImageErrorSimple(img: HTMLImageElement): void {
    if (img.dataset.errorHandled) return;
    setPlaceholder(img);
}

export function setupImageErrorHandling(img: HTMLImageElement, sku: string | null = null): void {
    img.onerror = () => {
        sku ? handleImageError(img, sku) : handleImageErrorSimple(img);
    };
}

function setPlaceholder(img: HTMLImageElement): void {
    img.src = 'images/items/placeholder.webp';
    img.dataset.errorHandled = 'final';
    img.onerror = null;
}
