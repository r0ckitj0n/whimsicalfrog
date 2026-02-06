import React from 'react';
import { MappingForm } from '../../area-mapper/MappingForm.js';
import { UnifiedMappingsTable } from '../../area-mapper/UnifiedMappingsTable.js';
import { IAreaMappingsHook } from '../../../../../types/room.js';
import { IAreaMapping } from '../../../../../types/index.js';

interface ShortcutsTabProps {
    mappings: IAreaMappingsHook;
    selectedRoom: string;
    newMapping: Partial<IAreaMapping>;
    setNewMapping: React.Dispatch<React.SetStateAction<Partial<IAreaMapping>>>;
    destinationOptions: React.ReactNode[];
    onContentSave: (e?: React.FormEvent) => Promise<void>;
    onContentUpload: (e: React.ChangeEvent<HTMLInputElement>, field: 'content_image' | 'link_image') => Promise<void>;
    onContentEdit: (mapping: IAreaMapping) => void;
    onContentConvert: (area: string, sku: string) => Promise<void>;
}

export const ShortcutsTab: React.FC<ShortcutsTabProps> = ({
    mappings,
    selectedRoom,
    newMapping,
    setNewMapping,
    destinationOptions,
    onContentSave,
    onContentUpload,
    onContentEdit,
    onContentConvert
}) => {
    return (
        <div className="p-8 lg:p-10 overflow-y-auto flex-1">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div className="lg:col-span-1">
                    <MappingForm
                        mapping={newMapping}
                        setMapping={setNewMapping}
                        availableAreas={mappings.availableAreas}
                        doorDestinations={mappings.doorDestinations}
                        destinationOptions={destinationOptions}
                        items={mappings.unrepresentedItems}
                        onSubmit={onContentSave}
                        onUpload={onContentUpload}
                        isLoading={mappings.isLoading}
                    />
                </div>
                <div className="lg:col-span-2">
                    <UnifiedMappingsTable
                        explicitMappings={mappings.explicitMappings}
                        derivedMappings={mappings.derivedMappings}
                        roomOptions={mappings.roomOptions}
                        onEdit={onContentEdit}
                        onDelete={(id: number) => mappings.deleteMapping(id, selectedRoom)}
                        onConvert={onContentConvert}
                    />
                </div>
            </div>
        </div>
    );
};
