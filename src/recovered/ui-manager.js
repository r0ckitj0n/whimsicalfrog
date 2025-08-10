/**
 * WhimsicalFrog UI Management and Indicators
 * Centralized JavaScript functions to eliminate duplication
 * Generated: 2025-07-01 23:31:50
 */

// UI Management Dependencies
// Requires: global-notifications.js

                            
                            // Function to force hide auto-save indicators
                            function hideAutoSaveIndicator() {
                                const indicators = document.querySelectorAll('.auto-save-indicator, .progress-bar, .loading-indicator');
                                                indicators.forEach(indicator => {
                    indicator.classList.add('indicator-hidden');
                });
                                
                                // Set timeout to double-check
                                                setTimeout(() => {
                    indicators.forEach(indicator => {
                        indicator.classList.add('indicator-hidden');
                    });
                }, 100);
                            }


// Auto-save indicator functions
function showAutoSaveIndicator() {
    const indicator = document.getElementById('dashboardAutoSaveIndicator');
    if (indicator) {
        indicator.classList.remove('hidden');
        indicator.textContent = '💾 Auto-saving...';
        indicator.className = 'px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm';
    }
}

