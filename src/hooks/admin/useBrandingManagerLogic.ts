import { useState, useEffect, useCallback } from 'react';
import { IBrandingTokens, IPaletteColor } from './useBranding.js';
import { useModalContext } from '../../context/ModalContext.js';
import { isDraftDirty } from '../../core/utils.js';
import { IFontMeta } from './useFontPicker.js';

interface UseBrandingManagerLogicProps {
    tokens: IBrandingTokens | null;
    isLoading: boolean;
    saveTokens: (tokens: Partial<IBrandingTokens>) => Promise<boolean>;
    createBackup: () => Promise<boolean>;
    resetToDefaults: () => Promise<boolean>;
    fetchTokens: () => void;
}

export const useBrandingManagerLogic = ({
    tokens,
    isLoading,
    saveTokens,
    createBackup,
    resetToDefaults,
    fetchTokens
}: UseBrandingManagerLogicProps) => {
    const [editTokens, setEditTokens] = useState<Partial<IBrandingTokens>>({});
    const [activeTab, setActiveTab] = useState<'colors' | 'fonts' | 'layout'>('colors');
    const [fontPickerTarget, setFontPickerTarget] = useState<'primary' | 'secondary' | 'title-primary' | 'title-secondary' | null>(null);

    useEffect(() => {
        if (isLoading) return;
        if (tokens) {
            setEditTokens(tokens);
        }
    }, [tokens, isLoading]);

    const handleSave = useCallback(async (): Promise<boolean> => {
        const success = await saveTokens(editTokens);
        if (success) {
            if (window.WFToast) window.WFToast.success('Branding updated successfully!');
            return true;
        } else {
            if (window.WFToast) window.WFToast.error('Failed to update branding');
            return false;
        }
    }, [editTokens, saveTokens]);

    const { confirm: themedConfirm, prompt: themedPrompt } = useModalContext();

    const handleBackup = useCallback(async () => {
        const confirmed = await themedConfirm({
            title: 'Create Backup',
            message: 'Create a new backup? This will replace any existing backup.',
            confirmText: 'Backup Now',
            iconKey: 'warning'
        });

        if (confirmed) {
            const success = await createBackup();
            if (success) {
                if (window.WFToast) window.WFToast.success('Branding backup created!');
            } else {
                if (window.WFToast) window.WFToast.error('Failed to create backup');
            }
        }
    }, [themedConfirm, createBackup]);

    const handleReset = useCallback(async () => {
        const confirmed = await themedConfirm({
            title: 'Reset Defaults',
            message: 'Reset ALL branding to factory defaults? This cannot be undone unless you have a backup.',
            confirmText: 'Reset Now',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const success = await resetToDefaults();
            if (success) {
                if (window.WFToast) window.WFToast.success('Branding reset to defaults');
            } else {
                if (window.WFToast) window.WFToast.error('Failed to reset branding');
            }
        }
    }, [themedConfirm, resetToDefaults]);

    const handleChange = useCallback((key: keyof IBrandingTokens, value: string | IPaletteColor[]) => {
        setEditTokens(prev => ({ ...prev, [key]: value }));
    }, []);

    const handleAddPaletteColor = useCallback(async () => {
        const name = await themedPrompt({
            title: 'Add Palette Color',
            message: 'Color name (e.g. Grass Green):',
            confirmText: 'Next',
            icon: '🎨'
        });
        if (!name) return;

        const hex = await themedPrompt({
            title: 'Set Color Hex',
            message: `Hex code for "${name}":`,
            input: { defaultValue: '#87ac3a' },
            confirmText: 'Add Color',
            icon: '🎨'
        });
        if (!hex) return;

        const current = Array.isArray(editTokens.business_brand_palette) ? editTokens.business_brand_palette : [];
        handleChange('business_brand_palette', [...current, { name, hex }]);
    }, [themedPrompt, editTokens.business_brand_palette, handleChange]);

    const handleRemovePaletteColor = useCallback((index: number) => {
        const current = Array.isArray(editTokens.business_brand_palette) ? editTokens.business_brand_palette : [];
        const next = [...current];
        next.splice(index, 1);
        handleChange('business_brand_palette', next);
    }, [editTokens.business_brand_palette, handleChange]);

    const handleFontSelect = useCallback((font: IFontMeta) => {
        if (fontPickerTarget === 'primary') {
            handleChange('business_brand_font_primary', font.stack);
        } else if (fontPickerTarget === 'secondary') {
            handleChange('business_brand_font_secondary', font.stack);
        } else if (fontPickerTarget === 'title-primary') {
            handleChange('business_brand_font_title_primary', font.stack);
        } else if (fontPickerTarget === 'title-secondary') {
            handleChange('business_brand_font_title_secondary', font.stack);
        }
        setFontPickerTarget(null);
    }, [fontPickerTarget, handleChange]);

    const isDirty = isDraftDirty(editTokens, tokens);

    return {
        editTokens,
        activeTab,
        setActiveTab,
        fontPickerTarget,
        setFontPickerTarget,
        handleSave,
        handleBackup,
        handleReset,
        handleAddPaletteColor,
        handleRemovePaletteColor,
        handleFontSelect,
        handleChange,
        isDirty
    };
};
