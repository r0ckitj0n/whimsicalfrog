import React from 'react';
import { IGlobalSize } from '../../../../hooks/admin/useGlobalEntities.js';

interface GlobalSizesTabProps {
    sizes: IGlobalSize[];
    onAdd: () => void;
    onDelete: (id: number, name: string) => void;
}

export const GlobalSizesTab: React.FC<GlobalSizesTabProps> = ({ sizes, onAdd, onDelete }) => {
    return (
        <div className="space-y-4">
            <div className="flex justify-between items-center">
                <h4 className="text-md font-bold text-gray-800">Manage Global Sizes</h4>
                <div className="flex gap-2">
                    <button
                        onClick={onAdd}
                        className="admin-action-btn btn-icon--add"
                        data-help-id="attributes-add-global-size"
                    />
                </div>
            </div>
            <div className="overflow-x-auto border rounded-lg shadow-sm">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Order</th>
                            <th className="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Code</th>
                            <th className="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Name</th>
                            <th className="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Category</th>
                            <th className="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {sizes.map(size => (
                            <tr key={size.id} className="hover:bg-[var(--brand-primary)]/10 transition-colors group">
                                <td className="px-4 py-3 text-sm text-gray-500 font-mono">{size.display_order}</td>
                                <td className="px-4 py-3 text-sm font-bold text-gray-900">{size.size_code}</td>
                                <td className="px-4 py-3 text-sm text-gray-600">{size.size_name}</td>
                                <td className="px-4 py-3 text-sm text-gray-500">{size.category || 'General'}</td>
                                <td className="px-4 py-3 text-right text-sm font-medium">
                                    <div className="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            onClick={() => onDelete(size.id, size.size_name)}
                                            className="admin-action-btn btn-icon--delete"
                                            data-help-id="attributes-delete-global-size"
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};
