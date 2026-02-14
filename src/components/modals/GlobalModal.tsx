import React, { useState, useEffect, Suspense, lazy } from 'react';
import { createPortal } from 'react-dom';
import { useModalContext } from '../../context/ModalContext.js';
import { GlobalModalHeader } from './global/GlobalModalHeader.js';
import { GlobalModalBody } from './global/GlobalModalBody.js';
import { GlobalModalFooter } from './global/GlobalModalFooter.js';

// Modal Registry for dynamic components
const modalRegistry: Record<string, React.ComponentType<any>> = {
    'AdminCustomerEditor': lazy(() => import('./admin/customers/CustomerEditorModal.js').then(m => ({ default: m.CustomerEditorModal })))
};

/**
 * GlobalModal v1.3.0
 * Refactored into sub-components to satisfy the <250 line rule.
 */
export const GlobalModal: React.FC = () => {
    const { modal, close } = useModalContext();
    const [inputValue, setInputValue] = useState('');

    useEffect(() => {
        if (modal?.isOpen && modal.input?.defaultValue) {
            setInputValue(modal.input.defaultValue);
        } else {
            setInputValue('');
        }
    }, [modal?.isOpen, modal?.input?.defaultValue]);

    if (!modal?.isOpen) return null;

    const {
        title = 'Confirm Action',
        message = 'Are you sure you want to proceed?',
        subtitle,
        details,
        detailsCollapsible,
        detailsLabel,
        detailsDefaultOpen,
        icon,
        iconType = 'warning',
        iconKey,
        confirmText = 'Confirm',
        cancelText = 'Cancel',
        confirmStyle = 'confirm',
        mode: rawMode = 'confirm',
        showCancel = rawMode !== 'alert',
        input,
        extraActions,
        component
    } = modal;

    const mode = component ? 'component' : rawMode;

    const handleConfirm = () => {
        if (mode === 'prompt') {
            close(inputValue);
        } else {
            close(true);
        }
    };

    const handleCancel = () => {
        close(mode === 'prompt' ? null : false);
    };

    if (mode === 'component' && component) {
        const TargetComponent = modalRegistry[component];
        if (!TargetComponent) {
            console.error(`Modal component "${component}" not found in registry.`);
            return null;
        }

        return createPortal(
            <div
                className="wf-modal-overlay show component-modal"
                style={{
                    position: 'fixed', inset: 0, zIndex: 'var(--wf-z-modal)',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    backgroundColor: 'rgba(0, 0, 0, 0.85)', backdropFilter: 'blur(8px)',
                    width: '100vw', height: '100vh'
                }}
                onClick={(e) => e.target === e.currentTarget && handleCancel()}
            >
                <Suspense fallback={<div className="text-white">Loading...</div>}>
                    <TargetComponent {...(modal.props || {})} onClose={handleCancel} />
                </Suspense>
            </div>,
            document.body
        );
    }

    const modalContent = (
        <div
            className="wf-modal-overlay show global-modal"
            style={{
                position: 'fixed', inset: 0, zIndex: 'var(--wf-z-modal)',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                backgroundColor: 'rgba(0, 0, 0, 0.85)', backdropFilter: 'blur(8px)',
                width: '100vw', height: '100vh', padding: '2.5vh 2.5vw', boxSizing: 'border-box'
            }}
            onClick={(e) => e.target === e.currentTarget && handleCancel()}
        >
            <div
                className="wf-modal-card my-auto animate-in zoom-in-95 slide-in-from-bottom-4 duration-300 flex flex-col"
                style={{
                    maxWidth: '400px', width: '100%', maxHeight: '100%',
                    backgroundColor: 'white', borderRadius: '16px',
                    overflow: 'hidden', position: 'relative'
                }}
                onClick={e => e.stopPropagation()}
            >
                <GlobalModalHeader
                    title={title}
                    iconKey={iconKey}
                    icon={icon}
                    iconType={iconType}
                    onClose={handleCancel}
                />

                <div className="p-6 space-y-4 flex-1 overflow-y-auto">
                    <GlobalModalBody
                        message={message}
                        subtitle={subtitle}
                        details={details}
                        detailsCollapsible={detailsCollapsible}
                        detailsLabel={detailsLabel}
                        detailsDefaultOpen={detailsDefaultOpen}
                        mode={mode}
                        inputValue={inputValue}
                        setInputValue={setInputValue}
                        input={input}
                        onConfirm={handleConfirm}
                    />

                    <GlobalModalFooter
                        confirmText={confirmText}
                        cancelText={cancelText}
                        confirmStyle={confirmStyle}
                        showCancel={showCancel}
                        extraActions={extraActions}
                        onConfirm={handleConfirm}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
