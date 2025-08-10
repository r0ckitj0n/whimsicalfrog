import '../css/room.css';

const RoomTemplateModule = {
    init() {
        // Only run this module on room pages by checking for a specific element.
        const roomPage = document.getElementById('universalRoomPage');
        if (!roomPage) {
            return;
        }

        this.bindItemEvents();
        this.bindModalEvents();
    },

    /**
     * Binds events to each product item icon on the page.
     */
    bindItemEvents() {
        document.querySelectorAll('.item-icon').forEach(icon => {
            // Mouseenter to show the informational popup.
            icon.addEventListener('mouseenter', (e) => {
                const itemDataJson = e.currentTarget.dataset.itemJson;
                if (itemDataJson && typeof window.showGlobalPopup === 'function') {
                    try {
                        const itemData = JSON.parse(itemDataJson);
                        window.showGlobalPopup(e.currentTarget, itemData);
                    } catch (err) {
                        console.error('Failed to parse item JSON data:', err);
                    }
                }
            });

            // Mouseleave to hide the popup.
            icon.addEventListener('mouseleave', () => {
                if (typeof window.hideGlobalPopup === 'function') {
                    window.hideGlobalPopup();
                }
            });

            // Click to show the detailed item modal.
            icon.addEventListener('click', (e) => {
                const productId = e.currentTarget.dataset.productId;
                if (productId && typeof window.showItemDetailsModal === 'function') {
                    window.showItemDetailsModal(productId);
                }
            });
        });
    },

    /**
     * Binds events for modals, using event delegation for elements that may not exist on page load.
     */
    bindModalEvents() {
        document.body.addEventListener('click', (e) => {
            // Handles closing the quantity modal from multiple buttons.
            if (e.target.matches('#closeQuantityModal, #cancelQuantityModal')) {
                const quantityModal = document.getElementById('quantityModal');
                if (quantityModal) {
                    quantityModal.classList.add('hidden');
                }
            }

            // Handles closing the detailed item modal.
            if (e.target.closest('.detailed-modal-close-btn')) {
                const detailedModal = document.getElementById('detailedItemModal');
                if (detailedModal) {
                    detailedModal.remove(); // Remove modal from DOM to ensure it's fresh next time.
                }
            }
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    RoomTemplateModule.init();
});
