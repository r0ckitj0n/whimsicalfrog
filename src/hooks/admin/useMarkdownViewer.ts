import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';

export const useMarkdownViewer = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [content, setContent] = useState<string>('');
    const [title, setTitle] = useState<string>('');
    const [error, setError] = useState<string | null>(null);

    const fetchMarkdown = useCallback(async (filePath: string) => {
        setIsLoading(true);
        setError(null);
        try {
            // Using existing md_viewer.php but we want raw content or JSON
            // If md_viewer.php is only for HTML rendering, we might need a dedicated API
            const response = await ApiClient.get<{ success?: boolean; html?: string; title?: string } | string>(`/sections/tools/md_viewer.php?file=${encodeURIComponent(filePath)}&modal=1`);
            
            // If the response is HTML (as md_viewer.php seems to be), we might need to adjust
            // For now, let's assume we can fetch raw content if we had an API.
            // Since md_viewer.php returns a full HTML partial, we'll treat it as such or look for a JSON alternative.
            
            if (typeof response === 'string') {
                setContent(response);
            } else if (response && response.success) {
                setContent(response.html || '');
                setTitle(response.title || '');
            }
        } catch (err) {
            logger.error('[MarkdownViewer] fetch failed', err);
            setError('Unable to load documentation.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    return {
        isLoading,
        content,
        title,
        error,
        fetchMarkdown
    };
};
