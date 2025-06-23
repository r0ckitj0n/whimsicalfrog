<?php
/**
 * AI Processing Modal Component
 * 
 * Shows real-time progress for AI image processing
 * Displays processing steps and status updates
 */
?>

<!-- AI Processing Modal -->
<div id="aiProcessingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">
                        🎨 AI Image Processing
                    </h3>
                    <button id="aiProcessingCloseBtn" class="text-gray-400 hover:text-gray-600 hidden">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Modal Body -->
            <div class="px-6 py-4">
                <!-- Processing Status -->
                <div id="aiProcessingStatus" class="mb-4">
                    <div class="text-sm text-gray-600 mb-2">Processing your image...</div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div id="aiProcessingProgressBar" class="bg-green-500 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                </div>
                
                <!-- Processing Steps -->
                <div id="aiProcessingSteps" class="space-y-3">
                    <!-- Step 1: AI Analysis -->
                    <div id="step1" class="flex items-center space-x-3">
                        <div id="step1Icon" class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center">
                            <div class="w-2 h-2 bg-gray-300 rounded-full"></div>
                        </div>
                        <div>
                            <div class="font-medium text-gray-900">AI Edge Analysis</div>
                            <div id="step1Status" class="text-sm text-gray-500">Analyzing image to detect object boundaries...</div>
                        </div>
                    </div>
                    
                    <!-- Step 2: Smart Cropping -->
                    <div id="step2" class="flex items-center space-x-3">
                        <div id="step2Icon" class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center">
                            <div class="w-2 h-2 bg-gray-300 rounded-full"></div>
                        </div>
                        <div>
                            <div class="font-medium text-gray-900">Smart Cropping</div>
                            <div id="step2Status" class="text-sm text-gray-500">Waiting for analysis...</div>
                        </div>
                    </div>
                    
                    <!-- Step 3: WebP Conversion -->
                    <div id="step3" class="flex items-center space-x-3">
                        <div id="step3Icon" class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center">
                            <div class="w-2 h-2 bg-gray-300 rounded-full"></div>
                        </div>
                        <div>
                            <div class="font-medium text-gray-900">WebP Conversion</div>
                            <div id="step3Status" class="text-sm text-gray-500">Waiting for cropping...</div>
                        </div>
                    </div>
                </div>
                
                <!-- Processing Results -->
                <div id="aiProcessingResults" class="mt-4 hidden">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800">Processing Complete!</h3>
                                <div id="aiProcessingResultText" class="mt-1 text-sm text-green-700"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Error Display -->
                <div id="aiProcessingError" class="mt-4 hidden">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Processing Error</h3>
                                <div id="aiProcessingErrorText" class="mt-1 text-sm text-red-700"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Processing Details -->
                <div id="aiProcessingDetails" class="mt-4 hidden">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <div class="text-sm font-medium text-blue-800 mb-2">Processing Details:</div>
                        <div id="aiProcessingDetailsList" class="text-sm text-blue-700 space-y-1"></div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-gray-200">
                <div class="flex justify-end space-x-3">
                    <button id="aiProcessingCancelBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">
                        Cancel
                    </button>
                    <button id="aiProcessingDoneBtn" class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 hidden">
                        Done
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * AI Processing Modal JavaScript
 */

class AIProcessingModal {
    constructor() {
        this.modal = document.getElementById('aiProcessingModal');
        this.progressBar = document.getElementById('aiProcessingProgressBar');
        this.results = document.getElementById('aiProcessingResults');
        this.error = document.getElementById('aiProcessingError');
        this.details = document.getElementById('aiProcessingDetails');
        this.cancelBtn = document.getElementById('aiProcessingCancelBtn');
        this.doneBtn = document.getElementById('aiProcessingDoneBtn');
        this.closeBtn = document.getElementById('aiProcessingCloseBtn');
        
        this.currentProcessing = null;
        this.setupEventListeners();
    }
    
