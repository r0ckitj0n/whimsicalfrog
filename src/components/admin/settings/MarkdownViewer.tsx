import React, { useState, useEffect } from 'react';
import { ApiClient } from '../../../core/ApiClient.js';

interface MarkdownViewerProps {
    file?: string;
    onClose?: () => void;
}

export function MarkdownViewer({ file, onClose }: MarkdownViewerProps) {
    const [content, setContent] = useState<string>('');
    const [title, setTitle] = useState<string>('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!file) {
            setError('No file specified');
            setLoading(false);
            return;
        }

        const fetchMarkdown = async () => {
            setLoading(true);
            setError(null);

            try {
                const json = await ApiClient.get<{ success: boolean; error?: string; data?: { html?: string; title?: string } }>('/api/markdown_viewer.php', { file });

                if (json.success) {
                    setContent(json.data?.html || '');
                    setTitle(json.data?.title || file);
                } else {
                    throw new Error(json.error || 'Failed to load');
                }
            } catch (err) {
                setError(err instanceof Error ? err.message : 'Network error');
            } finally {
                setLoading(false);
            }
        };

        fetchMarkdown();
    }, [file]);

    return (
        <div className="p-6 bg-white min-h-[400px]">
            <div className="flex items-center justify-between mb-4">
                <h1 className="text-2xl font-bold text-gray-800">{title || 'Markdown Viewer'}</h1>
                {onClose && (
                    <button type="button" onClick={onClose} className="btn-text-secondary">
                        Close
                    </button>
                )}
            </div>

            {loading && (
                <div className="flex items-center justify-center py-12">
                    <span className="text-gray-500">Loading...</span>
                </div>
            )}

            {error && (
                <div className="bg-red-50 border border-red-200 text-red-700 p-3 rounded">
                    {error}
                </div>
            )}

            {!loading && !error && content && (
                <article
                    className="prose max-w-none"
                    dangerouslySetInnerHTML={{ __html: content }}
                />
            )}
        </div>
    );
}

export default MarkdownViewer;
