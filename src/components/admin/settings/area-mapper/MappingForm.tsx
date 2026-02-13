import React from 'react';
import { IAreaMapping, MappingType, IItem } from '../../../../types/index.js';
import { IAreaOption, IDoorDestination } from '../../../../types/room.js';
import { MAPPING_TYPE } from '../../../../core/constants.js';
import type { IShortcutSignAsset } from '../../../../types/room-shortcuts.js';

interface MappingFormProps {
    mapping: Partial<IAreaMapping>;
    setMapping: (m: Partial<IAreaMapping>) => void;
    onSubmit: (e: React.FormEvent) => void;
    availableAreas: IAreaOption[];
    doorDestinations: IDoorDestination[];
    destinationOptions: React.ReactNode[];
    items: IItem[];
    isLoading: boolean;
    onUpload: (e: React.ChangeEvent<HTMLInputElement>, field: 'content_image' | 'link_image') => void;
    onGenerateImage: () => Promise<void>;
    onPreviewImage: (url: string) => void;
    onDeploySavedImage: (assetId: number) => Promise<void>;
    onDeleteSavedImage: (assetId: number) => Promise<void>;
    isGeneratingImage: boolean;
}

export const MappingForm: React.FC<MappingFormProps> = ({
    mapping,
    setMapping,
    onSubmit,
    availableAreas,
    doorDestinations,
    destinationOptions,
    items,
    isLoading,
    onUpload,
    onGenerateImage,
    onPreviewImage,
    onDeploySavedImage,
    onDeleteSavedImage,
    isGeneratingImage
}) => {
    const assets = (mapping.shortcut_images || []) as IShortcutSignAsset[];
    const activeAsset = assets.find(a => a.is_active === 1) || null;
    const [selectedAssetId, setSelectedAssetId] = React.useState<number>(activeAsset?.id || assets[0]?.id || 0);

    React.useEffect(() => {
        const nextActive = assets.find(a => a.is_active === 1) || assets[0] || null;
        setSelectedAssetId(prev => {
            if (prev && assets.some(a => a.id === prev)) return prev;
            return nextActive?.id || 0;
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [assets.length, activeAsset?.id]);

    const selectedAsset = assets.find(a => a.id === selectedAssetId) || null;

    return (
        <form onSubmit={onSubmit} className="p-4 bg-gray-50 border rounded-xl space-y-4">
            <h4 className="font-bold text-gray-700 text-sm uppercase tracking-wider mb-2">
                {mapping.id ? 'Edit Mapping' : 'Create New Mapping'}
            </h4>

            <div className="space-y-1">
                <label className="text-[10px] font-black text-gray-400 uppercase">Area Selector</label>
                <select
                    value={mapping.area_selector || ''}
                    onChange={e => setMapping({ ...mapping, area_selector: e.target.value })}
                    className="w-full p-2 border rounded-lg text-sm bg-white"
                    required
                >
                    <option value="">Select Area...</option>
                    <option value="-beginning-">Auto-place @ Start</option>
                    <option value="-end-">Auto-place @ End</option>
                    {availableAreas.map(a => <option key={a.val} value={a.val}>{a.label}</option>)}
                </select>
            </div>

            <div className="space-y-1">
                <label className="text-[10px] font-black text-gray-400 uppercase">Mapping Type</label>
                <select
                    value={mapping.mapping_type || MAPPING_TYPE.ITEM}
                    onChange={e => setMapping({ ...mapping, mapping_type: e.target.value as MappingType, content_target: '' })}
                    className="w-full p-2 border rounded-lg text-sm bg-white"
                >
                    <option value={MAPPING_TYPE.ITEM}>Item</option>
                    <option value={MAPPING_TYPE.CATEGORY}>Category</option>
                    <option value={MAPPING_TYPE.LINK}>External Link</option>
                    <option value={MAPPING_TYPE.CONTENT}>Shortcut</option>
                    <option value={MAPPING_TYPE.BUTTON}>Button</option>
                    <option value={MAPPING_TYPE.PAGE}>Inner Page</option>
                    <option value={MAPPING_TYPE.MODAL}>Modal</option>
                    <option value={MAPPING_TYPE.ACTION}>Global Action</option>
                </select>
            </div>

            <div className="space-y-1">
                <label className="text-[10px] font-black text-gray-400 uppercase">Destination</label>
                {mapping.mapping_type === MAPPING_TYPE.LINK ? (
                    <div className="space-y-2">
                        <input
                            type="text"
                            value={mapping.link_url || ''}
                            onChange={e => setMapping({ ...mapping, link_url: e.target.value })}
                            placeholder="https://..."
                            className="w-full p-2 border rounded-lg text-sm bg-white"
                            required
                        />
                    </div>
                ) : (
                    <select
                        value={mapping.content_target || ''}
                        onChange={e => {
                            const val = e.target.value;
                            const update: Partial<IAreaMapping> = { content_target: val };

                            // Auto-set labels or images if found
                            if (mapping.mapping_type === MAPPING_TYPE.ITEM) {
                                update.item_sku = val;
                            } else if (mapping.mapping_type === MAPPING_TYPE.CATEGORY) {
                                update.category_id = parseInt(val);
                            }

                            const door = doorDestinations.find(d => d.target === val);
                            if (door?.image) update.content_image = door.image;

                            if (mapping.mapping_type === MAPPING_TYPE.ITEM) {
                                const item = items.find(i => i.sku === val);
                                if (item?.image_url) update.image_url = item.image_url;
                            }

                            setMapping({ ...mapping, ...update });
                        }}
                        className="w-full p-2 border rounded-lg text-sm bg-white"
                        required
                    >
                        <option value="">Select Target...</option>
                        {destinationOptions}
                    </select>
                )}
            </div>

            {(['link', 'button', 'page', 'modal', 'action', 'content'] as MappingType[]).includes(mapping.mapping_type as MappingType) && (
                <div className="grid grid-cols-2 gap-3">
                    <div className="space-y-1">
                        <label className="text-[10px] font-black text-gray-400 uppercase">Label</label>
                        <input
                            type="text"
                            value={mapping.link_label || ''}
                            onChange={e => setMapping({ ...mapping, link_label: e.target.value })}
                            placeholder="Button Text"
                            className="w-full p-2 border rounded-lg text-sm bg-white"
                        />
                    </div>
                    <div className="space-y-1">
                        <label className="text-[10px] font-black text-gray-400 uppercase">Icon</label>
                        <div className="flex gap-2">
                            <input
                                type="text"
                                value={mapping.link_icon || ''}
                                onChange={e => setMapping({ ...mapping, link_icon: e.target.value })}
                                placeholder="🏠, fa-home"
                                className="flex-1 p-2 border rounded-lg text-sm bg-white"
                            />
                            {mapping.link_icon && (
                                <div className="w-10 h-10 border rounded flex items-center justify-center bg-gray-100 text-lg">
                                    {mapping.link_icon.includes('fa-') ? '⚙️' : mapping.link_icon}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {(['content', 'item', 'link', 'button', 'page', 'modal', 'action'] as MappingType[]).includes(mapping.mapping_type as MappingType) && (
                <div className="space-y-3 pt-2">
                    <label className="text-[10px] font-black text-gray-400 uppercase">Image Override</label>
                    <div className="flex gap-4 items-start">
                        <div className="w-20 h-20 border-2 border-dashed rounded-lg flex items-center justify-center bg-white overflow-hidden flex-shrink-0">
                            {mapping.content_image || mapping.image_url || mapping.link_image ? (
                                <button
                                    type="button"
                                    className="w-full h-full"
                                    onClick={() => {
                                        const imageUrl = String(mapping.content_image || mapping.image_url || mapping.link_image || '').trim();
                                        if (imageUrl !== '') {
                                            onPreviewImage(imageUrl);
                                        }
                                    }}
                                    title="View larger"
                                >
                                    <img
                                        src={mapping.content_image || mapping.image_url || mapping.link_image}
                                        alt="Content preview"
                                        className="w-full h-full object-contain cursor-zoom-in"
                                        loading="lazy"
                                    />
                                </button>
                            ) : (
                                <span className="text-2xl text-gray-300">🖼️</span>
                            )}
                        </div>
                        <div className="flex-1 space-y-2">
                            <input
                                type="file"
                                id="aimNewImage"
                                className="hidden"
                                onChange={e => onUpload(e, 'content_image')}
                            />
                            <div className="flex flex-col gap-2">
                                <label
                                    htmlFor="aimNewImage"
                                    className="btn btn-secondary w-full text-center cursor-pointer transition-all text-[10px] font-black uppercase tracking-widest"
                                >
                                    Upload Image
                                </label>
                                <p className="text-[10px] text-slate-500 font-black uppercase tracking-widest text-center">or</p>
                                <button
                                    type="button"
                                    onClick={() => void onGenerateImage()}
                                    disabled={isLoading || isGeneratingImage}
                                    className="btn btn-secondary w-full text-center transition-all text-[10px] font-black uppercase tracking-widest disabled:opacity-60"
                                >
                                    {isGeneratingImage ? 'Generating...' : 'Generate Image'}
                                </button>
                                {(mapping.content_image || mapping.link_image) && (
                                    <button
                                        type="button"
                                        onClick={() => setMapping({ ...mapping, content_image: '', link_image: '' })}
                                        className="text-[9px] text-rose-500 font-bold uppercase underline"
                                    >
                                        Clear Image
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Saved sign images (shortcut mappings) */}
                    {mapping.id && (
                        <div className="mt-3 bg-white border border-slate-200 rounded-xl p-3 space-y-2">
                            <div className="flex items-center justify-between gap-2">
                                <div className="text-[10px] font-black uppercase tracking-widest text-slate-500">
                                    Saved Images
                                </div>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        className="btn btn-primary px-2 py-1 text-[9px] font-black uppercase tracking-widest disabled:opacity-60"
                                        onClick={() => selectedAsset && void onDeploySavedImage(selectedAsset.id)}
                                        disabled={!selectedAsset || selectedAsset.is_active === 1}
                                    >
                                        Deploy
                                    </button>
                                    <button
                                        type="button"
                                        className="admin-action-btn btn-icon--view"
                                        onClick={() => selectedAsset && onPreviewImage(selectedAsset.image_url)}
                                        disabled={!selectedAsset}
                                        title="View"
                                    />
                                    <button
                                        type="button"
                                        className="admin-action-btn btn-icon--delete"
                                        onClick={() => selectedAsset && void onDeleteSavedImage(selectedAsset.id)}
                                        disabled={!selectedAsset}
                                        title="Delete"
                                    />
                                </div>
                            </div>

                            <div className="max-h-44 overflow-y-auto pr-1">
                                {assets.length > 0 ? (
                                    <div className="grid grid-cols-4 gap-2">
                                        {assets.map((asset) => {
                                            const isActive = asset.is_active === 1;
                                            const isSelected = asset.id === selectedAssetId;
                                            return (
                                                <button
                                                    key={asset.id}
                                                    type="button"
                                                    onClick={() => setSelectedAssetId(asset.id)}
                                                    className={[
                                                        'rounded-lg border-2 bg-white p-1 text-left transition',
                                                        isActive ? 'border-[var(--brand-primary)]' : 'border-slate-200',
                                                        isSelected ? 'ring-2 ring-[var(--brand-secondary)] ring-offset-1' : 'hover:border-slate-300'
                                                    ].join(' ')}
                                                    title={isActive ? 'Active' : (asset.source || 'Saved')}
                                                >
                                                    <img
                                                        src={asset.image_url}
                                                        alt={asset.source || 'Saved sign'}
                                                        className="w-full h-16 object-contain"
                                                        loading="lazy"
                                                    />
                                                    {isActive && (
                                                        <div className="mt-1 text-[8px] font-black uppercase tracking-widest text-[var(--brand-primary)]">
                                                            Active
                                                        </div>
                                                    )}
                                                </button>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <div className="text-xs text-slate-400 italic">
                                        No saved images found for this shortcut yet.
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            )}

        </form>
    );
};
