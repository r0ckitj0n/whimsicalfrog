/**
 * Main application entry point.
 * This file is responsible for initializing the core framework and all primary modules.
 */

// Import CSS to be processed by Vite
import '../css/z-index.css';
import '../css/main.css';

import './whimsical-frog-core-unified.js';
import CartSystem from './modules/cart-system.js';
import RoomModalManager from './modules/room-modal-manager.js';
import SearchSystem from './modules/search-system.js';
import SalesSystem from './modules/sales-system.js';
import WhimsicalFrogUtils from './modules/utilities.js';
import MainApplication from './main-application.js';
import ShopPage from './shop.js';

// Critical missing modules for core functionality
import './dynamic-background-loader.js';  // Background loading
import './room-coordinate-manager.js';    // Room map coordinates
import './api-client.js';                 // API communication
import './global-modals.js';              // Modal system
import './global-notifications.js';       // Notification system
import './modal-manager.js';              // General modal management
import './landing-page.js';               // Landing page functionality
import './room-main.js';                  // Room main page
import './analytics.js';                  // Site analytics

console.log('app.js loaded');

// Initialize recovered core systems
WhimsicalFrog.ready(() => {
    console.log('[App] Initializing recovered systems...');
    
    // Initialize cart system
    const cartSystem = new CartSystem();
    window.WF_Cart = cartSystem;
    WhimsicalFrog.registerModule('cart-system', cartSystem);
    
    // Initialize room modal manager
    const roomModalManager = new RoomModalManager();
    window.WF_RoomModal = roomModalManager;
    WhimsicalFrog.registerModule('room-modal-manager', roomModalManager);
    
    // Initialize search system
    const searchSystem = new SearchSystem();
    window.WF_Search = searchSystem;
    WhimsicalFrog.registerModule('search-system', searchSystem);
    
    // Initialize sales system
    const salesSystem = new SalesSystem();
    window.WF_Sales = salesSystem;
    WhimsicalFrog.registerModule('sales-system', salesSystem);
    
    // Initialize consolidated utilities
    const utils = new WhimsicalFrogUtils();
    window.WF_Utils = utils;
    WhimsicalFrog.registerModule('utilities', utils);
    
    console.log('[App] All core systems and utilities initialized successfully');
});

// The core WF object is exported and initialized automatically.
// Modules that need to run on specific pages will be imported here.
// Their own init logic will determine if they should run.
