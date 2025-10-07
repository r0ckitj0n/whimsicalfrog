document.addEventListener('DOMContentLoaded', () => {
    const categoryTableBody = document.getElementById('categoryTableBody');
    const addCategoryForm = document.getElementById('addCategoryForm');

    // --- Helper Functions ---
    const showNotification = (message, type) => window.showGlobalNotification?.(message, type);
    const logError = (message, error) => window.Logger?.error(message, error);

    // --- Add Category ---
    if (addCategoryForm) {
        addCategoryForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const categoryInput = document.getElementById('newCategory');
            const category = categoryInput.value.trim();
            if (!category) return;

            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Adding...';
            submitBtn.disabled = true;

            try {
                const data = await window.ApiClient.request('/api/categories', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'create', category })
                });

                if (data.success) {
                    location.reload(); // Easiest way to show the new category and its data
                } else {
                    showNotification(data.error || 'Failed to add category', 'error');
                }
            } catch (err) {
                logError('Category add failed', err);
                showNotification('Server error occurred', 'error');
            } finally {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        });
    }

    // --- Event Delegation for Delete and Edit ---
    if (categoryTableBody) {
        categoryTableBody.addEventListener('click', (e) => {
            if (e.target.closest('.delete-category-btn')) {
                handleDeleteCategory(e.target.closest('.delete-category-btn'));
            } else if (e.target.closest('.editable-field') && !e.target.closest('.editing')) {
                startInlineEdit(e.target.closest('.editable-field'));
            }
        });
    }

    // --- Delete Category ---
    async function handleDeleteCategory(btn) {
        const category = btn.dataset.category;
        if (!confirm(`Delete category "${category}"?\n\nThis will remove the category from all products and update the SKU naming scheme.\nThis action cannot be undone.`)) return;

        const originalText = btn.innerHTML;
        btn.innerHTML = '⏳';
        btn.disabled = true;

        try {
            const data = await window.ApiClient.request('/api/categories', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', category })
            });

            if (data.success) {
                btn.closest('tr').remove();
                showNotification(`Category "${category}" deleted.`, 'success');
                localStorage.setItem('categoriesUpdated', Date.now().toString());
            } else {
                showNotification(data.error || 'Failed to delete category', 'error');
            }
        } catch (err) {
            logError('Category delete failed', err);
            showNotification('Server error occurred', 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    // --- Inline Editing ---
    function startInlineEdit(categoryDiv) {
        if (document.querySelector('.editing')) return; // Prevent multiple edits

        const originalName = categoryDiv.dataset.original;
        categoryDiv.classList.add('editing');
        categoryDiv.innerHTML = `<input type="text" class="form-input category-edit-input" value="${originalName}">`;
        const input = categoryDiv.querySelector('input');
        input.focus();
        input.select();

        const saveOrCancel = (event) => {
            if (event.type === 'blur' || event.key === 'Enter') {
                saveEdit(input, categoryDiv, originalName);
            } else if (event.key === 'Escape') {
                cancelEdit(categoryDiv, originalName);
            }
        };

        input.addEventListener('blur', saveOrCancel);
        input.addEventListener('keydown', saveOrCancel);
    }

    function cancelEdit(categoryDiv, originalName) {
        categoryDiv.innerHTML = originalName;
        categoryDiv.classList.remove('editing');
    }

    async function saveEdit(input, categoryDiv, originalName) {
        const newName = input.value.trim();
        
        // Clean up listeners
        input.replaceWith(document.createTextNode(newName)); 

        if (!newName || newName === originalName) {
            cancelEdit(categoryDiv, originalName);
            return;
        }

        categoryDiv.innerHTML = 'Saving...';

        try {
            const data = await window.ApiClient.request('/api/categories', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'rename', category: originalName, newCategory: newName })
            });

            if (data.success) {
                // Just reload the page to reflect all changes, including updated SKUs
                showNotification(`Category renamed to "${newName}".`, 'success');
                location.reload();
            } else {
                showNotification(data.error || 'Failed to rename category', 'error');
                cancelEdit(categoryDiv, originalName);
            }
        } catch (err) {
            logError('Category rename failed', err);
            showNotification('Server error occurred', 'error');
            cancelEdit(categoryDiv, originalName);
        }
    }
});
