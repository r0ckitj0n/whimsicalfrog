import React from 'react';

interface ToolsetSectionProps {
    onLoadActive: () => void;
    onSaveMap: () => void;
    onToggleTool: (tool: 'select' | 'create') => void;
    activeTool: 'select' | 'create';
    onClearAll: () => void;
}

export const ToolsetSection: React.FC<ToolsetSectionProps> = ({
    onLoadActive,
    onSaveMap,
    onToggleTool,
    activeTool,
    onClearAll
}) => {
    return (
        <section className="space-y-4">
            <div className="flex items-center gap-2 text-xs font-black text-gray-400 uppercase tracking-widest pb-2 border-b">
                Toolset
            </div>
            <div className="grid grid-cols-4 gap-2">
                <button
                    onClick={onLoadActive}
                    className="admin-action-btn btn-icon--download"
                    data-help-id="map-load-active"
                />
                <button
                    onClick={onSaveMap}
                    className="admin-action-btn btn-icon--save"
                    data-help-id="map-save"
                />
                <button
                    onClick={() => onToggleTool('create')}
                    className={`admin-action-btn ${activeTool === 'create' ? 'btn-icon--power-on' : 'btn-icon--add'}`}
                    data-help-id="map-add-area"
                />
                <button
                    onClick={onClearAll}
                    className="admin-action-btn btn-icon--delete"
                    data-help-id="map-clear-all"
                />
            </div>
        </section>
    );
};
