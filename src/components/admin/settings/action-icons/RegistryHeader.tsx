import React from 'react';

interface RegistryHeaderProps {
    isLoading: boolean;
    onRefresh: () => void;
    onReset: () => void;
    onSave: () => void;
}

export const RegistryHeader: React.FC<RegistryHeaderProps> = ({
    isLoading,
    onRefresh,
    onReset,
    onSave
}) => {
    return (
        <div className="px-6 py-4 border-b bg-gray-50 flex items-center justify-between">
            <div className="flex items-center gap-3">
                <h2 className="text-lg font-bold text-gray-800">Action Icon Registry</h2>
                <p className="text-[10px] text-gray-400 font-black uppercase tracking-widest leading-none mt-1">Emoji Mapping & CSS Generator</p>
            </div>
            <div className="flex gap-2">
                <button
                    onClick={onRefresh}
                    className="admin-action-btn btn-icon--refresh"
                    data-help-id="action-icon-refresh"
                    type="button"
                />
                <button
                    onClick={onReset}
                    className="admin-action-btn btn-icon--reset"
                    data-help-id="action-icon-reset"
                    type="button"
                />
                <button
                    onClick={onSave}
                    disabled={isLoading}
                    className="admin-action-btn btn-icon--save"
                    data-help-id="action-icon-save"
                    type="button"
                />
            </div>
        </div>
    );
};
