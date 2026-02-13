import { useState, useEffect, useCallback, useMemo } from 'react';
import { useModalContext } from '../../context/ModalContext.js';
import { useGlobalEntities, ISizeTemplate, IColorTemplate } from './useGlobalEntities.js';
import { useInventoryOptionLinks } from './useInventoryOptionLinks.js';
import { useMaterials } from './useMaterials.js';
import { useCategoryList } from './useCategoryList.js';
import { useOptionCascadeConfigs } from './useOptionCascadeConfigs.js';
import type { InventoryOptionType, InventoryOptionAppliesToType } from '../../types/inventoryOptions.js';

export type { ISizeTemplate, IColorTemplate };

export type TabId = 'assignments' | 'cascade' | 'colors' | 'genders' | 'global-colors' | 'global-sizes' | 'materials' | 'sizes';

export const useAttributesManager = () => {
    const {
        colors,
        sizes,
        genders,
        sizeTemplates,
        colorTemplates,
        isLoading,
        error,
        fetchAll,
        fetchSizeTemplate,
        fetchColorTemplate,
        deleteGender,
        deleteSizeTemplate,
        deleteColorTemplate,
        saveGender,
        saveColor,
        deleteColor,
        saveSize,
        deleteSize,
        saveSizeTemplate,
        saveColorTemplate,
        duplicateSizeTemplate,
        duplicateColorTemplate
    } = useGlobalEntities();

    const linksApi = useInventoryOptionLinks();
    const materialsApi = useMaterials();
    const categoriesApi = useCategoryList();
    const cascadeApi = useOptionCascadeConfigs();

    // Default to Assignments (the entry point for most workflows).
    const [activeTab, setActiveTab] = useState<TabId>('assignments');
    const [editingSize, setEditingSize] = useState<ISizeTemplate | null>(null);
    const [localSize, setLocalSize] = useState<ISizeTemplate | null>(null);
    const [editingColor, setEditingColor] = useState<IColorTemplate | null>(null);
    const [localColor, setLocalColor] = useState<IColorTemplate | null>(null);
    const [isRedesignOpen, setIsRedesignOpen] = useState(false);

    useEffect(() => {
        if (isLoading) return;
        fetchAll();
    }, [fetchAll, isLoading]);

    const { confirm: themedConfirm, prompt: themedPrompt } = useModalContext();

    const handleDuplicateSize = useCallback(async (id: number) => {
        const success = await duplicateSizeTemplate(id);
        if (success && window.WFToast) window.WFToast.success('Size template duplicated');
    }, [duplicateSizeTemplate]);

    const handleDuplicateColor = useCallback(async (id: number) => {
        const success = await duplicateColorTemplate(id);
        if (success && window.WFToast) window.WFToast.success('Color template duplicated');
    }, [duplicateColorTemplate]);

    const handleDeleteGender = useCallback(async (id: number, name: string) => {
        const confirmed = await themedConfirm({
            title: 'Delete Gender',
            message: `Delete gender "${name}" everywhere? This cannot be undone.`,
            confirmText: 'Delete Now',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const success = await deleteGender(id);
            if (success && window.WFToast) window.WFToast.success('Gender deleted');
        }
    }, [themedConfirm, deleteGender]);

    const handleDeleteSizeTemplate = useCallback(async (id: number, name: string) => {
        const confirmed = await themedConfirm({
            title: 'Delete Size Template',
            message: `Delete size template "${name}"?`,
            confirmText: 'Delete Now',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const success = await deleteSizeTemplate(id);
            if (success && window.WFToast) window.WFToast.success('Size template deleted');
        }
    }, [themedConfirm, deleteSizeTemplate]);

    const handleDeleteColorTemplate = useCallback(async (id: number, name: string) => {
        const confirmed = await themedConfirm({
            title: 'Delete Color Template',
            message: `Delete color template "${name}"?`,
            confirmText: 'Delete Now',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const success = await deleteColorTemplate(id);
            if (success && window.WFToast) window.WFToast.success('Color template deleted');
        }
    }, [themedConfirm, deleteColorTemplate]);

    const handleDeleteGlobalColor = useCallback(async (id: number, name: string) => {
        const confirmed = await themedConfirm({
            title: 'Delete Global Color',
            message: `Delete global color "${name}"?`,
            confirmText: 'Delete Now',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const success = await deleteColor(id);
            if (success && window.WFToast) window.WFToast.success('Global color deleted');
        }
    }, [themedConfirm, deleteColor]);

    const handleDeleteGlobalSize = useCallback(async (id: number, name: string) => {
        const confirmed = await themedConfirm({
            title: 'Delete Global Size',
            message: `Delete global size "${name}"?`,
            confirmText: 'Delete Now',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const success = await deleteSize(id);
            if (success && window.WFToast) window.WFToast.success('Global size deleted');
        }
    }, [themedConfirm, deleteSize]);

    const handleAddGlobalColor = useCallback(async () => {
        const name = await themedPrompt({
            title: 'Add Global Color',
            message: 'Enter new color name:',
            confirmText: 'Next',
            icon: '🎨'
        });
        if (!name) return;

        const code = await themedPrompt({
            title: 'Set Color Hex',
            message: `Hex code for "${name}":`,
            input: { defaultValue: '#000000' },
            confirmText: 'Add Color',
            icon: '🎨'
        });

        if (name && name.trim()) {
            const res = await saveColor({ color_name: name.trim(), color_code: code || '#000000' });
            if (res?.success && window.WFToast) window.WFToast.success('Global color added');
        }
    }, [themedPrompt, saveColor]);

    const handleAddGlobalSize = useCallback(async () => {
        const name = await themedPrompt({
            title: 'Add Global Size',
            message: 'Enter new size name (e.g. Large):',
            confirmText: 'Next',
            icon: '📏'
        });
        if (!name) return;

        const code = await themedPrompt({
            title: 'Set Size Code',
            message: `Code for "${name}" (e.g. L):`,
            confirmText: 'Add Size',
            icon: '📏'
        });

        if (name && name.trim() && code) {
            const res = await saveSize({ size_name: name.trim(), size_code: code.trim() });
            if (res?.success && window.WFToast) window.WFToast.success('Global size added');
        }
    }, [themedPrompt, saveSize]);

    const handleAddGender = useCallback(async () => {
        const name = await themedPrompt({
            title: 'Add Gender',
            message: 'Enter new gender name:',
            confirmText: 'Add Now',
            icon: '🚻'
        });
        if (name && name.trim()) {
            const res = await saveGender({ gender_name: name.trim() });
            if (res?.success && window.WFToast) window.WFToast.success('Gender added');
        }
    }, [themedPrompt, saveGender]);

    const handleEditSize = useCallback(async (id: number) => {
        const template = await fetchSizeTemplate(id);
        if (template) setEditingSize(template);
    }, [fetchSizeTemplate]);

    const handleEditColor = useCallback(async (id: number) => {
        const template = await fetchColorTemplate(id);
        if (template) setEditingColor(template);
    }, [fetchColorTemplate]);

    const handleSaveTemplate = useCallback(async (): Promise<boolean> => {
        if (localSize) {
            const res = await saveSizeTemplate(localSize);
            if (res?.success) {
                setEditingSize(null);
                setLocalSize(null);
                if (window.WFToast) window.WFToast.success('Size template saved');
                return true;
            } else if (window.WFToast) {
                window.WFToast.error(res?.message || 'Failed to save size template');
                return false;
            }
        } else if (localColor) {
            const res = await saveColorTemplate(localColor);
            if (res?.success) {
                setEditingColor(null);
                setLocalColor(null);
                if (window.WFToast) window.WFToast.success('Color template saved');
                return true;
            } else if (window.WFToast) {
                window.WFToast.error(res?.message || 'Failed to save color template');
                return false;
            }
        }
        return false;
    }, [localSize, localColor, saveSizeTemplate, saveColorTemplate]);

    const isDirty = useMemo(() => {
        if (localSize && editingSize) return JSON.stringify(localSize) !== JSON.stringify(editingSize);
        if (localColor && editingColor) return JSON.stringify(localColor) !== JSON.stringify(editingColor);
        return false;
    }, [localSize, editingSize, localColor, editingColor]);

    const handleOpenSizeColorRedesign = useCallback(() => {
        setIsRedesignOpen(true);
    }, []);

    const addLink = useCallback(async (payload: {
        option_type: InventoryOptionType;
        option_id: number;
        applies_to_type: InventoryOptionAppliesToType;
        category_id?: number | null;
        item_sku?: string | null;
    }) => {
        return await linksApi.addLink(payload);
    }, [linksApi]);

    const deleteLink = useCallback(async (payload: { id: number }) => {
        return await linksApi.deleteLink(payload);
    }, [linksApi]);

    const clearOptionLinks = useCallback(async (payload: { option_type: InventoryOptionType; option_id: number }) => {
        return await linksApi.clearOptionLinks(payload);
    }, [linksApi]);

    return {
        colors,
        sizes,
        genders,
        sizeTemplates,
        colorTemplates,
        cascadeConfigs: cascadeApi.configs,
        materials: materialsApi.materials,
        optionLinks: linksApi.links,
        categories: categoriesApi.categories,
        isLoading: isLoading || linksApi.isLoading || materialsApi.isLoading || categoriesApi.isLoading || cascadeApi.isLoading,
        error: error || linksApi.error || materialsApi.error || categoriesApi.error || cascadeApi.error,
        activeTab,
        setActiveTab,
        editingSize,
        setEditingSize,
        localSize,
        setLocalSize,
        editingColor,
        setEditingColor,
        localColor,
        setLocalColor,
        isRedesignOpen,
        setIsRedesignOpen,
        isDirty,
        fetchAll,
        handleDuplicateSize,
        handleDuplicateColor,
        handleDeleteGender,
        handleDeleteSizeTemplate,
        handleDeleteColorTemplate,
        handleDeleteGlobalColor,
        handleDeleteGlobalSize,
        handleAddGlobalColor,
        handleAddGlobalSize,
        handleAddGender,
        handleEditSize,
        handleEditColor,
        handleOpenSizeColorRedesign,
        handleSaveTemplate,
        addLink,
        deleteLink,
        clearOptionLinks,
        materialsApi,
        cascadeApi,
        themedPrompt,
        themedConfirm
    };
};
