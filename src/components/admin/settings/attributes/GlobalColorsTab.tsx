import React from 'react';
import { IGlobalColor } from '../../../../hooks/admin/useGlobalEntities.js';

interface GlobalColorsTabProps {
    colors: IGlobalColor[];
    onAdd: () => void;
    onDelete: (id: number, name: string) => void;
}

export const GlobalColorsTab: React.FC<GlobalColorsTabProps> = ({ colors, onAdd, onDelete }) => {
    return (
        <div className="space-y-4">
            <div className="flex justify-between items-center">
                <h4 className="text-md font-bold text-gray-800">Manage Global Colors</h4>
                <div className="flex gap-2">
                    <button
                        onClick={onAdd}
                        className="admin-action-btn btn-icon--add"
                        data-help-id="attributes-add-global-color"
                    />
                </div>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {colors.map(color => (
                    <div key={color.id} className="flex items-center justify-between p-3 border rounded-lg bg-white shadow-sm hover:shadow-md transition-shadow group">
                        <div className="flex items-center gap-3">
                            <div
                                className="w-8 h-8 rounded-full border shadow-sm wf-color-preview-swatch"
                                style={{ '--swatch-bg': color.color_code || '#cccccc' } as React.CSSProperties}
                            />
                            <div>
                                <div className="text-sm font-bold text-gray-900">{color.color_name}</div>
                                <div className="text-[10px] text-gray-500 font-mono uppercase">{color.color_code || 'No code'}</div>
                            </div>
                        </div>
                        <div className="flex items-center gap-1">
                            <button
                                type="button"
                                onClick={() => onDelete(color.id, color.color_name)}
                                className="admin-action-btn btn-icon--delete"
                                data-help-id="attributes-delete-global-color"
                            />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};
