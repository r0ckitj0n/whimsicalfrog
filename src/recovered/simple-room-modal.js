/**
 * Simple Room Modal System - Direct Content Loading
 * No iframe complexity, direct positioning
 */

console.log('Loading simple-room-modal.js...');

class SimpleRoomModal {
    constructor() {
        this.overlay = null;
        this.isOpen = false;
        this.currentRoom = null;
        this.init();
    }

    init() {
        this.createModalStructure();
        this.setupEventListeners();
        window.roomModal = this; // Global access
    }

    createModalStructure() {
        // Create modal overlay
        this.overlay = document.createElement('div');
        this.overlay.id = 'roomModalOverlay';
        this.overlay.className = 'room-modal-overlay';
        

        // Create modal content container
        const content = document.createElement('div');
        content.className = 'room-modal-content';
        

        // Create close button
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '×';
        closeBtn.className = 'room-modal-close';
        
        closeBtn.onclick = () => this.close();

        // Create room content area
        const roomContent = document.createElement('div');
        roomContent.id = 'roomContent';
        roomContent.className = 'room-content-area';
        

        content.appendChild(closeBtn);
        content.appendChild(roomContent);
        this.overlay.appendChild(content);
        document.body.appendChild(this.overlay);
    }

    setupEventListeners() {
        // Listen for room door clicks
        document.addEventListener('click', (e) => {
            const roomDoor = e.target.closest('[data-room]');
            if (roomDoor) {
                e.preventDefault();
                const roomNumber = roomDoor.dataset.room;
                this.openRoom(roomNumber);
            }
        });

        // Close on overlay click
        this.overlay.addEventListener('click', (e) => {
            if (e.target === this.overlay) {
                this.close();
            }
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });

        // Handle window resize to recalculate coordinates
        window.addEventListener('resize', () => {
            if (this.isOpen && this.currentRoom) {
                setTimeout(() => {
                    this.loadRoomCoordinates(this.currentRoom);
                }, 100);
            }
        });
    }

    async openRoom(roomNumber) {
        if (this.isOpen) return;
        
        console.log(`Opening room ${roomNumber}...`);
        this.currentRoom = roomNumber;
        this.isOpen = true;

        // Show modal
        this.overlay.classList.add('is-open');
        
        // Load room content
        await this.loadRoomContent(roomNumber);
        
        // Load and apply coordinates
        await this.loadRoomCoordinates(roomNumber);
    }

        close() {
        if (!this.isOpen) return;
        this.isOpen = false;
        this.overlay.classList.remove('is-open');
        this.currentRoom = null;
        const roomContent = document.getElementById('roomContent');
        if (roomContent) {
            roomContent.innerHTML = '';
            roomContent.style.backgroundImage = 'none';
        }
    }

    async loadRoomContent(roomNumber) {
        const roomContent = document.getElementById('roomContent');

        try {
            // Set background image
            const backgroundUrl = `/images/backgrounds/background_room${roomNumber}.webp`;
            roomContent.style.backgroundImage = `url('${backgroundUrl}')`;
            roomContent.style.backgroundSize = 'contain';
            roomContent.style.backgroundPosition = 'center';
            roomContent.style.backgroundRepeat = 'no-repeat';

            // Store original image dimensions for scaling calculations
            roomContent.dataset.originalWidth = '1280';
            roomContent.dataset.originalHeight = '896';

            // Create product icons container
            roomContent.innerHTML = `
                <div class="room-title" style="position: absolute; top: 20px; left: 20px; color: white; font-size: 24px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.7); z-index: 10;">
                    Room ${roomNumber}
                </div>
                <div id="productIcons" style="position: relative; width: 100%; height: 100%;"></div>
            `;

            console.log(`Room ${roomNumber} content loaded with original dimensions 1280x896`);
        } catch (error) {
            console.error('Error loading room content:', error);
            roomContent.innerHTML = '<p style="color: red; padding: 20px;">Error loading room content</p>';
        }
    }

    async loadRoomCoordinates(roomNumber) {
        try {
            const response = await fetch(`/api/get_room_coordinates.php?room_type=room${roomNumber}`);
            const data = await response.json();

            if (data.success && data.coordinates) {
                // Clear existing icons before applying new ones
                const productIconsContainer = document.getElementById('productIcons');
                if (productIconsContainer) {
                    productIconsContainer.innerHTML = '';
                }

                this.applyCoordinates(data.coordinates);
                console.log(`Applied ${data.coordinates.length} coordinates for room ${roomNumber}`);
            } else {
                console.warn('No coordinates found for room', roomNumber);
            }
        } catch (error) {
            console.error('Error loading coordinates:', error);
        }
    }

