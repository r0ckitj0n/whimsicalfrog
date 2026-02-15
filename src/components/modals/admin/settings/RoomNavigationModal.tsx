import React from 'react';
import { createPortal } from 'react-dom';
import { NavigationTab } from '../../../admin/settings/room-manager/tabs/NavigationTab.js';
import type { IRoomConnection, IRoomHeaderLink } from '../../../../types/index.js';

interface RoomNavigationModalProps {
    isOpen: boolean;
    onClose: () => void;
    connections: IRoomConnection[];
    externalLinks: IRoomConnection[];
    headerLinks: IRoomHeaderLink[];
    isDetecting: boolean;
    onDetectConnections: () => Promise<void>;
}

export const RoomNavigationModal: React.FC<RoomNavigationModalProps> = ({
    isOpen,
    onClose,
    connections,
    externalLinks,
    headerLinks,
    isDetecting,
    onDetectConnections
}) => {
    React.useEffect(() => {
        if (!isOpen) return;
        const onKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
        };
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    return createPortal(
        <div
            className="fixed inset-0 z-[calc(var(--wf-z-modal)+10)] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
            role="dialog"
            aria-modal="true"
            aria-label="Room navigation"
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose();
            }}
        >
            <div className="w-full max-w-6xl max-h-[92vh] bg-white rounded-2xl shadow-xl overflow-hidden flex flex-col">
                <div className="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-white flex-shrink-0">
                    <div className="flex items-center gap-3">
                        <span className="text-xl">🔗</span>
                        <h3 className="text-sm font-black text-slate-700 uppercase tracking-widest">Navigation</h3>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Close"
                        className="w-10 h-10 rounded-full bg-slate-50 border border-slate-200 text-slate-600 hover:bg-slate-100 transition-colors flex items-center justify-center"
                        data-help-id="common-close"
                    >
                        ×
                    </button>
                </div>

                <div className="flex-1 min-h-0 overflow-hidden">
                    <NavigationTab
                        connections={connections}
                        externalLinks={externalLinks}
                        headerLinks={headerLinks}
                        isDetecting={isDetecting}
                        onDetectConnections={onDetectConnections}
                    />
                </div>
            </div>
        </div>,
        document.body
    );
};

