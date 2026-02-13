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

    const validateMappingForSave = useCallback((mapping: Partial<IAreaMapping>): string | null => {
        const areaSelector = String(mapping.area_selector || '').trim();
        const mappingType = String(mapping.mapping_type || '').trim();
        const contentTarget = String(mapping.content_target || '').trim();

        if (areaSelector === '' || mappingType === '') {
            return 'Area Selector and Mapping Type are required';
        }

        if (['content', 'page', 'modal'].includes(mappingType) && contentTarget === '') {
            return 'Destination is required for Shortcut/Page/Modal mappings';
        }

        if (mappingType === 'link' && String(mapping.link_url || '').trim() === '') {
            return 'Destination URL is required for External Link mappings';
        }

        if (mappingType === 'category' && !mapping.category_id) {
            return 'Category is required for Category mappings';
        }

        if (mappingType === 'item' && String(mapping.item_sku || '').trim() === '') {
            return 'Item is required for Item mappings';
        }

        return null;
    }, []);

    const handleContentSave = useCallback(async (e?: React.FormEvent) => {
        e?.preventDefault();
        if (!selectedRoom) return;
        const validationError = validateMappingForSave(newMapping);
        if (validationError) {
            window.WFToast?.error?.(validationError);
            return;
        }

        const success = await mappings.saveMapping({ ...newMapping, room_number: selectedRoom });
        if (success) {
            setNewMapping({ mapping_type: 'item', area_selector: '' });
            if (window.WFToast) window.WFToast.success('Mapping saved');
        }
    }, [selectedRoom, newMapping, mappings, validateMappingForSave]);

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

    const handleContentEdit = useCallback(async (mapping: IAreaMapping) => {
        setNewMapping(mapping);
        const mappingId = Number(mapping.id || 0);
        if (!mappingId || !selectedRoom) return;
        try {
            const assets = await mappings.fetchShortcutSignAssets(mappingId, selectedRoom);
            setNewMapping(prev => ({
                ...prev,
                shortcut_images: assets
            }));
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to load shortcut sign images';
            window.WFToast?.error?.(message);
        }
    }, [mappings, selectedRoom]);

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
        const validationError = validateMappingForSave(newMapping);
        if (validationError) {
            window.WFToast?.error?.(validationError);
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
                link_label: String(newMapping.link_label || '').trim(),
                mapping_id: Number(newMapping.id || 0) || undefined
            });
            if (!generated?.image_url) {
                throw new Error('AI did not return a generated image');
            }

            const mappingToSave: Partial<IAreaMapping> = {
                ...newMapping,
                room_number: selectedRoom,
                content_image: generated.image_url,
                link_image: generated.image_url
            };
            const success = await mappings.saveMapping(mappingToSave);
            if (!success) {
                throw new Error('Sign was generated, but saving the mapping failed');
            }

            const mappingId = Number(mappingToSave.id || newMapping.id || 0);
            if (mappingId) {
                const assets = await mappings.fetchShortcutSignAssets(mappingId, selectedRoom);
                setNewMapping(prev => ({
                    ...prev,
                    shortcut_images: assets
                }));
            }

            setNewMapping({ mapping_type: 'item', area_selector: '' });
            window.WFToast?.success?.('Shortcut sign image generated and saved');
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to generate shortcut sign image';
            window.WFToast?.error?.(message);
        } finally {
            setIsGeneratingImage(false);
        }
    }, [confirmWithEstimate, mappings, newMapping, selectedRoom, validateMappingForSave]);

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
