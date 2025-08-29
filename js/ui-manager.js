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
<<<<<<< HEAD
                                indicators.forEach(indicator => {
                                    indicator.style.display = 'none';
                                    indicator.style.visibility = 'hidden';
                                    indicator.style.opacity = '0';
                                    indicator.classList.add('hidden');
                                });
                                
                                // Set timeout to double-check
                                setTimeout(() => {
                                    indicators.forEach(indicator => {
                                        indicator.style.display = 'none';
                                        indicator.style.visibility = 'hidden';
                                        indicator.style.opacity = '0';
                                    });
                                }, 100);
=======
                                                indicators.forEach(indicator => {
                    indicator.classList.add('indicator-hidden');
                });
                                
                                // Set timeout to double-check
                                                setTimeout(() => {
                    indicators.forEach(indicator => {
                        indicator.classList.add('indicator-hidden');
                    });
                }, 100);
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
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

