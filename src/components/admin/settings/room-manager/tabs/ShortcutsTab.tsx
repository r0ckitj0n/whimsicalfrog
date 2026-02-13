import React from 'react';
import { MappingForm } from '../../area-mapper/MappingForm.js';
import { UnifiedMappingsTable } from '../../area-mapper/UnifiedMappingsTable.js';
import { IAreaMappingsHook } from '../../../../../types/room.js';
import { IAreaMapping } from '../../../../../types/index.js';
import { useModalContext } from '../../../../../context/ModalContext.js';

interface ShortcutsTabProps {
    mappings: IAreaMappingsHook;
    selectedRoom: string;
    newMapping: Partial<IAreaMapping>;
    setNewMapping: React.Dispatch<React.SetStateAction<Partial<IAreaMapping>>>;
    destinationOptions: React.ReactNode[];
    onContentSave: (e?: React.FormEvent) => Promise<void>;
    onContentUpload: (e: React.ChangeEvent<HTMLInputElement>, field: 'content_image' | 'link_image') => Promise<void>;
    onGenerateContentImage: () => Promise<void>;
    onPreviewContentImage: (mapping: IAreaMapping, url: string) => void;
    onContentEdit: (mapping: IAreaMapping) => void;
    onContentConvert: (area: string, sku: string) => Promise<void>;
    onToggleMappingActive: (id: number, currentActive: boolean | number) => Promise<void>;
    isGeneratingImage: boolean;
}

export const ShortcutsTab: React.FC<ShortcutsTabProps> = ({
    mappings,
    selectedRoom,
    newMapping,
    setNewMapping,
    destinationOptions,
    onContentSave,
    onContentUpload,
    onGenerateContentImage,
    onPreviewContentImage,
    onContentEdit,
    onContentConvert,
    onToggleMappingActive,
    isGeneratingImage
}) => {
    const { confirm } = useModalContext();

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
                        onGenerateImage={onGenerateContentImage}
                        onPreviewImage={(url) => {
                            if (newMapping.id) {
                                onPreviewContentImage(newMapping as IAreaMapping, url);
                            }
                        }}
                        isGeneratingImage={isGeneratingImage}
                        isLoading={mappings.isLoading}
                    />
                </div>
                <div className="lg:col-span-2">
                    <UnifiedMappingsTable
                        explicitMappings={mappings.explicitMappings}
                        derivedMappings={mappings.derivedMappings}
                        roomOptions={mappings.roomOptions}
                        onEdit={onContentEdit}
                        onToggleActive={onToggleMappingActive}
                        onDelete={async (id: number) => {
                            const confirmed = await confirm({
                                title: 'Delete Shortcut Mapping',
                                message: 'Delete this shortcut mapping? Active mappings will be deactivated; already-inactive mappings will be permanently removed.',
                                confirmText: 'Delete',
                                cancelText: 'Cancel',
                                confirmStyle: 'danger',
                                iconKey: 'warning'
                            });
                            if (!confirmed) return;

                            const ok = await mappings.deleteMapping(id, selectedRoom);
                            if (!ok) {
                                window.WFToast?.error?.('Failed to delete mapping');
                            } else {
                                window.WFToast?.success?.('Mapping deleted');
                            }
                        }}
                        onConvert={onContentConvert}
                        onPreviewImage={(mapping: IAreaMapping) => {
                            const imageUrl = String(mapping.content_image || mapping.image_url || mapping.link_image || '').trim();
                            if (!imageUrl) {
                                window.WFToast?.error?.('No image available for this shortcut');
                                return;
                            }
                            onPreviewContentImage(mapping, imageUrl);
                        }}
                    />
                </div>
            </div>
        </div>
    );
};
