import { useState, useCallback, useMemo } from 'react';
import { IAreaMapping } from '../../../types/admin.js';
import { IAreaMappingsHook, IRoomShortcutsHook } from '../../../types/room.js';
import { useAICostEstimateConfirm } from '../useAICostEstimateConfirm.js';

export const useRoomShortcuts = (selectedRoom: string, mappings: IAreaMappingsHook): IRoomShortcutsHook => {
    const { confirmWithEstimate } = useAICostEstimateConfirm();
    const [newMapping, setNewMapping] = useState<Partial<IAreaMapping>>({
        mapping_type: 'item',
        area_selector: ''
    });
    const [isGeneratingImage, setIsGeneratingImage] = useState(false);

    const handleContentSave = useCallback(async (e?: React.FormEvent) => {
        e?.preventDefault();
        if (!selectedRoom) return;
        const success = await mappings.saveMapping({ ...newMapping, room_number: selectedRoom });
        if (success) {
            setNewMapping({ mapping_type: 'item', area_selector: '' });
            if (window.WFToast) window.WFToast.success('Mapping saved');
        }
    }, [selectedRoom, newMapping, mappings]);

    const handleContentConvert = useCallback(async (area: string, sku: string) => {
        if (!selectedRoom) return;
        await mappings.saveMapping({
            room_number: selectedRoom,
            area_selector: area,
            mapping_type: 'item',
            item_sku: sku
        });
    }, [selectedRoom, mappings]);

    const handleToggleMappingActive = useCallback(async (id: number, currentActive: boolean | number) => {
        if (!selectedRoom) return;
        const success = await mappings.toggleMappingActive(selectedRoom, id, currentActive);
        if (success && window.WFToast) {
            const wasActive = currentActive === true || currentActive === 1;
            window.WFToast.success(wasActive ? 'Shortcut deactivated' : 'Shortcut activated');
        }
    }, [selectedRoom, mappings]);

    const handleContentUpload = useCallback(async (e: React.ChangeEvent<HTMLInputElement>, field: 'content_image' | 'link_image') => {
        const file = e.target.files?.[0];
        if (!file || !selectedRoom) return;
        const imageUrl = await mappings.uploadImage(file);
        if (imageUrl) {
            setNewMapping((prev: Partial<IAreaMapping>) => ({ ...prev, [field === 'content_image' ? 'content_image' : 'link_image']: imageUrl }));
        }
    }, [selectedRoom, mappings]);

    const handleContentEdit = useCallback((mapping: IAreaMapping) => {
        setNewMapping(mapping);
    }, []);

    const handleGenerateContentImage = useCallback(async () => {
        if (!selectedRoom) {
            window.WFToast?.error?.('Select a room first');
            return;
        }

        const contentTarget = String(newMapping.content_target || '').trim();
        if (contentTarget === '') {
            window.WFToast?.error?.('Select a destination first');
            return;
        }

        const confirmed = await confirmWithEstimate({
            action_key: 'shortcut_generate_sign_image',
            action_label: 'Generate shortcut sign image with AI',
            operations: [
                { key: 'room_image_generation', label: 'Shortcut sign image generation', image_generations: 1 }
            ],
            context: {
                prompt_length: (String(newMapping.link_label || '').trim().length || 24)
            },
            confirmText: 'Generate Image'
        });
        if (!confirmed) return;

        try {
            setIsGeneratingImage(true);
            window.WFToast?.info?.('Generating shortcut sign image...');
            const generated = await mappings.generateShortcutImage({
                room_number: selectedRoom,
                content_target: contentTarget,
                link_label: String(newMapping.link_label || '').trim()
            });
            if (!generated?.image_url) {
                throw new Error('AI did not return a generated image');
            }

            setNewMapping((prev) => ({
                ...prev,
                content_image: generated.image_url,
                link_image: generated.image_url
            }));
            window.WFToast?.success?.('Shortcut sign image generated');
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to generate shortcut sign image';
            window.WFToast?.error?.(message);
        } finally {
            setIsGeneratingImage(false);
        }
    }, [confirmWithEstimate, mappings, newMapping.content_target, newMapping.link_label, selectedRoom]);

    const isContentDirty = useMemo(() =>
        !!newMapping.area_selector || !!newMapping.item_sku || !!newMapping.category_id || !!newMapping.content_target
        , [newMapping]);

    return {
        newMapping,
        setNewMapping,
        handleContentSave,
        handleContentConvert,
        handleToggleMappingActive,
        handleContentUpload,
        handleGenerateContentImage,
        handleContentEdit,
        isGeneratingImage,
        isContentDirty
    };
};
