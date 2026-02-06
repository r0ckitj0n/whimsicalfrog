import React, { useEffect, useState, useCallback } from 'react';
import { createPortal } from 'react-dom';
import api_client from '../../core/ApiClient.js';
import { KEYBOARD } from '../../core/constants.js';

interface PolicyModalProps {
    isOpen: boolean;
    onClose: () => void;
    url: string;
    label: string;
}

/**
 * PolicyModal v1.2.9
 * Uses React Portal to ensure topmost stacking and 95% viewport height cap.
 */
export const PolicyModal: React.FC<PolicyModalProps> = ({ isOpen, onClose, url, label }) => {
    const [content, setContent] = useState<string>('');
    const [isLoading, setIsLoading] = useState<boolean>(false);

    const fetchContent = useCallback(async () => {
        if (!url) return;
        setIsLoading(true);
        try {
            const target = url + (url.indexOf('?') > -1 ? '&' : '?') + 'modal=1';
            const html = await api_client.request<string>(target, { method: 'GET', responseType: 'text' });

            const doc = new DOMParser().parseFromString(html, 'text/html');
            const contentNode = doc.querySelector('.wf-cloud-card .content') ||
                doc.querySelector('.page-content .wf-cloud-card .content') ||
                doc.querySelector('.page-content') ||
                doc.body;

            setContent(contentNode ? contentNode.innerHTML : html);
        } catch (error) {
            setContent('Failed to load policy content.');
        } finally {
            setIsLoading(false);
        }
    }, [url]);

    useEffect(() => {
        if (isOpen) {
            fetchContent();
        }
    }, [isOpen, fetchContent]);

    // Trap focus and handle escape key
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === KEYBOARD.ESCAPE && isOpen) {
                onClose();
            }
        };

        if (isOpen) {
            window.addEventListener('keydown', handleKeyDown);
        }

        return () => {
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    const modalContent = (
        <div
            id="wfPolicyModalOverlay"
            className="wf-modal-overlay show policy-modal"
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 'var(--wf-z-modal)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                backgroundColor: 'rgba(0, 0, 0, 0.85)',
                backdropFilter: 'blur(8px)',
                width: '100vw',
                height: '100vh',
                padding: '2.5vh 2.5vw',
                boxSizing: 'border-box'
            }}
            onClick={(e) => e.target === e.currentTarget && onClose()}
            aria-label="Close policy"
            role="presentation"
        >
            <div
                id="wfPolicyModal"
                role="dialog"
                aria-modal="true"
                aria-label={label}
                className="wf-modal-card my-auto animate-in zoom-in-95 slide-in-from-bottom-4 duration-300 overflow-hidden flex flex-col"
                style={{
                    maxWidth: '800px',
                    width: '100%',
                    maxHeight: '100%',
                    backgroundColor: 'white',
                    borderRadius: '24px',
                    boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.5)',
                    position: 'relative'
                }}
                onClick={e => e.stopPropagation()}
            >
                <div className="wf-modal-header" style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '1.25rem 2rem',
                    background: 'var(--bg-gradient-brand, linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-secondary) 100%))',
                    borderBottom: '1px solid rgba(255,255,255,0.1)',
                    flexShrink: 0
                }}>
                    <h2 className="admin-card-title" style={{
                        margin: 0,
                        color: '#ffffff',
                        fontFamily: "'Merienda', cursive",
                        fontSize: '1.5rem',
                        fontWeight: 700,
                        textShadow: '0 2px 4px rgba(0,0,0,0.1)'
                    }}>
                        {label}
                    </h2>
                    <button
                        onClick={onClose}
                        className="admin-action-btn btn-icon--close"
                        aria-label="Close"
                        data-help-id="modal-close"
                    />
                </div>
                <div className="flex-1 overflow-y-auto p-8">
                    {isLoading ? (
                        <div className="wf-emoji-loader" style={{ fontSize: '24px' }}>⏳</div>
                    ) : (
                        <div dangerouslySetInnerHTML={{ __html: content }} />
                    )}
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};

export default PolicyModal;
