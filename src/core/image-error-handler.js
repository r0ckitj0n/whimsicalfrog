/**
 * Image Error Handler (ES module)
 * Migrated from legacy modules/image-error-handler.js
 */

export function handleImageError(img, sku = null) {
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

export function handleImageErrorSimple(img) {
  if (img.dataset.errorHandled) return;
  setPlaceholder(img);
}

export function setupImageErrorHandling(img, sku = null) {
  img.onerror = () => {
    sku ? handleImageError(img, sku) : handleImageErrorSimple(img);
  };
}

function setPlaceholder(img) {
  img.src = 'images/items/placeholder.webp';
  img.dataset.errorHandled = 'final';
  img.onerror = null;
}

console.log('[ImageErrorHandler] ES module loaded');
