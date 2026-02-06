import { useState, useCallback, useMemo } from 'react';
import { IAreaMapping } from '../../../types/admin.js';
import { IAreaMappingsHook, IRoomShortcutsHook } from '../../../types/room.js';

export const useRoomShortcuts = (selectedRoom: string, mappings: IAreaMappingsHook): IRoomShortcutsHook => {
    const [newMapping, setNewMapping] = useState<Partial<IAreaMapping>>({
        mapping_type: 'item',
        area_selector: ''
    });

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

    const isContentDirty = useMemo(() =>
        !!newMapping.area_selector || !!newMapping.item_sku || !!newMapping.category_id || !!newMapping.content_target
        , [newMapping]);

    return {
        newMapping,
        setNewMapping,
        handleContentSave,
        handleContentConvert,
        handleContentUpload,
        handleContentEdit,
        isContentDirty
    };
};
