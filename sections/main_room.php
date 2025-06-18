<?php
// Main room page with clickable doors for each category
?>
<style>
    .door-area {
        position: absolute;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        /* overflow: hidden; */
        pointer-events: auto;
    }
    
    .door-area:hover {
        transform: scale(1.05);
    }
    
    .door-label {
        display: none;
    }
    
    .door-sign {
        width: 100%;
        height: 100%;
        object-fit: contain;
        transition: transform 0.3s ease;
        background: transparent;
        mix-blend-mode: normal;
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
    }
    
    .door-area:hover .door-sign {
        transform: scale(1.1);
    }
    
    /* Welcome sign specific styles */
    .flex-grow picture {
        background: transparent;
        display: block;
        line-height: 0;
    }

    .flex-grow img {
        background: transparent;
        mix-blend-mode: normal;
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
        display: block;
        line-height: 0;
    }

    /* Remove special overflow and debug borders for Sublimation */
    #mainRoomPage { overflow: visible !important; }
    .door-area.area-5, .door-area.area-5 .door-sign { border: none !important; overflow: unset !important; }
</style>

<section id="mainRoomPage" class="p-2">
    <!-- Dynamic doors will be loaded here -->
    <div id="dynamicRoomDoors">
        <!-- Loading placeholder -->
        <div class="text-center text-gray-500 mt-10">
            Loading rooms...
        </div>
    </div>
</section>

<script>
let roomsData = [];

function enterRoom(roomNumber) {
    console.log('Entering room:', roomNumber);
    window.location.href = `/?page=room${roomNumber}`;
}

// Load room settings and create dynamic doors
async function loadRoomDoors() {
    try {
        const response = await fetch('/api/room_settings.php?action=get_navigation_rooms');
        const data = await response.json();
        
        if (data.success && data.rooms) {
            roomsData = data.rooms;
            createDynamicDoors(data.rooms);
            // Position doors after they're created
            setTimeout(() => {
                positionDoors();
            }, 100);
        } else {
            console.error('Failed to load room settings:', data.message);
            // Fallback to hardcoded doors if API fails
            createFallbackDoors();
        }
    } catch (error) {
        console.error('Error loading room settings:', error);
        createFallbackDoors();
    }
}

function createDynamicDoors(rooms) {
    const container = document.getElementById('dynamicRoomDoors');
    container.innerHTML = '';
    
    rooms.forEach((room, index) => {
        const areaNumber = index + 1;
        const doorHtml = `
            <div class="door-area area-${areaNumber}" onclick="enterRoom(${room.room_number})">
                <picture class="block">
                    <source srcset="images/sign_door_room${room.room_number}.webp" type="image/webp">
                    <img src="images/sign_door_room${room.room_number}.png" alt="${room.door_label}" class="door-sign">
                </picture>
                <div class="door-label">${room.door_label}</div>
            </div>
        `;
        container.innerHTML += doorHtml;
    });
}

function createFallbackDoors() {
    const fallbackRooms = [
        { room_number: 2, door_label: 'T-Shirts & Apparel' },
        { room_number: 3, door_label: 'Tumblers & Drinkware' },
        { room_number: 4, door_label: 'Custom Artwork' },
        { room_number: 6, door_label: 'Window Wraps' },
        { room_number: 5, door_label: 'Sublimation Items' }
    ];
    createDynamicDoors(fallbackRooms);
    setTimeout(() => {
        positionDoors();
    }, 100);
}

// Direct positioning script for main room doors
function positionDoors() {
    // Original image dimensions
    const originalImageWidth = 1280;
    const originalImageHeight = 896;
    
    // Door coordinates from user
    const doorCoordinates = [
        { selector: '.area-1', top: 243, left: 30, width: 234, height: 233 }, // Area 1
        { selector: '.area-2', top: 403, left: 390, width: 202, height: 241 }, // Area 2
        { selector: '.area-3', top: 271, left: 753, width: 170, height: 235 }, // Area 3
        { selector: '.area-4', top: 291, left: 1001, width: 197, height: 255 }, // Area 4
        { selector: '.area-5', top: 157, left: 486, width: 190, height: 230 } // Area 5
    ];

    // Get viewport dimensions - use full viewport
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    // Calculate the scale factor for the full-screen background
    const viewportRatio = viewportWidth / viewportHeight;
    const imageRatio = originalImageWidth / originalImageHeight;

    let scale, offsetX, offsetY;
    
    // Calculate how the background image is displayed (cover)
    if (viewportRatio > imageRatio) {
        // Viewport is wider than image ratio, image width matches viewport width
        scale = viewportWidth / originalImageWidth;
        offsetY = (viewportHeight - (originalImageHeight * scale)) / 2;
        offsetX = 0;
    } else {
        // Viewport is taller than image ratio, image height matches viewport height
        scale = viewportHeight / originalImageHeight;
        offsetX = (viewportWidth - (originalImageWidth * scale)) / 2;
        offsetY = 0;
    }
    
    console.log('Viewport dimensions:', viewportWidth, 'x', viewportHeight);
    console.log('Scale:', scale, 'Offsets:', offsetX, offsetY);
    
    // Position each door
    doorCoordinates.forEach(door => {
        const element = document.querySelector(door.selector);
        if (element) {
            // Apply scaled coordinates
            element.style.top = `${(door.top * scale) + offsetY}px`;
            element.style.left = `${(door.left * scale) + offsetX}px`;
            element.style.width = `${door.width * scale}px`;
            element.style.height = `${door.height * scale}px`;
            console.log(`Positioned ${door.selector}:`, element.style.top, element.style.left);
        }
    });
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadRoomDoors();
    window.addEventListener('resize', positionDoors);
});
</script> 
