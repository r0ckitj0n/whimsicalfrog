import React from 'react';
import { IColorTemplate } from '../../../../hooks/admin/useGlobalEntities.js';
import type { IInventoryOptionLink } from '../../../../types/inventoryOptions.js';

interface ColorTemplatesTabProps {
    templates: IColorTemplate[];
    links?: IInventoryOptionLink[];
    onAdd: () => void;
    onEdit: (id: number) => void;
    onDuplicate: (id: number) => void;
    onDelete: (id: number, name: string) => void;
    onOpenRedesign: () => void;
}

export const ColorTemplatesTab: React.FC<ColorTemplatesTabProps> = ({
    templates,
    links = [],
    onAdd,
    onEdit,
    onDuplicate,
    onDelete,
    onOpenRedesign
}) => {
    const linkById = new Map<number, IInventoryOptionLink>();
    links.filter(l => l.option_type === 'color_template').forEach(l => linkById.set(l.option_id, l));

    const summarize = (l?: IInventoryOptionLink) => {
        if (!l) return 'Unassigned';
        if (l.applies_to_type === 'category') return `Category: ${l.category_name || `#${l.category_id ?? '?'}`}`;
        return `SKU: ${l.item_sku || '?'}${l.item_name ? ` (${l.item_name})` : ''}`;
    };

    return (
        <div className="space-y-4">
            <div className="flex justify-between items-center">
                <h4 className="text-md font-bold text-gray-800">Manage Color Templates</h4>
                <div className="flex gap-2">
                    <button
                        onClick={onOpenRedesign}
                        className="admin-action-btn btn-icon--settings"
                        data-help-id="attributes-redesign-system"
                    />
                    <button
                        onClick={onAdd}
                        className="admin-action-btn btn-icon--add"
                        data-help-id="attributes-new-color-template"
                    />
                </div>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {templates.map(tpl => (
                    <div key={tpl.id} className="p-4 border rounded-lg bg-white shadow-sm hover:shadow-md transition-shadow group">
                        <div className="flex justify-between items-start mb-2">
                            <div>
                                <div className="text-sm font-bold text-gray-900">{tpl.template_name}</div>
                                <div className="text-xs text-gray-500">{tpl.category || 'General'}</div>
                                <div className="text-[11px] text-gray-600 mt-1">
                                    <span className="font-semibold text-gray-800">Applies to:</span> {summarize(linkById.get(tpl.id))}
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <button
                                    onClick={() => onDuplicate(tpl.id)}
                                    className="admin-action-btn btn-icon--duplicate"
                                    data-help-id="attributes-duplicate-color-template"
                                />
                                <button
                                    onClick={() => onEdit(tpl.id)}
                                    className="admin-action-btn btn-icon--edit"
                                    data-help-id="attributes-edit-color-template"
                                />
                                <button
                                    onClick={() => onDelete(tpl.id, tpl.template_name)}
                                    className="admin-action-btn btn-icon--delete"
                                    data-help-id="attributes-delete-color-template"
                                />
                            </div>
                        </div>
                        <div className="text-xs font-mono text-gray-400">
                            {tpl.color_count || 0} colors defined
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};
