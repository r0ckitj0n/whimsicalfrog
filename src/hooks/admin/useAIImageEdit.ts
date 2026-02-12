import { useCallback, useState } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import type { IAiImageEditRequest, IAiImageEditResponse } from '../../types/ai-image-edit.js';
import { useAICostEstimateConfirm } from './useAICostEstimateConfirm.js';

export const useAIImageEdit = () => {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const { confirmWithEstimate } = useAICostEstimateConfirm();

    const submitImageEdit = useCallback(async (payload: IAiImageEditRequest): Promise<IAiImageEditResponse> => {
        const isBackground = payload.target_type === 'background';
        const isShortcutSign = payload.target_type === 'shortcut_sign';
        const confirmed = await confirmWithEstimate({
            action_key: isBackground
                ? 'background_image_submit_to_ai'
                : (isShortcutSign ? 'shortcut_image_submit_to_ai' : 'item_image_submit_to_ai'),
            action_label: isBackground
                ? 'Submit background image edit to AI'
                : (isShortcutSign ? 'Submit shortcut sign image edit to AI' : 'Submit item image edit to AI'),
            operations: [
                { key: 'image_edit_generation', label: 'Image edit generation', image_count: 1, image_generations: 1 }
            ],
            context: {
                image_count: 1,
                prompt_length: String(payload.instructions || '').length
            },
            confirmText: 'Submit to AI'
        });
        if (!confirmed) {
            throw new Error('AI image edit canceled');
        }

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
    }, [confirmWithEstimate]);

    return {
        isSubmitting,
        submitImageEdit
    };
};
