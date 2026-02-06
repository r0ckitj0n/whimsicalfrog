import React from 'react';

import { IEmailAssignment } from '../../../../hooks/admin/useEmailTemplates.js';

interface AssignmentListProps {
    emailTypes: { id: string; label: string }[];
    assignments: IEmailAssignment;
    templates: { id: number; template_name: string; template_type: string }[];
    onSetAssignment: (type: string, templateId: number | null) => void;
}

export const AssignmentList: React.FC<AssignmentListProps> = ({
    emailTypes,
    assignments,
    templates,
    onSetAssignment
}) => {
    const handleChange = (emailType: string, value: string) => {
        if (value === '') {
            onSetAssignment(emailType, null);
        } else {
            onSetAssignment(emailType, parseInt(value, 10));
        }
    };

    return (
        <div className="space-y-6">
            <div className="p-4 bg-[var(--brand-primary)]/5 border border-[var(--brand-primary)]/10 rounded-lg">
                <h4 className="text-[var(--brand-primary)] font-bold mb-1">How Assignments Work</h4>
                <p className="text-xs text-[var(--brand-primary)]/80">These mappings determine which template is automatically sent for specific system events. You can assign any template to these events. Changes are buffered locally until you click Save.</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {emailTypes.map(type => (
                    <div key={type.id} className="p-4 border rounded-xl bg-white shadow-sm">
                        <label className="block text-xs font-black text-gray-400 uppercase tracking-widest mb-3">{type.label}</label>
                        <select
                            value={String(assignments[type.id] || '')}
                            onChange={(e) => handleChange(type.id, e.target.value)}
                            className="w-full p-2 border border-gray-300 rounded-lg text-sm bg-gray-50 focus:bg-white focus:ring-2 focus:ring-brand-primary outline-none transition-all"
                        >
                            <option value="">-- No template assigned --</option>
                            {templates.map(tpl => (
                                <option key={tpl.id} value={tpl.id}>{tpl.template_name} ({tpl.template_type})</option>
                            ))}
                        </select>
                    </div>
                ))}
            </div>
        </div>
    );
};
