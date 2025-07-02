/**
 * WhimsicalFrog Room Modal and Popup Management
 * Centralized room functions to eliminate duplication across room files
 * Generated: 2025-07-01 23:22:19
 */

console.log('Loading room-modal-manager.js...');

// Room modal management system
window.RoomModals = window.RoomModals || {};
// updateDetailedModalContent function moved to modal-functions.js for centralization


// Modal close functions (matching the detailed modal component)
function closeDetailedModal() {
    const modal = document.getElementById('detailedItemModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = ''; // Restore scrolling
    }
}


function closeDetailedModalOnOverlay(event) {
    if (event.target === event.currentTarget) {
        closeDetailedModal();
    }
}

console.log('room-modal-manager.js loaded successfully');
