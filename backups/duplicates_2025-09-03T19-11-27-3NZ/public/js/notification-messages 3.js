// Centralized notification messages configuration
// This file can be easily customized by admins without touching core code

window.NotificationMessages = {
    // Success messages
    success: {
        itemSaved: 'Item saved successfully!',
        itemDeleted: 'Item deleted successfully!',
        imageUploaded: 'Image uploaded successfully!',
        priceUpdated: 'Price updated successfully!',
        stockSynced: 'Stock levels synchronized!',
        templateSaved: 'Template saved successfully!',
        aiProcessingComplete: 'AI processing completed! Images have been updated.',
        marketingGenerated: '🎯 AI content generated successfully!',
        costBreakdownApplied: '✅ Cost breakdown applied and saved!',
        settingsSaved: 'Settings saved successfully!'
    },
    
    // Error messages
    error: {
        itemNotFound: 'Item not found. Please refresh the page.',
        uploadFailed: 'Upload failed. Please try again.',
        invalidInput: 'Please check your input and try again.',
        networkError: 'Network error occurred. Please check your connection.',
        aiProcessingFailed: 'AI processing failed. Please try again.',
        insufficientData: 'Insufficient data provided.',
        serverError: 'Server error occurred. Please contact support if this persists.',
        fileTooBig: 'File is too large. Maximum size allowed is 10MB.',
        invalidFileType: 'Invalid file type. Please upload images only.'
    },
    
    // Warning messages
    warning: {
        unsavedChanges: 'You have unsaved changes. Are you sure you want to leave?',
        noItemsSelected: 'Please select at least one item.',
        lowStock: 'Warning: Stock level is low.',
        duplicateEntry: 'This entry already exists.',
        dataIncomplete: 'Some data may be incomplete.'
    },
    
    // Info messages
    info: {
        processing: 'Processing your request...',
        loading: 'Loading data...',
        analyzing: 'Analyzing with AI...',
        saving: 'Saving changes...',
        uploading: 'Uploading files...'
    },
    
    // Validation messages
    validation: {
        required: 'This field is required.',
        emailInvalid: 'Please enter a valid email address.',
        priceInvalid: 'Please enter a valid price.',
        quantityInvalid: 'Please enter a valid quantity.',
        skuRequired: 'SKU is required.',
        nameRequired: 'Name is required.',
        colorRequired: 'Please select a color before adding to cart.',
        paymentRequired: 'Please select a payment method.',
        shippingRequired: 'Please select a shipping method.'
    }
};

// Helper function to get message with fallback
window.getMessage = function(category, key, fallback = 'Operation completed') {
    try {
        return window.NotificationMessages[category]?.[key] || fallback;
    } catch (e) {
        return fallback;
    }
};

// Enhanced notification functions that use the message config
window.showSuccessMessage = function(key, fallback) {
    showSuccess(getMessage('success', key, fallback));
};

window.showErrorMessage = function(key, fallback) {
    showError(getMessage('error', key, fallback));
};

window.showWarningMessage = function(key, fallback) {
    showWarning(getMessage('warning', key, fallback));
};

window.showInfoMessage = function(key, fallback) {
    showInfo(getMessage('info', key, fallback));
};

window.showValidationMessage = function(key, fallback) {
    showValidation(getMessage('validation', key, fallback));
}; 