    setupEventListeners() {
        this.cancelBtn.addEventListener('click', () => this.cancel());
        this.doneBtn.addEventListener('click', () => this.close());
        this.closeBtn.addEventListener('click', () => this.close());
        
        // Close modal on backdrop click
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.cancel();
            }
        });
    }
    
    show() {
        this.modal.classList.remove('hidden');
        this.reset();
    }
    
    hide() {
        this.modal.classList.add('hidden');
    }
    
    close() {
        this.hide();
        if (this.onComplete) {
            this.onComplete();
        }
    }
    
    cancel() {
        if (this.currentProcessing) {
            // Cancel the processing if possible
            console.log('Canceling AI processing...');
        }
        this.hide();
        if (this.onCancel) {
            this.onCancel();
        }
    }
    
    reset() {
        // Reset progress
        this.progressBar.style.width = '0%';
        
        // Reset steps
        for (let i = 1; i <= 3; i++) {
            this.resetStep(i);
        }
        
        // Hide result sections
        this.results.classList.add('hidden');
        this.error.classList.add('hidden');
        this.details.classList.add('hidden');
        
        // Reset buttons
        this.cancelBtn.classList.remove('hidden');
        this.doneBtn.classList.add('hidden');
        this.closeBtn.classList.add('hidden');
    }
    
    resetStep(stepNumber) {
        const icon = document.getElementById(`step${stepNumber}Icon`);
        const status = document.getElementById(`step${stepNumber}Status`);
        
        // Reset icon
        icon.className = 'w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center';
        icon.innerHTML = '<div class="w-2 h-2 bg-gray-300 rounded-full"></div>';
        
        // Reset status based on step
        switch (stepNumber) {
            case 1:
                status.textContent = 'Analyzing image to detect object boundaries...';
                break;
            case 2:
                status.textContent = 'Waiting for analysis...';
                break;
            case 3:
                status.textContent = 'Waiting for cropping...';
                break;
        }
    }
    
    updateProgress(percent) {
        this.progressBar.style.width = `${percent}%`;
    }
    
    updateStep(stepNumber, status, isComplete = false, isError = false) {
        const icon = document.getElementById(`step${stepNumber}Icon`);
        const statusElement = document.getElementById(`step${stepNumber}Status`);
        
        statusElement.textContent = status;
        
        if (isError) {
            icon.className = 'w-6 h-6 rounded-full border-2 border-red-500 bg-red-500 flex items-center justify-center';
            icon.innerHTML = '<svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>';
        } else if (isComplete) {
            icon.className = 'w-6 h-6 rounded-full border-2 border-green-500 bg-green-500 flex items-center justify-center';
            icon.innerHTML = '<svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>';
        } else {
            // In progress
            icon.className = 'w-6 h-6 rounded-full border-2 border-blue-500 bg-blue-500 flex items-center justify-center';
            icon.innerHTML = '<div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>';
        }
    }
    
    showSuccess(message, details = []) {
        this.updateProgress(100);
        
        this.results.classList.remove('hidden');
        document.getElementById('aiProcessingResultText').textContent = message;
        
        if (details.length > 0) {
            this.showDetails(details);
        }
        
        // Show done button
        this.cancelBtn.classList.add('hidden');
        this.doneBtn.classList.remove('hidden');
        this.closeBtn.classList.remove('hidden');
    }
    
    showError(message) {
        this.error.classList.remove('hidden');
        document.getElementById('aiProcessingErrorText').textContent = message;
        
        // Show close button
        this.cancelBtn.classList.add('hidden');
        this.doneBtn.classList.remove('hidden');
        this.closeBtn.classList.remove('hidden');
    }
    
    showDetails(details) {
        this.details.classList.remove('hidden');
        const detailsList = document.getElementById('aiProcessingDetailsList');
        detailsList.innerHTML = '';
        
        details.forEach(detail => {
            const div = document.createElement('div');
            div.textContent = `• ${detail}`;
            detailsList.appendChild(div);
        });
    }
    
    /**
     * Process an image with AI
     */
    async processImage(imagePath, sku, options = {}) {
        this.show();
        this.currentProcessing = true;
        
        try {
            // Step 1: Start AI Analysis
            this.updateStep(1, 'Analyzing image with AI...', false, false);
            this.updateProgress(10);
            
            const response = await fetch('/api/process_image_ai.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'process_image',
                    imagePath: imagePath,
                    sku: sku,
                    options: options
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Processing failed');
            }
            
            // Process the steps from the response
            this.processSteps(data);
            
            return data;
            
        } catch (error) {
            console.error('AI processing error:', error);
            this.updateStep(1, 'Analysis failed', false, true);
            this.showError(error.message);
            throw error;
        } finally {
            this.currentProcessing = null;
        }
    }
    
    processSteps(data) {
        const steps = data.processing_steps || [];
        let currentStep = 1;
        let progress = 20;
        
        steps.forEach((step, index) => {
            if (step.includes('AI edge analysis') || step.includes('AI Analysis')) {
                this.updateStep(1, 'AI analysis completed', true, false);
                currentStep = 2;
                progress = 40;
            } else if (step.includes('smart cropping') || step.includes('cropping')) {
                this.updateStep(2, 'Smart cropping applied', true, false);
                currentStep = 3;
                progress = 70;
            } else if (step.includes('WebP') || step.includes('conversion')) {
                this.updateStep(3, 'WebP conversion completed', true, false);
                progress = 100;
            } else if (step.includes('fallback')) {
                this.updateStep(1, 'Using fallback edge detection', true, false);
                currentStep = 2;
                progress = 30;
            } else if (step.includes('Error')) {
                this.updateStep(currentStep, step, false, true);
                this.showError(step);
                return;
            }
            
            this.updateProgress(progress);
        });
        
        if (data.success) {
            const message = data.ai_analysis ? 
                'Image processed with AI edge detection' : 
                'Image processed with fallback edge detection';
            
            this.showSuccess(message, data.processing_steps);
        }
    }
}

// Initialize the modal
window.aiProcessingModal = new AIProcessingModal();

// Helper function to process images
window.processImageWithAI = async function(imagePath, sku, options = {}) {
    try {
        const result = await window.aiProcessingModal.processImage(imagePath, sku, options);
        return result;
    } catch (error) {
        console.error('Image processing failed:', error);
        throw error;
    }
};
</script>

<style>
.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: .5;
    }
}
</style> 