    applyCoordinates(coordinates) {
        const productIconsContainer = document.getElementById('productIcons');
        const roomContent = document.getElementById('roomContent');
        if (!productIconsContainer || !roomContent) return;

        // Calculate scaling factors based on actual rendered background image size
        const scaling = this.calculateScaling(roomContent);

        console.log('Scaling factors:', scaling);

        coordinates.forEach((coord, index) => {
            const icon = document.createElement('div');
            icon.className = `room-product-icon area-${index + 1}`;

            // Apply scaled coordinates
            const scaledTop = Math.round(coord.top * scaling.scaleY + scaling.offsetY);
            const scaledLeft = Math.round(coord.left * scaling.scaleX + scaling.offsetX);
            const scaledWidth = Math.round(coord.width * scaling.scaleX);
            const scaledHeight = Math.round(coord.height * scaling.scaleY);

            icon.style.cssText = `
                position: absolute;
                top: ${scaledTop}px;
                left: ${scaledLeft}px;
                width: ${scaledWidth}px;
                height: ${scaledHeight}px;
                background: rgba(135, 172, 58, 0.8);
                border: 2px solid #87ac3a;
                border-radius: 4px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
                text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
                transition: all 0.3s ease;
                z-index: 5;
            `;

            icon.innerHTML = `<span style="font-size: ${Math.max(10, scaledWidth/8)}px;">Item ${index + 1}</span>`;

            // Add hover effect
            icon.addEventListener('mouseenter', () => {
                icon.style.background = 'rgba(135, 172, 58, 1)';
                icon.style.transform = 'scale(1.05)';
            });

            icon.addEventListener('mouseleave', () => {
                icon.style.background = 'rgba(135, 172, 58, 0.8)';
                icon.style.transform = 'scale(1)';
            });

            // Add click handler
            icon.addEventListener('click', () => {
                console.log(`Clicked product icon ${index + 1} at scaled position ${scaledLeft},${scaledTop} (original: ${coord.left},${coord.top})`);
                // Here you can add product detail modal functionality
            });

            productIconsContainer.appendChild(icon);

            console.log(`Icon ${index + 1}: Original(${coord.left},${coord.top}) -> Scaled(${scaledLeft},${scaledTop})`);
        });
    }

    calculateScaling(roomContent) {
        // Original image dimensions (from database coordinate system)
        const originalWidth = parseInt(roomContent.dataset.originalWidth) || 1280;
        const originalHeight = parseInt(roomContent.dataset.originalHeight) || 896;

        // Current container dimensions
        const containerWidth = roomContent.offsetWidth;
        const containerHeight = roomContent.offsetHeight;

        // Calculate aspect ratios
        const containerAspectRatio = containerWidth / containerHeight;
        const imageAspectRatio = originalWidth / originalHeight;

        let renderedImageWidth, renderedImageHeight;
        let offsetX = 0;
        let offsetY = 0;

        // Determine how the background image is actually rendered (background-size: contain)
        if (containerAspectRatio > imageAspectRatio) {
            // Container is wider than image - image height fills container, width is scaled
            renderedImageHeight = containerHeight;
            renderedImageWidth = renderedImageHeight * imageAspectRatio;
            offsetX = (containerWidth - renderedImageWidth) / 2;
        } else {
            // Container is taller than image - image width fills container, height is scaled
            renderedImageWidth = containerWidth;
            renderedImageHeight = renderedImageWidth / imageAspectRatio;
            offsetY = (containerHeight - renderedImageHeight) / 2;
        }

        // Calculate scaling factors
        const scaleX = renderedImageWidth / originalWidth;
        const scaleY = renderedImageHeight / originalHeight;

        console.log(`Container: ${containerWidth}x${containerHeight}, Original: ${originalWidth}x${originalHeight}`);
        console.log(`Rendered: ${renderedImageWidth}x${renderedImageHeight}, Offset: ${offsetX},${offsetY}`);
        console.log(`Scale: ${scaleX}x${scaleY}`);

        return {
            scaleX,
            scaleY,
            offsetX,
            offsetY,
            renderedImageWidth,
            renderedImageHeight
        };
    }

    close() {
        if (!this.isOpen) return;
        
        console.log('Closing room modal');
        this.overlay.classList.remove('is-open');
        this.isOpen = false;
        this.currentRoom = null;
        
        // Clear content
        const roomContent = document.getElementById('roomContent');
        if (roomContent) {
            roomContent.innerHTML = '';
            roomContent.style.backgroundImage = '';
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new SimpleRoomModal();
    console.log('Simple room modal system initialized');
});

// Also initialize immediately if DOM is already loaded
if (document.readyState === 'loading') {
    // DOM is still loading, wait for DOMContentLoaded
} else {
    // DOM is already loaded
    new SimpleRoomModal();
    console.log('Simple room modal system initialized (immediate)');
}
