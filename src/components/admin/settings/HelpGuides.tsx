import React, { useState, useEffect, useMemo } from 'react';
import { createPortal } from 'react-dom';
import { useHelpGuides, IDocListItem } from '../../../hooks/admin/useHelpGuides.js';

interface HelpGuidesProps {
    onClose?: () => void;
    title?: string;
}

/**
 * A simple markdown-to-HTML renderer using regex for basic documentation needs.
 */
const SimpleMarkdown: React.FC<{ content: string }> = ({ content }) => {
    const html = useMemo(() => {
        let text = content;

        // Headers
        text = text.replace(/^# (.*$)/gim, '<h1 class="text-3xl font-black text-gray-900 mb-6 border-b pb-4">$1</h1>');
        text = text.replace(/^## (.*$)/gim, '<h2 class="text-2xl font-bold text-gray-800 mt-10 mb-4">$1</h2>');
        text = text.replace(/^### (.*$)/gim, '<h3 class="text-xl font-bold text-gray-800 mt-8 mb-3">$1</h3>');

        // Bold
        text = text.replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-gray-900">$1</strong>');

        // Lists
        text = text.replace(/^\* (.*$)/gim, '<li class="ml-4 list-disc mb-1">$1</li>');
        text = text.replace(/^- (.*$)/gim, '<li class="ml-4 list-disc mb-1">$1</li>');

        // Wrap lists in ul
        text = text.replace(/(<li.*<\/li>)/gms, '<ul class="my-4 space-y-1">$1</ul>');

        // Newlines to breaks (if not already handled by block elements)
        text = text.replace(/\n\n/g, '</p><p class="mb-4">');

        // Links
        text = text.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" class="text-blue-600 hover:underline" target="_blank">$1</a>');

        return `<p class="mb-4 text-gray-700 leading-relaxed">${text}</p>`;
    }, [content]);

    return <div className="markdown-body prose max-w-none" dangerouslySetInnerHTML={{ __html: html }} />;
};

export const HelpGuides: React.FC<HelpGuidesProps> = ({ onClose, title }) => {
    const { documents, currentDoc, isLoading, error, fetchDoc } = useHelpGuides();
    const [searchQuery, setSearchQuery] = useState('');

    const filteredDocs = useMemo(() => {
        if (!searchQuery) return documents;
        return documents.filter(doc =>
            doc.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
            doc.path.toLowerCase().includes(searchQuery.toLowerCase()) ||
            doc.content.toLowerCase().includes(searchQuery.toLowerCase())
        );
    }, [documents, searchQuery]);

    const handleDocClick = (path: string) => {
        fetchDoc(path);
    };

    // Auto-load first doc if none selected
    useEffect(() => {
        if (documents.length > 0 && !currentDoc && !isLoading && !error) {
            handleDocClick(documents[0].path);
        }
    }, [documents, currentDoc, isLoading, error]);

    const modalContent = (
        <div
            className="admin-modal-overlay over-header show topmost"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose?.();
            }}
        >
            <div
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1200px] max-w-[95vw] h-[85vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">📚</span> {title || 'Help & Guides'}
                    </h2>

                    <div className="flex-1" />

                    <div className="flex items-center gap-4">
                        <button
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="common-close"
                        />
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-hidden flex">
                    {/* Sidebar */}
                    <div className="w-80 border-r border-gray-100 flex flex-col bg-gray-50/50">
                        <div className="p-4 border-b border-gray-100 bg-white">
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Search guides..."
                                className="form-input form-input-search text-sm"
                            />
                        </div>

                        <div className="flex-1 overflow-y-auto p-4 space-y-1">
                            {filteredDocs.map((doc) => (
                                <button
                                    key={doc.path}
                                    onClick={() => handleDocClick(doc.path)}
                                    className={`w-full text-left px-4 py-3 rounded-xl text-sm font-medium transition-all ${currentDoc?.path === doc.path
                                        ? 'bg-blue-600 text-white shadow-md'
                                        : 'text-gray-600 hover:bg-white hover:shadow-sm'
                                        }`}
                                >
                                    {doc.title}
                                </button>
                            ))}
                            {filteredDocs.length === 0 && !isLoading && (
                                <div className="text-center py-10">
                                    <span className="text-3xl block mb-2">🎈</span>
                                    <p className="text-xs text-gray-400 font-bold uppercase tracking-widest">No matching guides</p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Content */}
                    <div className="flex-1 overflow-y-auto bg-white p-10 lg:p-16 relative">
                        {isLoading && (
                            <div className="absolute inset-0 bg-white/80 backdrop-blur-[2px] z-10 flex items-center justify-center">
                                <div className="flex flex-col items-center gap-4">
                                    <div className="wf-spinner" />
                                    <p className="text-[10px] font-black text-blue-600 uppercase tracking-widest">Loading Content...</p>
                                </div>
                            </div>
                        )}

                        {error && (
                            <div className="bg-red-50 border border-red-100 text-red-600 p-6 rounded-2xl mb-8 flex items-center gap-4">
                                <span className="text-2xl">⚠️</span>
                                <div>
                                    <h4 className="font-bold text-sm">Failed to load content</h4>
                                    <p className="text-xs opacity-80">{error}</p>
                                </div>
                            </div>
                        )}

                        {currentDoc ? (
                            <SimpleMarkdown content={currentDoc.content} />
                        ) : !isLoading && (
                            <div className="h-full flex flex-col items-center justify-center text-center max-w-md mx-auto">
                                <span className="text-6xl mb-6">📖</span>
                                <h3 className="text-xl font-black text-gray-800 mb-2">Select a Guide</h3>
                                <p className="text-sm text-gray-500 leading-relaxed">
                                    Choose a document from the sidebar to view instructions and platform documentation.
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
