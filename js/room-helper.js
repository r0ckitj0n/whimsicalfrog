(function() {
  const script = document.currentScript;
  window.roomItems = script.dataset.roomItems ? JSON.parse(script.dataset.roomItems) : [];
  window.roomNumber = script.dataset.roomNumber || '';
  window.roomType = script.dataset.roomType || '';
  window.ROOM_TYPE = window.roomType;
  window.originalImageWidth = 1280;
  window.originalImageHeight = 896;
  window.baseAreas = script.dataset.baseAreas ? JSON.parse(script.dataset.baseAreas) : [];
  window.roomOverlayWrapper = null;

  function updateItemPositions() {
    if (!window.roomOverlayWrapper || !window.baseAreas) return;
    const wrapperWidth = window.roomOverlayWrapper.offsetWidth;
    const wrapperHeight = window.roomOverlayWrapper.offsetHeight;
    const imageAspectRatio = window.originalImageWidth / window.originalImageHeight;
    let renderedImageWidth, renderedImageHeight, offsetX = 0, offsetY = 0;
    const wrapperAspectRatio = wrapperWidth / wrapperHeight;
    if (wrapperAspectRatio > imageAspectRatio) {
      renderedImageHeight = wrapperHeight;
      renderedImageWidth = renderedImageHeight * imageAspectRatio;
      offsetX = (wrapperWidth - renderedImageWidth) / 2;
    } else {
      renderedImageWidth = wrapperWidth;
      renderedImageHeight = renderedImageWidth / imageAspectRatio;
      offsetY = (wrapperHeight - renderedImageHeight) / 2;
    }
    const scaleX = renderedImageWidth / window.originalImageWidth;
    const scaleY = renderedImageHeight / window.originalImageHeight;
    window.roomItems.forEach((_, index) => {
      const itemElement = document.getElementById('item-icon-' + index);
      const areaData = window.baseAreas[index];
      if (itemElement && areaData) {
        itemElement.style.top = (areaData.top * scaleY + offsetY) + 'px';
        itemElement.style.left = (areaData.left * scaleX + offsetX) + 'px';
        itemElement.style.width = (areaData.width * scaleX) + 'px';
        itemElement.style.height = (areaData.height * scaleY) + 'px';
      }
    });
  }

  function adjustTitleBoxSize() {
    const titleOverlay = document.querySelector('.room-title-overlay');
    if (!titleOverlay) return;
    const title = titleOverlay.querySelector('.room-title');
    const description = titleOverlay.querySelector('.room-description');
    if (!title) return;
    const titleLength = title.textContent.length;
    const descriptionLength = description ? description.textContent.length : 0;
    const totalLength = titleLength + descriptionLength;
    const screenWidth = window.innerWidth;
    const isMobile = screenWidth <= 480;
    const isTablet = screenWidth <= 768;
    let dynamicWidth, dynamicPadding;
    if (isMobile) {
      dynamicWidth = totalLength <= 25 ? '140px' : totalLength <= 40 ? '180px' : totalLength <= 60 ? '220px' : '240px';
      dynamicPadding = totalLength <= 30 ? '6px 10px' : '8px 12px';
    } else if (isTablet) {
      dynamicWidth = totalLength <= 30 ? '160px' : totalLength <= 50 ? '210px' : totalLength <= 70 ? '250px' : '280px';
      dynamicPadding = totalLength <= 30 ? '8px 12px' : '10px 14px';
    } else {
      dynamicWidth = totalLength <= 30 ? '200px' : totalLength <= 50 ? '250px' : totalLength <= 80 ? '300px' : '400px';
      dynamicPadding = totalLength <= 30 ? '10px 14px' : totalLength <= 50 ? '12px 16px' : '14px 18px';
    }
    titleOverlay.style.width = dynamicWidth;
    titleOverlay.style.padding = dynamicPadding;
    let titleFontSize, descriptionFontSize;
    if (isMobile) {
      titleFontSize = titleLength <= 15 ? '1.6rem' : titleLength <= 25 ? '1.3rem' : titleLength <= 35 ? '1.1rem' : '1rem';
      descriptionFontSize = descriptionLength <= 30 ? '0.9rem' : descriptionLength <= 50 ? '0.8rem' : '0.7rem';
    } else if (isTablet) {
      titleFontSize = titleLength <= 15 ? '2rem' : titleLength <= 25 ? '1.7rem' : titleLength <= 35 ? '1.4rem' : '1.2rem';
      descriptionFontSize = descriptionLength <= 30 ? '1.1rem' : descriptionLength <= 50 ? '1rem' : '0.9rem';
    } else {
      titleFontSize = titleLength <= 15 ? '2.5rem' : titleLength <= 25 ? '2.2rem' : titleLength <= 35 ? '1.9rem' : titleLength <= 45 ? '1.6rem' : '1.4rem';
      descriptionFontSize = descriptionLength <= 30 ? '1.3rem' : descriptionLength <= 50 ? '1.2rem' : descriptionLength <= 70 ? '1.1rem' : '1rem';
    }
    title.style.fontSize = titleFontSize;
    title.style.whiteSpace = '';
    title.style.overflow = '';
    title.style.textOverflow = '';
    if (description) {
      description.style.fontSize = descriptionFontSize;
      description.style.whiteSpace = '';
      description.style.overflow = '';
      description.style.textOverflow = '';
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    window.roomOverlayWrapper = document.querySelector('.room-overlay-wrapper');
    if (window.roomOverlayWrapper && window.baseAreas && window.baseAreas.length > 0) {
      updateItemPositions();
      let resizeTimeout;
      window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
          updateItemPositions();
          adjustTitleBoxSize();
        }, 100);
      });
    }
    adjustTitleBoxSize();
  });

  document.addEventListener('whimsicalfrog:ready', function() {
    if (typeof initializeRoomCoordinates === 'function') {
      initializeRoomCoordinates();
    }
  });
})();

// Ensure clicking main modal image opens image viewer
// FIRST_EDIT: Comment out old custom click listener for detailed modal
/*
document.body.addEventListener('click', function(e) {
  if (e.target && e.target.id === 'detailedMainImage') {
    openImageViewer(e.target.src, e.target.alt);
  }
  // Close detailed item modal when clicking on overlay background
  if (e.target && e.target.id === 'detailedItemModal') {
    // Allow room-modal-manager or central handler
    if (typeof closeDetailedModalOnOverlay === 'function') {
      closeDetailedModalOnOverlay(e);
    }
  }
});
*/
// SECOND_EDIT: Add delegated click handler for detailed modal interactions
document.body.addEventListener('click', function(e) {
  const actionEl = e.target.closest('[data-action="openImageViewer"], [data-action="closeDetailedModalOnOverlay"]');
  if (!actionEl) return;
  const action = actionEl.dataset.action;
  const params = actionEl.dataset.params ? JSON.parse(actionEl.dataset.params) : {};
  if (action === 'openImageViewer' && typeof openImageViewer === 'function') {
    openImageViewer(params.src, params.name);
  }
  if (action === 'closeDetailedModalOnOverlay' && typeof closeDetailedModalOnOverlay === 'function') {
    closeDetailedModalOnOverlay(e);
  }
}); 