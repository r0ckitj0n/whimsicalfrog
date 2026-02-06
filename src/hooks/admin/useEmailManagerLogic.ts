import React, { useState, useEffect, useMemo, useCallback } from 'react';
import { IEmailTemplate, IEmailAssignment } from './useEmailTemplates.js';
import { useModalContext } from '../../context/ModalContext.js';

interface UseEmailManagerLogicProps {
    templates: IEmailTemplate[];
    assignments: IEmailAssignment;
    saveTemplate: (template: Partial<IEmailTemplate>) => Promise<{ success: boolean; error?: string }>;
    deleteTemplate: (id: number) => Promise<boolean>;
    saveAllAssignments: (changed: Record<string, number | null>) => Promise<boolean>;
    sendTestEmail: (templateId: number, recipient: string) => Promise<{ success: boolean; message?: string }>;
    fetchAll: () => void;
}

export const useEmailManagerLogic = ({
    templates,
    assignments,
    saveTemplate,
    deleteTemplate,
    saveAllAssignments,
    sendTestEmail,
    fetchAll
}: UseEmailManagerLogicProps) => {
    const [activeTab, setActiveTab] = useState<'templates' | 'assignments'>('templates');
    const [editingTemplate, setEditingTemplate] = useState<Partial<IEmailTemplate> | null>(null);
    const [testEmailAddress, setTestEmailAddress] = useState('');
    const [isTesting, setIsTesting] = useState<number | null>(null);

    const [pendingAssignments, setPendingAssignments] = useState<IEmailAssignment>({});
    const [initialAssignments, setInitialAssignments] = useState<IEmailAssignment>({});

    const hasFetched = React.useRef(false);

    useEffect(() => {
        if (hasFetched.current) return;
        hasFetched.current = true;
        fetchAll();
    }, [fetchAll]);

    useEffect(() => {
        if (assignments && Object.keys(assignments).length >= 0) {
            setPendingAssignments({ ...assignments });
            setInitialAssignments({ ...assignments });
        }
    }, [assignments]);

    const isDirty = useMemo(() => {
        return JSON.stringify(pendingAssignments) !== JSON.stringify(initialAssignments);
    }, [pendingAssignments, initialAssignments]);

    const handleEdit = useCallback((template: IEmailTemplate) => {
        setEditingTemplate({ ...template });
    }, []);

    const handleCreate = useCallback(() => {
        setEditingTemplate({
            template_name: '',
            template_type: 'custom',
            subject: '',
            html_content: '',
            is_active: true
        });
    }, []);

    const handleSaveTemplate = useCallback(async (e: React.FormEvent) => {
        e.preventDefault();
        if (editingTemplate) {
            const res = await saveTemplate(editingTemplate);
            if (res?.success) {
                setEditingTemplate(null);
                if (window.WFToast) window.WFToast.success('Template saved');
            } else {
                if (window.WFToast) window.WFToast.error(res?.error || 'Failed to save template');
            }
        }
    }, [editingTemplate, saveTemplate]);

    const { confirm: themedConfirm, prompt: themedPrompt } = useModalContext();

    const handleDelete = useCallback(async (id: number, name: string) => {
        const confirmed = await themedConfirm({
            title: 'Delete Template',
            message: `Delete template "${name}"?`,
            confirmText: 'Delete Now',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const success = await deleteTemplate(id);
            if (success && window.WFToast) window.WFToast.success('Template deleted');
        }
    }, [themedConfirm, deleteTemplate]);

    const handleSendTest = useCallback(async (templateId: number) => {
        const email = await themedPrompt({
            title: 'Send Test Email',
            message: 'Enter recipient email:',
            input: { defaultValue: testEmailAddress },
            confirmText: 'Send Test',
            icon: '📧'
        });

        if (email) {
            setTestEmailAddress(email);
            setIsTesting(templateId);
            const res = await sendTestEmail(templateId, email);
            setIsTesting(null);

            const msg = res.message || (res.success ? 'Test email sent!' : 'Failed to send test email');
            if (window.WFToast) {
                if (res.success) window.WFToast.success(msg);
                else window.WFToast.error(msg);
            }
        }
    }, [themedPrompt, testEmailAddress, sendTestEmail]);

    const handleAssignmentChange = useCallback((emailType: string, templateId: number | null) => {
        setPendingAssignments(prev => {
            const next = { ...prev };
            if (templateId === null) {
                delete next[emailType];
            } else {
                next[emailType] = templateId;
            }
            return next;
        });
    }, []);

    const handleSaveAssignments = useCallback(async () => {
        const changed: Record<string, number | null> = {};

        for (const [emailType, templateId] of Object.entries(pendingAssignments)) {
            if (initialAssignments[emailType] !== templateId) {
                changed[emailType] = templateId as number;
            }
        }

        for (const emailType of Object.keys(initialAssignments)) {
            if (!(emailType in pendingAssignments)) {
                changed[emailType] = null;
            }
        }

        if (Object.keys(changed).length === 0) return;

        const success = await saveAllAssignments(changed);
        if (success) {
            setInitialAssignments({ ...pendingAssignments });
            if (window.WFToast) window.WFToast.success('Assignments saved');
        } else {
            if (window.WFToast) window.WFToast.error('Failed to save assignments');
        }
    }, [pendingAssignments, initialAssignments, saveAllAssignments]);

    return {
        activeTab,
        setActiveTab,
        editingTemplate,
        setEditingTemplate,
        isTesting,
        pendingAssignments,
        isDirty,
        handleEdit,
        handleCreate,
        handleSaveTemplate,
        handleDelete,
        handleSendTest,
        handleAssignmentChange,
        handleSaveAssignments
    };
};
