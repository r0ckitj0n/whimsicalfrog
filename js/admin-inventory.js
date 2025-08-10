class AdminInventoryModule {
    constructor() {
        this.currentItemSku = null;
        this.items = [];
        this.categories = [];
        this.costBreakdown = { materials: {}, labor: {}, energy: {}, equipment: {}, totals: {} };
        this.currentEditCostItem = null;
        this.tooltipTimeout = null;
        this.currentTooltip = null;

        this.loadData();
        this.bindEvents();
        this.init();
    }

    loadData() {
        const dataElement = document.getElementById('inventory-data');
        if (dataElement) {
            try {
                const data = JSON.parse(dataElement.textContent);
                this.currentItemSku = data.currentItemSku;
                this.items = data.items || [];
                this.categories = data.categories || [];
                if (data.costBreakdown) {
                    this.costBreakdown = {
                        materials: data.costBreakdown.materials || {},
                        labor: data.costBreakdown.labor || {},
                        energy: data.costBreakdown.energy || {},
                        equipment: data.costBreakdown.equipment || {},
                        totals: data.costBreakdown.totals || {}
                    };
                }
            } catch (e) {
                console.error("Error parsing inventory data:", e);
            }
        }
    }

    init() {
        this.renderCostList('materials');
        this.renderCostList('labor');
        this.renderCostList('energy');
        this.renderCostList('equipment');
        this.updateTotalsDisplay();
        this.loadExistingPriceSuggestion(this.currentItemSku);
        this.loadExistingMarketingSuggestion(this.currentItemSku);
        this.updateCategoryDropdown();
    }

    bindEvents() {
        document.body.addEventListener('click', this.handleDelegatedClick.bind(this));
        document.addEventListener('keydown', this.handleKeyDown.bind(this));

        const imageUploadInput = document.getElementById('imageUpload');
        if (imageUploadInput) {
            imageUploadInput.addEventListener('change', this.handleImageUpload.bind(this));
        }
    }

    handleKeyDown(event) {
        if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') {
            return;
        }

        if (event.key === 'ArrowRight') {
            this.navigateToItem('next');
        } else if (event.key === 'ArrowLeft') {
            this.navigateToItem('prev');
        }
    }

    handleDelegatedClick(event) {
        const target = event.target.closest('[data-action]');
        if (!target) return;

        const action = target.dataset.action;
        const id = target.dataset.id;
        const category = target.dataset.category;

        if (target.tagName === 'BUTTON') {
            event.preventDefault();
        }

        switch (action) {
            case 'navigate-item':
                this.navigateToItem(target.dataset.direction);
                break;
            case 'set-primary-image':
                this.setPrimaryImage(id);
                break;
            case 'delete-image':
                this.confirmDeleteImage(id);
                break;
            case 'trigger-upload':
                document.getElementById('imageUpload').click();
                break;
            case 'process-images-ai':
                this.processExistingImagesWithAI();
                break;
            case 'open-cost-modal':
                this.openCostModal(category, id);
                break;
            case 'close-cost-modal':
                this.closeCostModal();
                break;
            case 'save-cost-item':
                this.saveCostItem();
                break;
            case 'delete-cost-item':
                this.confirmDeleteCostItem(category, id);
                break;
            case 'clear-cost-breakdown':
                this.confirmClearCostBreakdown();
                break;
            case 'get-cost-suggestion':
                this.getCostSuggestion();
                break;
            case 'get-price-suggestion':
                this.getPriceSuggestion();
                break;
            case 'apply-price-suggestion':
                this.applyPriceSuggestion();
                break;
            case 'clear-price-suggestion':
                this.clearPriceSuggestion();
                break;
            case 'generate-marketing-copy':
                this.generateMarketingCopy();
                break;
            case 'apply-cost-suggestion-to-cost':
                 this.applyCostSuggestionToCost();
                 break;
            case 'apply-suggested-cost-to-cost-field':
                this.applySuggestedCostToCostField(target);
                break;
             case 'populate-cost-breakdown-from-suggestion':
                this.populateCostBreakdownFromSuggestion(JSON.parse(target.getAttribute('data-suggestion').replace(/&quot;/g, '"').replace(/&#39;/g, "'")));
                this.closeCostSuggestionChoiceDialog();
                break;
            case 'close-cost-suggestion-choice-dialog':
                this.closeCostSuggestionChoiceDialog();
                break;
            case 'refresh-categories':
                this.refreshCategoryDropdown();
                break;
        }
    }

    navigateToItem(direction) {
        if (!this.currentItemSku || this.items.length === 0) return;
        const currentIndex = this.items.findIndex(item => item.sku === this.currentItemSku);
        if (currentIndex === -1) return;
        let nextIndex;
        if (direction === 'next') {
            nextIndex = (currentIndex + 1) % this.items.length;
        } else {
            nextIndex = (currentIndex - 1 + this.items.length) % this.items.length;
        }
        const nextSku = this.items[nextIndex].sku;
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('edit', nextSku);
        currentUrl.searchParams.delete('view');
        window.location.href = currentUrl.toString();
    }

    async setPrimaryImage(imageId) {
        try {
            const response = await fetch('/api/set_primary_image.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sku: this.currentItemSku, image_id: imageId })
            });
            const result = await response.json();
            if (result.success) {
                this.showSuccess('Primary image updated!');
                document.querySelectorAll('.image-card').forEach(card => card.classList.remove('border-green-500', 'border-4'));
                document.querySelector(`.image-card[data-id='${imageId}']`).classList.add('border-green-500', 'border-4');
            } else {
                this.showError(result.error || 'Failed to set primary image.');
            }
        } catch (error) {
            this.showError('An error occurred. Please try again.');
            console.error('Set primary image error:', error);
        }
    }

    confirmDeleteImage(imageId) {
        this.showConfirmationModal('Delete Image', 'Are you sure you want to delete this image? This action cannot be undone.', () => this.deleteImage(imageId));
    }

    async deleteImage(imageId) {
        try {
            const response = await fetch('/api/delete_item_image.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image_id: imageId })
            });
            const result = await response.json();
            if (result.success) {
                this.showSuccess('Image deleted successfully!');
                const imageCard = document.querySelector(`.image-card[data-id='${imageId}']`);
                if (imageCard) {
                    imageCard.remove();
                }
            } else {
                this.showError(result.error || 'Failed to delete image.');
            }
        } catch (error) {
            this.showError('An error occurred. Please try again.');
            console.error('Delete image error:', error);
        }
    }

    async handleImageUpload(event) {
        const files = event.target.files;
        if (files.length === 0) return;
        const formData = new FormData();
        formData.append('sku', this.currentItemSku);
        for (let i = 0; i < files.length; i++) {
            formData.append('images[]', files[i]);
        }
        const uploadIndicator = document.getElementById('uploadIndicator');
        uploadIndicator.classList.remove('hidden');
        try {
            const response = await fetch('/api/upload_item_images.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                this.showSuccess('Images uploaded successfully! Refreshing...');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                this.showError(result.error || 'An error occurred during upload.');
            }
        } catch (error) {
            this.showError('Upload failed. Please check the console for details.');
            console.error('Upload error:', error);
        } finally {
            uploadIndicator.classList.add('hidden');
        }
    }

    async processExistingImagesWithAI() {
        this.showConfirmationModal('Process Images with AI', 'This will analyze existing images to improve product data. This may take a moment. Continue?', async () => {
            const button = document.querySelector('[data-action="process-images-ai"]');
            const originalHtml = button.innerHTML;
            button.innerHTML = 'Processing...';
            button.disabled = true;
            try {
                const response = await fetch(`/api/run_image_analysis.php?sku=${this.currentItemSku}`);
                const result = await response.json();
                if (result.success) {
                    this.showSuccess('AI processing complete! The page will now reload.');
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    this.showError(result.error || 'AI processing failed.');
                }
            } catch (error) {
                this.showError('An error occurred during AI processing.');
                console.error('AI processing error:', error);
            } finally {
                button.innerHTML = originalHtml;
                button.disabled = false;
            }
        });
    }

    openCostModal(category, itemId = null) {
        this.currentEditCostItem = { category, id: itemId };
        const modal = document.getElementById('costItemModal');
        const modalTitle = document.getElementById('costItemModalTitle');
        const costNameField = document.getElementById('costName');
        const costValueField = document.getElementById('costValue');
        modalTitle.textContent = itemId ? `Edit ${category} Item` : `Add ${category} Item`;
        if (itemId && this.costBreakdown[category] && this.costBreakdown[category][itemId]) {
            const item = this.costBreakdown[category][itemId];
            costNameField.value = item.name;
            costValueField.value = item.cost;
        } else {
            costNameField.value = '';
            costValueField.value = '';
        }
        modal.classList.remove('hidden');
    }

    closeCostModal() {
        const modal = document.getElementById('costItemModal');
        modal.classList.add('hidden');
        this.currentEditCostItem = null;
    }

    async saveCostItem() {
        if (!this.currentEditCostItem) return;
        const { category, id } = this.currentEditCostItem;
        const name = document.getElementById('costName').value.trim();
        const cost = parseFloat(document.getElementById('costValue').value);
        if (!name || isNaN(cost)) {
            this.showError('Please enter a valid name and cost.');
            return;
        }
        const itemId = id || `${category}_${Date.now()}`;
        const itemData = { name, cost };
        if (!this.costBreakdown[category]) this.costBreakdown[category] = {};
        this.costBreakdown[category][itemId] = itemData;
        this.renderCostList(category);
        this.updateTotalsDisplay();
        this.closeCostModal();
        try {
            const response = await fetch('/functions/process_cost_breakdown.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: id ? 'update' : 'add', inventory_id: this.currentItemSku, category, item_id: itemId, data: itemData })
            });
            const result = await response.json();
            if (!result.success) {
                this.showError('Failed to save item. Reverting changes.');
                delete this.costBreakdown[category][itemId];
                this.renderCostList(category);
                this.updateTotalsDisplay();
            }
        } catch (error) {
            this.showError('An error occurred while saving.');
            console.error('Save cost item error:', error);
        }
    }

    confirmDeleteCostItem(category, itemId) {
        this.showConfirmationModal('Delete Cost Item', 'Are you sure you want to delete this item?', () => this.deleteCostItem(category, itemId));
    }

    async deleteCostItem(category, itemId) {
        if (this.costBreakdown[category] && this.costBreakdown[category][itemId]) {
            delete this.costBreakdown[category][itemId];
            this.renderCostList(category);
            this.updateTotalsDisplay();
        }
        try {
            const response = await fetch('/functions/process_cost_breakdown.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', inventory_id: this.currentItemSku, category, item_id: itemId })
            });
            const result = await response.json();
            if (!result.success) {
                this.showError('Failed to delete item from server.');
            }
        } catch (error) {
            this.showError('An error occurred while deleting.');
            console.error('Delete cost item error:', error);
        }
    }

    renderCostList(category) {
        const listElement = document.getElementById(`${category}List`);
        if (!listElement) return;
        listElement.innerHTML = '';
        const items = this.costBreakdown[category];
        const itemKeys = Object.keys(items || {});
        if (itemKeys.length === 0) {
            listElement.innerHTML = '<p class="text-gray-500 text-xs italic">No items added yet.</p>';
            return;
        }
        itemKeys.forEach(id => {
            const item = items[id];
            const itemRow = document.createElement('div');
            itemRow.className = 'cost-item-row flex justify-between items-center p-2 rounded hover:bg-gray-100';
            itemRow.innerHTML = `
                <span>${this.escapeHtml(item.name)}</span>
                <div class="flex items-center">
                    <span class="mr-4 font-medium">$${parseFloat(item.cost).toFixed(2)}</span>
                    <button data-action="open-cost-modal" data-category="${category}" data-id="${id}" class="text-blue-500 hover:text-blue-700 mr-2">Edit</button>
                    <button data-action="delete-cost-item" data-category="${category}" data-id="${id}" class="text-red-500 hover:text-red-700">Delete</button>
                </div>
            `;
            listElement.appendChild(itemRow);
        });
    }

    updateTotalsDisplay() {
        let materialTotal = 0, laborTotal = 0, energyTotal = 0, equipmentTotal = 0;
        for (const id in this.costBreakdown.materials) materialTotal += parseFloat(this.costBreakdown.materials[id].cost);
        for (const id in this.costBreakdown.labor) laborTotal += parseFloat(this.costBreakdown.labor[id].cost);
        for (const id in this.costBreakdown.energy) energyTotal += parseFloat(this.costBreakdown.energy[id].cost);
        for (const id in this.costBreakdown.equipment) equipmentTotal += parseFloat(this.costBreakdown.equipment[id].cost);
        const totalCost = materialTotal + laborTotal + energyTotal + equipmentTotal;
        this.costBreakdown.totals = { materialTotal, laborTotal, energyTotal, equipmentTotal, totalCost };
        document.getElementById('materialTotal').textContent = `$${materialTotal.toFixed(2)}`;
        document.getElementById('laborTotal').textContent = `$${laborTotal.toFixed(2)}`;
        document.getElementById('energyTotal').textContent = `$${energyTotal.toFixed(2)}`;
        document.getElementById('equipmentTotal').textContent = `$${equipmentTotal.toFixed(2)}`;
        document.getElementById('totalCost').textContent = `$${totalCost.toFixed(2)}`;
        const suggestedCostDisplay = document.getElementById('suggestedCostDisplay');
        if(suggestedCostDisplay) {
            suggestedCostDisplay.textContent = `$${totalCost.toFixed(2)}`;
        }
    }

    confirmClearCostBreakdown() {
        this.showConfirmationModal('Clear Cost Breakdown', 'Are you sure you want to delete all cost items? This is irreversible.', () => this.clearCostBreakdownCompletely());
    }

    async clearCostBreakdownCompletely() {
        this.costBreakdown = { materials: {}, labor: {}, energy: {}, equipment: {}, totals: {} };
        ['materials', 'labor', 'energy', 'equipment'].forEach(cat => this.renderCostList(cat));
        this.updateTotalsDisplay();
        try {
            const response = await fetch('/functions/process_cost_breakdown.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clear_all', inventory_id: this.currentItemSku })
            });
            const result = await response.json();
            if (result.success) {
                this.showSuccess('Cost breakdown cleared.');
            } else {
                this.showError('Failed to clear breakdown on server.');
            }
        } catch (error) {
            this.showError('An error occurred while clearing the breakdown.');
            console.error('Clear breakdown error:', error);
        }
    }

    async getCostSuggestion() {
        const button = document.querySelector('[data-action="get-cost-suggestion"]');
        const originalHtml = button.innerHTML;
        button.innerHTML = 'Analyzing...';
        button.disabled = true;
        try {
            const response = await fetch(`/api/suggest_cost.php?sku=${this.currentItemSku}`);
            const data = await response.json();
            if (data.success) {
                this.showCostSuggestionChoiceDialog(data.suggestions);
            } else {
                this.showError(data.error || 'Failed to get cost suggestion.');
            }
        } catch (error) {
            this.showError('Failed to connect to cost suggestion service.');
            console.error('Get cost suggestion error:', error);
        } finally {
            button.innerHTML = originalHtml;
            button.disabled = false;
        }
    }

    async getPriceSuggestion() {
        const button = document.querySelector('[data-action="get-price-suggestion"]');
        const originalHtml = button.innerHTML;
        button.innerHTML = 'Analyzing...';
        button.disabled = true;
        const itemData = {
            name: document.getElementById('name').value,
            description: document.getElementById('description').value,
            category: document.getElementById('category').value,
            costPrice: document.getElementById('costPrice').value,
            sku: this.currentItemSku,
            useImages: true
        };
        try {
            const response = await fetch('/api/suggest_price.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(itemData)
            });
            const data = await response.json();
            if (data.success) {
                this.displayPriceSuggestion(data);
                this.showSuccess('Price suggestion generated!');
            } else {
                this.showError(data.error || 'Failed to get price suggestion.');
            }
        } catch (error) {
            this.showError('Failed to connect to pricing service.');
            console.error('Get price suggestion error:', error);
        } finally {
            button.innerHTML = originalHtml;
            button.disabled = false;
        }
    }

    displayPriceSuggestion(data) {
        const display = document.getElementById('priceSuggestionDisplay');
        const placeholder = document.getElementById('priceSuggestionPlaceholder');
        if (!display || !placeholder) return;
        placeholder.classList.add('hidden');
        display.classList.remove('hidden');
        document.getElementById('displaySuggestedPrice').textContent = `$${parseFloat(data.suggestedPrice).toFixed(2)}`;
        document.getElementById('displayConfidence').textContent = `${data.confidence || 'N/A'}`;
        document.getElementById('displayTimestamp').textContent = new Date(data.createdAt).toLocaleString();
        display.dataset.suggestedPrice = data.suggestedPrice;
        const reasoningList = document.getElementById('reasoningList');
        reasoningList.innerHTML = '';
        if (data.reasoning) {
            const items = data.reasoning.split('•').filter(s => s.trim());
            items.forEach(itemText => {
                const li = document.createElement('li');
                li.textContent = itemText.trim();
                reasoningList.appendChild(li);
            });
        }
    }

    applyPriceSuggestion() {
        const display = document.getElementById('priceSuggestionDisplay');
        const retailPriceField = document.getElementById('retailPrice');
        const suggestedPrice = display.dataset.suggestedPrice;
        if (suggestedPrice) {
            retailPriceField.value = parseFloat(suggestedPrice).toFixed(2);
            retailPriceField.style.backgroundColor = '#dcfce7';
            setTimeout(() => { retailPriceField.style.backgroundColor = ''; }, 2000);
            this.showSuccess('Suggested price applied!');
        }
    }

    clearPriceSuggestion() {
        const display = document.getElementById('priceSuggestionDisplay');
        const placeholder = document.getElementById('priceSuggestionPlaceholder');
        display.classList.add('hidden');
        placeholder.classList.remove('hidden');
        display.dataset.suggestedPrice = '';
    }

    async loadExistingPriceSuggestion(sku) {
        if (!sku) return;
        try {
            const response = await fetch(`/api/get_price_suggestion.php?sku=${sku}`);
            const data = await response.json();
            if (data.success && data.suggestedPrice) {
                this.displayPriceSuggestion(data);
            }
        } catch (error) {
            console.error('Error loading existing price suggestion:', error);
        }
    }
    
    async loadExistingMarketingSuggestion(sku) {
        if (!sku) return;
        try {
            const response = await fetch(`/api/get_marketing_suggestion.php?sku=${sku}`);
            const data = await response.json();
            if (data.success && data.exists) {
                const marketingButton = document.querySelector('[data-action="generate-marketing-copy"]');
                if (!marketingButton) return;
                const indicator = document.createElement('span');
                indicator.className = 'suggestion-indicator ml-2 px-2 py-1 bg-purple-100 text-purple-700 text-xs rounded-full';
                indicator.textContent = '💾 Previous';
                indicator.title = `Previous AI analysis available`;
                marketingButton.appendChild(indicator);
            }
        } catch (error) {
            console.error('Error loading existing marketing suggestion:', error);
        }
    }

    async generateMarketingCopy() {
        this.showError('generateMarketingCopy not fully implemented yet.');
    }

    showConfirmationModal(title, message, onConfirm) {
        const modal = document.getElementById('confirmationModal');
        document.getElementById('confirmationModalTitle').textContent = title;
        document.getElementById('confirmationModalMessage').textContent = message;
        const confirmBtn = document.getElementById('confirmationModalConfirm');
        const cancelBtn = document.getElementById('confirmationModalCancel');
        const confirmAndClose = () => {
            this.closeConfirmationModal();
            onConfirm();
        };
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        newConfirmBtn.addEventListener('click', confirmAndClose);
        const newCancelBtn = cancelBtn.cloneNode(true);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        newCancelBtn.addEventListener('click', this.closeConfirmationModal.bind(this));
        modal.classList.remove('hidden');
    }

    closeConfirmationModal() {
        const modal = document.getElementById('confirmationModal');
        modal.classList.add('hidden');
    }

    showCostSuggestionChoiceDialog(suggestions) {
        const dialog = document.getElementById('costSuggestionChoiceDialog');
        const container = document.getElementById('costSuggestionChoices');
        container.innerHTML = '';
        if (!suggestions || suggestions.length === 0) {
            container.innerHTML = '<p>No suggestions available.</p>';
        } else {
            suggestions.forEach(suggestion => {
                const suggestionCard = document.createElement('div');
                suggestionCard.className = 'suggestion-card p-4 border rounded-lg hover:bg-gray-50';
                suggestionCard.innerHTML = `
                    <h4 class="font-bold text-lg">${this.escapeHtml(suggestion.model)}</h4>
                    <p class="text-2xl font-light my-2">$${parseFloat(suggestion.suggestedCost).toFixed(2)}</p>
                    <p class="text-sm text-gray-600">Confidence: ${this.escapeHtml(suggestion.confidence)}</p>
                    <p class="text-xs italic text-gray-500 mt-2">${this.escapeHtml(suggestion.reasoning)}</p>
                    <div class="mt-4 flex gap-2">
                        <button data-action="populate-cost-breakdown-from-suggestion" data-suggestion='${this.escapeHtml(JSON.stringify(suggestion))}' class="btn btn-primary flex-1">Apply Breakdown</button>
                        <button data-action="apply-suggested-cost-to-cost-field" data-suggestion='${this.escapeHtml(JSON.stringify(suggestion))}' class="btn btn-secondary flex-1">Apply to Field</button>
                    </div>
                `;
                container.appendChild(suggestionCard);
            });
        }
        dialog.classList.remove('hidden');
    }

    closeCostSuggestionChoiceDialog() {
        const dialog = document.getElementById('costSuggestionChoiceDialog');
        dialog.classList.add('hidden');
    }
    
    applyCostSuggestionToCost() {
        const suggestedCostDisplay = document.getElementById('suggestedCostDisplay');
        const costPriceField = document.getElementById('costPrice');
        if (suggestedCostDisplay && costPriceField) {
            const suggestedCostText = suggestedCostDisplay.textContent.replace('$', '');
            const suggestedCostValue = parseFloat(suggestedCostText) || 0;
            if (suggestedCostValue > 0) {
                costPriceField.value = suggestedCostValue.toFixed(2);
                costPriceField.style.backgroundColor = '#dbeafe';
                setTimeout(() => { costPriceField.style.backgroundColor = ''; }, 2000);
                this.showSuccess('Suggested cost applied to Cost Price field!');
            } else {
                this.showError('No suggested cost available.');
            }
        }
    }

    applySuggestedCostToCostField(button) {
        try {
            const suggestionData = JSON.parse(button.getAttribute('data-suggestion').replace(/&quot;/g, '"').replace(/&#39;/g, "'"));
            const costPriceField = document.getElementById('costPrice');
            if (costPriceField) {
                const suggestedCost = parseFloat(suggestionData.suggestedCost) || 0;
                costPriceField.value = suggestedCost.toFixed(2);
                costPriceField.style.backgroundColor = '#dcfce7';
                setTimeout(() => { costPriceField.style.backgroundColor = ''; }, 3000);
                this.closeCostSuggestionChoiceDialog();
                this.showSuccess(`AI suggested cost of $${suggestedCost.toFixed(2)} applied!`);
            }
        } catch (error) {
            console.error('Error applying suggested cost:', error);
            this.showError('Error applying suggested cost.');
        }
    }
    
    async populateCostBreakdownFromSuggestion(suggestionData) {
        await this.clearCostBreakdownCompletely();
        const breakdown = suggestionData.breakdown;
        const categories = ['materials', 'labor', 'energy', 'equipment'];
        for (const category of categories) {
            if (breakdown[category] > 0) {
                const itemId = `${category}_${Date.now()}`;
                const itemData = { name: `Suggested ${category}`, cost: breakdown[category] };
                this.costBreakdown[category][itemId] = itemData;
                await this.saveCostItemToDatabase(category, itemData, itemId);
            }
        }
        this.init();
        this.showSuccess('AI cost breakdown has been applied and saved.');
    }
    
    async saveCostItemToDatabase(category, data, itemId) {
         try {
            const response = await fetch('/functions/process_cost_breakdown.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add', inventory_id: this.currentItemSku, category, item_id: itemId, data })
            });
            const result = await response.json();
            if (!result.success) {
                this.showError(`Failed to save ${category} item.`);
            }
        } catch (error) {
            this.showError(`An error occurred while saving ${category}.`);
        }
    }

    async refreshCategoryDropdown() {
        try {
            const response = await fetch('/api/get_categories.php');
            const newCategories = await response.json();
            this.categories = newCategories;
            this.updateCategoryDropdown();
            this.showSuccess('Categories updated!');
        } catch (error) {
            this.showError('Failed to refresh categories.');
            console.error('Refresh categories error:', error);
        }
    }

    updateCategoryDropdown() {
        const dropdown = document.getElementById('category');
        if (!dropdown) return;
        const currentVal = dropdown.value;
        dropdown.innerHTML = '';
        this.categories.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat.id;
            option.textContent = cat.name;
            dropdown.appendChild(option);
        });
        dropdown.value = currentVal;
    }

    escapeHtml(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500' };
        toast.className = `toast ${colors[type]} text-white p-4 rounded-lg shadow-lg mb-2`;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 500);
        }, 3000);
    }

    showSuccess(message) {
        this.showToast(message, 'success');
    }

    showError(message) {
        this.showToast(message, 'error');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('inventory-data') && document.getElementById('admin-inventory-container')) {
        new AdminInventoryModule();
    }
});
