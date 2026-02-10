import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { IItemImage } from '../../types/index.js';
import { API_ACTION } from '../../core/constants.js';

export const useInventoryImages = (sku: string) => {
    const [images, setImages] = useState<IItemImage[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);
    const [error, setError] = useState<string | null>(null);

    const fetchImages = useCallback(async () => {
        if (!sku) return;
        setIsLoading(true);
        try {
            const data = await ApiClient.get<{ success: boolean; images?: IItemImage[]; message?: string }>(`/api/get_item_images.php?sku=${sku}`);
            if (data?.success) {
                setImages(data.images || []);
            } else {
                setError(data?.message || 'Failed to load images');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchImages failed', err);
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, [sku]);

    const uploadImages = useCallback(async (files: FileList | File[], altText: string = '', useAI: boolean = true) => {
        if (!sku) return null;
        setIsLoading(true);
        setUploadProgress(0);
        setError(null);

        const formData = new FormData();
        formData.append('sku', sku);
        formData.append('altText', altText);
        formData.append('useAIProcessing', useAI ? 'true' : 'false');
        
        const filesArray = Array.from(files);
        filesArray.forEach((file) => {
            formData.append('images[]', file);
        });

        try {
            const result = await ApiClient.upload<{ success: boolean; error?: string } | null>('/functions/process_multi_image_upload.php', formData, {
                onProgress: (e) => {
                    if (e.lengthComputable) {
                        const percent = Math.min(100, Math.round((e.loaded / e.total) * 100));
                        setUploadProgress(percent);
                    }
                }
            });

            if (result?.success) {
                await fetchImages();
                return result;
            } else {
                throw new Error(result?.error || 'Upload failed');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('uploadImages failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
            setUploadProgress(0);
        }
    }, [sku, fetchImages]);

    const deleteImage = useCallback(async (imageId: number) => {
        setIsLoading(true);
        try {
            const data = await ApiClient.post<{ success: boolean; error?: string }>(`/api/delete_item_image.php?action=${API_ACTION.DELETE}`, { imageId });
            if (data?.success) {
                await fetchImages();
                return true;
            } else {
                throw new Error(data?.error || 'Failed to delete image');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteImage failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchImages]);

    const setPrimaryImage = useCallback(async (imageId: number) => {
        setIsLoading(true);
        try {
            const data = await ApiClient.post<{ success: boolean; error?: string }>('/api/set_primary_image.php', { imageId, sku });
            if (data?.success) {
                await fetchImages();
                return true;
            } else {
                throw new Error(data?.error || 'Failed to set primary image');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('setPrimaryImage failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [sku, fetchImages]);

    useEffect(() => {
        if (!sku) {
            setImages([]);
            setError(null);
            return;
        }
        void fetchImages();
    }, [sku, fetchImages]);

    return {
        images,
        isLoading,
        uploadProgress,
        error,
        fetchImages,
        uploadImages,
        deleteImage,
        setPrimaryImage
    };
};
