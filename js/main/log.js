// Logging utility
function log(message, level = 'info') {
    if (!WF_CORE.debug && level === 'debug') return;
    const timestamp = new Date().toISOString().slice(11, 23);
    const prefix = `[WF-Core ${timestamp}]`;
    
    switch (level) {
        case 'error':
            console.error(prefix, message);
            break;
        case 'warn':
            console.warn(prefix, message);
            break;
        case 'debug':
            console.debug(prefix, message);
            break;
        default:
            console.log(prefix, message);
    }
}
