// Test notification functionality immediately
console.log('[NOTIFICATION-TEST] Testing notifications immediately...');

// Test the admin notification system
if (window.adminNotifications) {
    console.log('[NOTIFICATION-TEST] adminNotifications available, testing...');
    window.adminNotifications.success('Test notification - System working!', { title: 'Test Success' });
    setTimeout(() => {
        window.adminNotifications.error('Test error notification', { title: 'Test Error' });
    }, 2000);
    setTimeout(() => {
        window.adminNotifications.info('Test info notification', { title: 'Test Info' });
    }, 4000);
} else if (window.showSuccess) {
    console.log('[NOTIFICATION-TEST] Using showSuccess...');
    window.showSuccess('Test notification - showSuccess working!');
} else if (window.showNotification) {
    console.log('[NOTIFICATION-TEST] Using showNotification...');
    window.showNotification('Test notification - showNotification working!', 'success', { title: 'Test' });
} else {
    console.log('[NOTIFICATION-TEST] No notification system found, using alert...');
    alert('Test notification - No system found!');
}

console.log('[NOTIFICATION-TEST] AdminCustomersModule test complete!');

// Make test functions globally available
window.testNotifications = function() {
    console.log('[NOTIFICATION-TEST] Manual test triggered...');

    if (window.adminNotifications) {
        console.log('[NOTIFICATION-TEST] Testing adminNotifications manually...');
        window.adminNotifications.success('Manual test - Admin notifications working!', { title: 'Manual Test' });
    } else if (window.showSuccess) {
        console.log('[NOTIFICATION-TEST] Testing showSuccess manually...');
        window.showSuccess('Manual test - ShowSuccess working!');
    } else if (window.showNotification) {
        console.log('[NOTIFICATION-TEST] Testing showNotification manually...');
        window.showNotification('Manual test - ShowNotification working!', 'info', { title: 'Manual Test' });
    } else {
        console.log('[NOTIFICATION-TEST] No notification system for manual test...');
        alert('Manual test - No notification system!');
    }
};

console.log('[NOTIFICATION-TEST] Test functions available:');
console.log('- testNotifications() - Test notification system');
console.log('- window.adminNotifications - Direct access to admin notification system');
