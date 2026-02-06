import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type {
    ISocialPostTemplate,
    ISocialImage,
    ISocialTemplatesResponse,
    ISocialImagesResponse,
    ISocialTemplateDetailResponse
} from '../../types/social.js';

// Re-export for backward compatibility
export type {
    ISocialPostTemplate,
    ISocialImage,
    ISocialTemplatesResponse,
    ISocialImagesResponse,
    ISocialTemplateDetailResponse
} from '../../types/social.js';



export const useSocialPosts = () => {
    const [templates, setTemplates] = useState<ISocialPostTemplate[]>([]);
    const [images, setImages] = useState<ISocialImage[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchTemplates = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<ISocialTemplatesResponse>('/api/social_posts_templates.php?action=list');
            if (res && res.success) {
                setTemplates(res.templates || []);
            } else {
                setError(res?.message || 'Failed to load templates.');
            }
        } catch (err) {
            logger.error('[SocialPosts] failed to load templates', err);
            setError('Unable to load social post templates.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const fetchImages = useCallback(async () => {
        try {
            const res = await ApiClient.get<ISocialImagesResponse>('/api/list_images.php');
            if (res && res.success) {
                setImages(res.images || []);
            }
        } catch (err) {
            logger.error('[SocialPosts] failed to load images', err);
        }
    }, []);

    const saveTemplate = async (template: Partial<ISocialPostTemplate>) => {
        setIsLoading(true);
        try {
            const isUpdate = !!template.id;
            const url = isUpdate ? '/api/social_posts_templates.php?action=update' : '/api/social_posts_templates.php?action=create';
            const res = await ApiClient.post<{ success: boolean }>(url, template);
            if (res && res.success) {
                await fetchTemplates();
                return true;
            }
        } catch (err) {
            logger.error('[SocialPosts] failed to save template', err);
        } finally {
            setIsLoading(false);
        }
        return false;
    };

    const deleteTemplate = async (id: string) => {
        try {
            const res = await ApiClient.post<{ success: boolean }>('/api/social_posts_templates.php?action=delete', { id });
            if (res && res.success) {
                await fetchTemplates();
                return true;
            }
        } catch (err) {
            logger.error('[SocialPosts] failed to delete template', err);
        }
        return false;
    };

    const publishTemplate = async (id: string) => {
        setIsLoading(true);
        try {
            const getRes = await ApiClient.get<ISocialTemplateDetailResponse>(`/api/social_posts_templates.php?action=get&id=${encodeURIComponent(id)}`);
            if (getRes && getRes.success && getRes.template) {
                const t = getRes.template;
                const payload = {
                    content: t.content || '',
                    image_url: t.image_url || '',
                    platforms: Array.isArray(t.platforms) ? t.platforms : [],
                    publish_all: true
                };
                const res = await ApiClient.post<{ success: boolean }>('/api/publish_social.php?action=publish', payload);
                return res && res.success;
            }
        } catch (err) {
            logger.error('[SocialPosts] failed to publish template', err);
        } finally {
            setIsLoading(false);
        }
        return false;
    };

    useEffect(() => {
        fetchTemplates();
        fetchImages();
    }, [fetchTemplates, fetchImages]);

    return {
        templates,
        images,
        isLoading,
        error,
        fetchTemplates,
        saveTemplate,
        deleteTemplate,
        publishTemplate
    };
};
