import { useState, useCallback } from 'react';
import { IRoomVisualsHook } from '../../../types/room.js';
import { resolveBackgroundAssetUrl } from '../../../utils/background-url.js';

export const useRoomVisuals = (): IRoomVisualsHook => {
    const [preview_image, setPreviewImage] = useState<IRoomVisualsHook['preview_image']>(null);

    const getImageUrl = useCallback((bg: { webp_filename?: string; image_filename?: string }) => {
        if (!bg) return '';
        return resolveBackgroundAssetUrl(bg.webp_filename || bg.image_filename || '');
    }, []);

    return {
        preview_image,
        setPreviewImage,
        getImageUrl
    };
};
