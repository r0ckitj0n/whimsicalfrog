import React from 'react';
import { IGlobalGender } from '../../../../hooks/admin/useGlobalEntities.js';

interface GenderTabProps {
    genders: IGlobalGender[];
    onAdd: () => void;
    onDelete: (id: number, name: string) => void;
}

export const GenderTab: React.FC<GenderTabProps> = ({ genders, onAdd, onDelete }) => {
    return (
        <div className="space-y-4">
            <div className="flex justify-between items-center">
                <h4 className="text-md font-bold text-gray-800">Manage Genders</h4>
                <div className="flex gap-2">
                    <button
                        onClick={onAdd}
                        className="admin-action-btn btn-icon--add"
                        data-help-id="attributes-add-gender"
                    />
                </div>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {genders.map(gender => (
                    <div key={gender.id} className="flex items-center justify-between p-4 border rounded-lg bg-white shadow-sm group">
                        <div className="flex items-center gap-3">
                            <span className="text-2xl">👤</span>
                            <div className="font-bold text-gray-900">{gender.gender_name}</div>
                        </div>
                        <button
                            onClick={() => onDelete(gender.id, gender.gender_name)}
                            className="admin-action-btn btn-icon--delete"
                            data-help-id="attributes-delete-gender"
                        />
                    </div>
                ))}
            </div>
        </div>
    );
};
