import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type {
    IHelpDocument as IDocument,
    IHelpDocListItem as IDocListItem,
    IHelpListResponse,
    IHelpDocResponse
} from '../../types/help.js';

// Re-export for backward compatibility
export type {
    IHelpDocument as IDocument,
    IHelpDocListItem as IDocListItem,
    IHelpListResponse,
    IHelpDocResponse
} from '../../types/help.js';



/**
 * Hook for managing and fetching help documentation.
 */
export const useHelpGuides = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [documents, setDocuments] = useState<IDocListItem[]>([]);
    const [currentDoc, setCurrentDoc] = useState<IDocument | null>(null);
    const [error, setError] = useState<string | null>(null);

    const fetchList = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IHelpListResponse>('/api/docs_proxy.php?docs=list');
            if (res?.success && res.documents) {
                // Map backend 'filename' to 'path' for frontend consistency
                const mapped = res.documents.map((d) => ({
                    title: d.title,
                    path: d.filename,
                    content: d.content || ''
                }));
                setDocuments(mapped);
            } else {
                setError(res?.error || 'Failed to load documentation list');
            }
        } catch (err) {
            logger.error('[useHelpGuides] fetchList failed', err);
            setError('Failed to load documentation list');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const fetchDoc = useCallback(async (path: string) => {
        if (!path || isLoading) return;
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IHelpDocResponse>('/api/docs_proxy.php', { docs: 'get', file: path });
            if (res?.success && res.document) {
                setCurrentDoc({ ...res.document, path });
            } else {
                setError(res?.error || 'Failed to load document');
            }
        } catch (err) {
            logger.error('[useHelpGuides] fetchDoc failed', err);
            setError('Failed to load document');
        } finally {
            setIsLoading(false);
        }
    }, [isLoading]);

    useEffect(() => {
        fetchList();
    }, [fetchList]);

    return {
        isLoading,
        documents,
        currentDoc,
        error,
        fetchList,
        fetchDoc,
        setCurrentDoc,
        setError
    };
};
