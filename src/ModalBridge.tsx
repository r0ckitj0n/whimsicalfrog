import { useEffect } from 'react';
import { useModalContext } from './context/ModalContext.js';
import { useApp } from './context/AppContext.js';
import { IModalOptions } from './types/modals.js';
import logger from './core/logger.js';

/**
 * ModalBridge Component
 * Exposes the React modal system to the legacy window objects.
 */
export const ModalBridge = () => {
    const { modal, show, confirm, alert, prompt, close: closeModal } = useModalContext();
    const { authMode } = useApp();

    useEffect(() => {
        if (typeof window === 'undefined') return;

        // Global confirmation function
        window.showConfirmationModal = async (options: IModalOptions) => {
            return await show({ ...options, mode: 'confirm' });
        };

        // Close topmost modal helper (legacy support)
        window.closeTopAdminModal = () => {
            closeModal(false);
            // Also try to find and close any legacy overlays that might still be in the DOM
            const legacy = document.querySelector('.admin-modal-overlay.show, .wf-login-overlay.show, .confirmation-modal-overlay.show') as HTMLElement;
            if (legacy) {
                legacy.classList.remove('show', 'flex');
                legacy.classList.add('hidden');
                legacy.style.removeProperty('display');
                legacy.setAttribute('aria-hidden', 'true');

                setTimeout(() => {
                    if (!document.querySelector('.admin-modal-overlay.show')) {
                        document.body.classList.remove('wf-modal-open');
                    }
                }, 10);
            }
        };

        // Convenience functions for different types of confirmations
        window.confirmAction = async (title: string, message: string, confirmText = 'Confirm') => {
            return await confirm({
                title,
                message,
                confirmText,
                icon: '⚠️',
                iconType: 'warning'
            });
        };

        window.confirmDanger = async (title: string, message: string, confirmText = 'Delete') => {
            return await confirm({
                title,
                message,
                confirmText,
                confirmStyle: 'danger',
                icon: '⚠️',
                iconType: 'danger'
            });
        };

        window.confirmInfo = async (title: string, message: string, confirmText = 'Continue') => {
            return await confirm({
                title,
                message,
                confirmText,
                icon: 'ℹ️',
                iconType: 'info'
            });
        };

        window.confirmSuccess = async (title: string, message: string, confirmText = 'Proceed') => {
            return await confirm({
                title,
                message,
                confirmText,
                icon: '✅',
                iconType: 'success'
            });
        };

        // Alert modal (single OK button)
        window.showAlertModal = async (options: IModalOptions = {}) => {
            return await alert(options);
        };

        // Global Backdrop Click Handler for legacy overlays (not React admin modals)
        // Note: .admin-modal-overlay is NOT included here because React modal components
        // handle their own overlay clicks via onClick, which properly updates the URL.
        const handleGlobalBackdropClick = (e: MouseEvent) => {
            const target = e.target as HTMLElement;
            if (target.classList.contains('modal-overlay') ||
                target.classList.contains('confirmation-modal-overlay')) {
                window.closeTopAdminModal?.();
            }
        };

        document.addEventListener('mousedown', handleGlobalBackdropClick);

        // Prompt modal (text input)
        window.showPromptModal = async (options: IModalOptions = {}) => {
            return await prompt(options);
        };

        // Enhanced confirmation with details
        window.confirmWithDetails = async (title: string, message: string, details: string, options: IModalOptions = {}) => {
            return await confirm({
                title,
                message,
                details,
                ...options
            });
        };

        // Global Themed Aliases (Site Standard)
        window.WF_Confirm = async (options: IModalOptions) => await confirm(options);
        window.WF_Alert = async (options: IModalOptions) => await alert(options);

        // Soft override for window.alert (non-blocking)
        const nativeAlert = window.alert;
        window.alert = (message?: unknown) => {
            alert({
                title: 'Note',
                message: String(message || ''),
                iconKey: 'info',
                iconType: 'info',
                confirmText: 'OK',
                showCancel: false
            });
        };

        // Warning for native confirm
        const nativeConfirm = window.confirm;
        window.confirm = (message?: string) => {
            console.warn('⚠️ Native window.confirm() is deprecated. Please use async window.WF_Confirm() or useModalContext().confirm() for the themed experience.');
            return nativeConfirm(message);
        };


        logger.info('🎉 ModalBridge: Legacy global handlers and themed overrides attached');


        return () => {
            document.removeEventListener('mousedown', handleGlobalBackdropClick);
        };
    }, [show, confirm, alert, prompt, closeModal]);

    return null;
};
