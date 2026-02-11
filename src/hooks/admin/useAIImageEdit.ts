import { useCallback, useState } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import type { IAiImageEditRequest, IAiImageEditResponse } from '../../types/ai-image-edit.js';

export const useAIImageEdit = () => {
    const [isSubmitting, setIsSubmitting] = useState(false);

    const submitImageEdit = useCallback(async (payload: IAiImageEditRequest): Promise<IAiImageEditResponse> => {
        setIsSubmitting(true);
        try {
            const response = await ApiClient.post<IAiImageEditResponse>('/api/ai_edit_image.php', payload);
            if (!response?.success) {
                throw new Error(response?.error || response?.message || 'AI image edit failed');
            }
            return response;
        } finally {
            setIsSubmitting(false);
        }
    }, []);

    return {
        isSubmitting,
        submitImageEdit
    };
};
