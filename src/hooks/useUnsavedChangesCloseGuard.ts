import { useCallback } from 'react';
import { useModalContext } from '../context/ModalContext.js';

interface UnsavedChangesCloseGuardOptions {
    isDirty: boolean;
    isBlocked?: boolean;
    onClose?: () => void;
    onSave?: () => Promise<boolean | void> | boolean | void;
    closeAfterSave?: boolean;
}

export const useUnsavedChangesCloseGuard = ({
    isDirty,
    isBlocked = false,
    onClose,
    onSave,
    closeAfterSave = true
}: UnsavedChangesCloseGuardOptions) => {
    const { confirm } = useModalContext();

    return useCallback(async () => {
        if (isBlocked) return;

        if (!isDirty) {
            onClose?.();
            return;
        }

        const shouldSave = await confirm({
            title: 'Unsaved Changes',
            message: 'Save changes before closing this modal?',
            subtitle: 'Choose Save to keep edits, or Discard to close without saving.',
            confirmText: 'Save',
            cancelText: 'Discard',
            confirmStyle: 'warning',
            iconKey: 'warning'
        });

        if (!shouldSave) {
            onClose?.();
            return;
        }

        if (onSave) {
            try {
                const saveResult = await onSave();
                if (saveResult === false) return;
            } catch {
                return;
            }
        }

        if (closeAfterSave) {
            onClose?.();
        }
    }, [isBlocked, isDirty, onClose, onSave, closeAfterSave, confirm]);
};
