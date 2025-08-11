<?php
/**
 * AI Processing Modal Component
 *
 * Shows real-time progress for AI image processing
 * Displays processing steps and status updates
 */
?>

<!- AI Processing Modal ->
<div id="aiProcessingModal" class="modal-overlay hidden">
    <div class="modal-content">
        <!- Modal Header ->
        <div class="modal-header">
            <h3 class="modal-title">
                AI Image Processing
            </h3>
            <button id="aiProcessingCloseBtn" class="modal-close hidden">
                <svg class="u-w-6 u-h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <!- Modal Body ->
        <div class="modal-body">
            <!- Processing Status ->
            <div id="aiProcessingStatus" class="u-mb-4">
                <div class="u-text-sm u-text-gray-600 u-mb-2">Processing your image...</div>
                <div class="u-w-full u-bg-gray-200 u-rounded-full u-h-2">
                    <div id="aiProcessingProgressBar" class="u-bg-green-500 u-h-2 u-rounded-full u-transition-all u-duration-300 width_0"></div>
                </div>
            </div>
            
            <!- Processing Steps ->
            <div id="aiProcessingSteps" class="u-space-y-3">
                <!- Step 1: AI Analysis ->
                <div id="step1" class="u-display-flex u-items-center u-space-x-3">
                    <div id="step1Icon" class="u-w-6 u-h-6 u-rounded-full u-border-2 u-border-gray-300 u-display-flex u-items-center u-justify-center">
                        <div class="u-w-2 u-h-2 u-bg-gray-300 u-rounded-full"></div>
                    </div>
                    <div>
                        <div class="u-font-medium u-text-gray-900">AI Edge Analysis</div>
                        <div id="step1Status" class="u-text-sm u-text-gray-500">Analyzing image to detect object boundaries...</div>
                    </div>
                </div>
                
                <!- Step 2: Smart Cropping ->
                <div id="step2" class="u-display-flex u-items-center u-space-x-3">
                    <div id="step2Icon" class="u-w-6 u-h-6 u-rounded-full u-border-2 u-border-gray-300 u-display-flex u-items-center u-justify-center">
                        <div class="u-w-2 u-h-2 u-bg-gray-300 u-rounded-full"></div>
                    </div>
                    <div>
                        <div class="u-font-medium u-text-gray-900">Smart Cropping</div>
                        <div id="step2Status" class="u-text-sm u-text-gray-500">Waiting for analysis...</div>
                    </div>
                </div>
                
                <!- Step 3: WebP Conversion ->
                <div id="step3" class="u-display-flex u-items-center u-space-x-3">
                    <div id="step3Icon" class="u-w-6 u-h-6 u-rounded-full u-border-2 u-border-gray-300 u-display-flex u-items-center u-justify-center">
                        <div class="u-w-2 u-h-2 u-bg-gray-300 u-rounded-full"></div>
                    </div>
                    <div>
                        <div class="u-font-medium u-text-gray-900">WebP Conversion</div>
                        <div id="step3Status" class="u-text-sm u-text-gray-500">Waiting for cropping...</div>
                    </div>
                </div>
            </div>
            
            <!- Processing Results ->
            <div id="aiProcessingResults" class="u-mt-4 hidden">
                <div class="modal-success">
                    <div class="u-display-flex u-items-center">
                        <div class="u-flex-shrink-0">
                            <svg class="u-h-5 u-w-5 u-text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="u-ml-3">
                            <h3 class="u-text-sm u-font-medium u-text-green-800">Processing Complete!</h3>
                            <div id="aiProcessingResultText" class="u-mt-1 u-text-sm u-text-green-700"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!- Error Display ->
            <div id="aiProcessingError" class="u-mt-4 hidden">
                <div class="modal-error">
                    <div class="u-display-flex u-items-center">
                        <div class="u-flex-shrink-0">
                            <svg class="u-h-5 u-w-5 u-text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="u-ml-3">
                            <h3 class="u-text-sm u-font-medium u-text-red-800">Processing Error</h3>
                            <div id="aiProcessingErrorText" class="u-mt-1 u-text-sm u-text-red-700"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!- Processing Details ->
            <div id="aiProcessingDetails" class="u-mt-4 hidden">
                <div class="u-bg-blue-50 u-border u-border-blue-200 u-rounded-lg u-p-3">
                    <div class="u-text-sm u-font-medium u-text-blue-800 u-mb-2">Processing Details:</div>
                    <div id="aiProcessingDetailsList" class="u-text-sm u-text-blue-700 u-space-y-1"></div>
                </div>
            </div>
        </div>
        
        <!- Modal Footer ->
        <div class="modal-footer">
            <button id="aiProcessingCancelBtn" class="btn btn-secondary">
                Cancel
            </button>
            <button id="aiProcessingDoneBtn" class="btn btn-primary hidden">
                Done
            </button>
        </div>
    </div>
</div>

<!-- AI Processing Modal JS moved to Vite module: src/modules/ai-processing-modal.js -->

 