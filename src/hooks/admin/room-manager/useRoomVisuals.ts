import { useState, useCallback } from 'react';
import { IRoomVisualsHook } from '../../../types/room.js';

export const useRoomVisuals = (): IRoomVisualsHook => {
    const [preview_image, setPreviewImage] = useState<IRoomVisualsHook['preview_image']>(null);

    const getImageUrl = useCallback((bg: { webp_filename?: string; image_filename?: string }) => {
        if (!bg) return '';
        let filename = bg.webp_filename || bg.image_filename;
        if (!filename) return '';
        if (filename.startsWith('http')) return filename;
        // Strip 'backgrounds/' prefix if present to avoid duplicate path segment
        if (filename.startsWith('backgrounds/')) {
            filename = filename.slice('backgrounds/'.length);
        }
        return `/images/backgrounds/${filename}`;
    }, []);

    return {
        preview_image,
        setPreviewImage,
        getImageUrl
    };
};
