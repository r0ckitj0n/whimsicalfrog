// AI Processing Modal Module (Vite-managed)
// Initializes the AIProcessingModal class if the modal exists on the page

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
    if (this.cancelBtn) this.cancelBtn.addEventListener('click', () => this.cancel());
    if (this.doneBtn) this.doneBtn.addEventListener('click', () => this.close());
    if (this.closeBtn) this.closeBtn.addEventListener('click', () => this.close());

    // Close modal on backdrop click
    if (this.modal) {
      this.modal.addEventListener('click', (e) => {
        if (e.target === this.modal) {
          this.cancel();
        }
      });
    }
  }

  show() {
    if (!this.modal) return;
    this.modal.classList.remove('hidden');
    this.reset();
  }

  hide() {
    if (!this.modal) return;
    this.modal.classList.add('hidden');
  }

  close() {
    this.hide();
    if (this.onComplete) {
      try { this.onComplete(); } catch (e) { /* noop */ }
    }
  }

  cancel() {
    if (this.currentProcessing) {
      console.log('Canceling AI processing...');
    }
    this.hide();
    if (this.onCancel) {
      try { this.onCancel(); } catch (e) { /* noop */ }
    }
  }

  reset() {
    if (this.progressBar) this.progressBar.style.width = '0%';

    for (let i = 1; i <= 3; i++) {
      this.resetStep(i);
    }

    if (this.results) this.results.classList.add('hidden');
    if (this.error) this.error.classList.add('hidden');
    if (this.details) this.details.classList.add('hidden');

    if (this.cancelBtn) this.cancelBtn.classList.remove('hidden');
    if (this.doneBtn) this.doneBtn.classList.add('hidden');
    if (this.closeBtn) this.closeBtn.classList.add('hidden');
  }

  resetStep(stepNumber) {
    const icon = document.getElementById(`step${stepNumber}Icon`);
    const status = document.getElementById(`step${stepNumber}Status`);
    if (!icon || !status) return;

    icon.className = 'w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center';
    icon.innerHTML = '<div class="w-2 h-2 bg-gray-300 rounded-full"></div>';

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
    if (this.progressBar) this.progressBar.style.width = `${percent}%`;
  }

  updateStep(stepNumber, status, isComplete = false, isError = false) {
    const icon = document.getElementById(`step${stepNumber}Icon`);
    const statusElement = document.getElementById(`step${stepNumber}Status`);
    if (!icon || !statusElement) return;

    statusElement.textContent = status;

    if (isError) {
      icon.className = 'w-6 h-6 rounded-full border-2 border-red-500 bg-red-500 flex items-center justify-center';
      icon.innerHTML = '<svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>';
    } else if (isComplete) {
      icon.className = 'w-6 h-6 rounded-full border-2 border-green-500 bg-green-500 flex items-center justify-center';
      icon.innerHTML = '<svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>';
    } else {
      icon.className = 'w-6 h-6 rounded-full border-2 border-blue-500 bg-blue-500 flex items-center justify-center';
      icon.innerHTML = '<div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>';
    }
  }

  showSuccess(message, details = []) {
    this.updateProgress(100);

    if (this.results) this.results.classList.remove('hidden');
    const resultText = document.getElementById('aiProcessingResultText');
    if (resultText) resultText.textContent = message;

    if (Array.isArray(details) && details.length) {
      this.showDetails(details);
    }

    if (this.cancelBtn) this.cancelBtn.classList.add('hidden');
    if (this.doneBtn) this.doneBtn.classList.remove('hidden');
    if (this.closeBtn) this.closeBtn.classList.remove('hidden');
  }

  showError(message) {
    if (this.error) this.error.classList.remove('hidden');
    const errText = document.getElementById('aiProcessingErrorText');
    if (errText) errText.textContent = message;

    if (this.cancelBtn) this.cancelBtn.classList.add('hidden');
    if (this.doneBtn) this.doneBtn.classList.remove('hidden');
    if (this.closeBtn) this.closeBtn.classList.remove('hidden');
  }

  showDetails(details) {
    if (this.details) this.details.classList.remove('hidden');
    const detailsList = document.getElementById('aiProcessingDetailsList');
    if (!detailsList) return;
    detailsList.innerHTML = '';
    details.forEach(detail => {
      const div = document.createElement('div');
      div.textContent = `• ${detail}`;
      detailsList.appendChild(div);
    });
  }

  async processImage(imagePath, sku, options = {}) {
    this.show();
    this.currentProcessing = true;

    try {
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

    steps.forEach((step) => {
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

function initializeIfPresent() {
  const modal = document.getElementById('aiProcessingModal');
  if (!modal) return;
  const instance = new AIProcessingModal();
  window.aiProcessingModal = instance;
  window.processImageWithAI = async function(imagePath, sku, options = {}) {
    return instance.processImage(imagePath, sku, options);
  };
  console.log('[ai-processing-modal] Initialized');
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeIfPresent, { once: true });
} else {
  initializeIfPresent();
}
