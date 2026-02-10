import React, { useEffect, useState } from 'react';
import { useInventoryImages } from '../../../hooks/admin/useInventoryImages.js';
import { IItemImage } from '../../../types/index.js';
import { useModalContext } from '../../../context/ModalContext.js';
import { ApiClient } from '../../../core/ApiClient.js';

interface ImageGalleryProps {
    sku: string;
    isEdit?: boolean;
    isReadOnly?: boolean;
    onImagesChanged?: (images: IItemImage[]) => void;
    variant?: 'full' | 'upload-only';
}

export const ImageGallery: React.FC<ImageGalleryProps> = ({
    sku,
    isEdit = false,
    isReadOnly = false,
    onImagesChanged,
    variant = 'full'
}) => {
    const { images, isLoading, uploadProgress, error, deleteImage, setPrimaryImage, uploadImages, fetchImages } = useInventoryImages(sku);
    const { confirm: confirmModal } = useModalContext();
    const [isViewerOpen, setIsViewerOpen] = useState(false);
    const [selectedImage, setSelectedImage] = useState<IItemImage | null>(null);
    const [isProcessingAll, setIsProcessingAll] = useState(false);

    useEffect(() => {
        onImagesChanged?.(images);
    }, [images, onImagesChanged]);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files.length > 0) {
            uploadImages(e.target.files);
        }
    };

    const openViewer = (image: IItemImage) => {
        setSelectedImage(image);
        setIsViewerOpen(true);
    };

    const closeViewer = () => {
        setIsViewerOpen(false);
        setSelectedImage(null);
    };

    const handleProcessExistingImagesWithAI = async () => {
        if (!sku || images.length === 0 || isProcessingAll) return;

        const confirmed = await confirmModal({
            title: 'AI Crop/Compress All Images?',
            message: `Run AI crop/compress for all ${images.length} image${images.length === 1 ? '' : 's'} on ${sku}?`,
            subtitle: 'This updates existing image files to optimized crop/compression outputs.',
            confirmText: 'Process Images',
            cancelText: 'Cancel',
            confirmStyle: 'warning',
            iconKey: 'warning'
        });

        if (!confirmed) return;

        setIsProcessingAll(true);
        try {
            const response = await ApiClient.get<{
                success?: boolean;
                processed?: number;
                skipped?: number;
                errors?: string[];
                error?: string;
            }>('/api/run_image_analysis.php', { sku, force: 1 });

            if (!response?.success) {
                throw new Error(response?.error || 'AI image processing failed');
            }

            await fetchImages();
            const processed = response.processed ?? 0;
            const skipped = response.skipped ?? 0;
            const errorCount = Array.isArray(response.errors) ? response.errors.length : 0;
            window.WFToast?.success?.(`AI crop/compress complete: ${processed} processed, ${skipped} skipped, ${errorCount} errors.`);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to process images';
            window.WFToast?.error?.(message);
        } finally {
            setIsProcessingAll(false);
        }
    };

    const handleDeleteImage = async (imageId: number) => {
        const confirmed = await confirmModal({
            title: 'Delete This Image?',
            message: 'This will permanently remove the image from this item.',
            subtitle: 'This action cannot be undone.',
            confirmText: 'Delete Image',
            cancelText: 'Cancel',
            confirmStyle: 'danger',
            iconKey: 'warning'
        });

        if (!confirmed) return;
        await deleteImage(imageId);
    };

    if (!sku) return null;

    if (variant === 'upload-only') {
        return (
            <div>
                {error && <div className="p-2 mb-2 bg-[var(--brand-error)]/5 border border-[var(--brand-error)]/20 text-[var(--brand-error)] text-sm rounded">{error}</div>}
                {!isReadOnly && (
                    <div className="multi-image-upload-section">
                        <input
                            type="file"
                            id="singleStepImageUpload"
                            multiple
                            accept="image/*"
                            className="hidden"
                            onChange={handleFileChange}
                        />
                        <label
                            htmlFor="singleStepImageUpload"
                            className="inline-flex items-center gap-2 rounded-lg px-4 py-2 font-bold uppercase tracking-widest text-xs bg-emerald-100 text-emerald-800 hover:bg-emerald-200 transition-colors cursor-pointer"
                        >
                            Upload Image
                        </label>
                        {uploadProgress > 0 && (
                            <div className="mt-3 text-xs text-slate-600">Uploading ({uploadProgress}%)...</div>
                        )}
                    </div>
                )}
                {images.length > 0 && (
                    <div className="mt-3 rounded-xl border border-slate-200 bg-white p-2 max-w-xs">
                        <img
                            src={`/${images[0].image_path}`}
                            alt={images[0].alt_text || `Inventory image for item ${sku}`}
                            className="w-full h-36 object-cover rounded-lg"
                            loading="lazy"
                        />
                    </div>
                )}
            </div>
        );
    }

    return (
        <div>
            <div id="imagesSection" className="images-section-container">
                {error && <div className="p-2 mb-2 bg-[var(--brand-error)]/5 border border-[var(--brand-error)]/20 text-[var(--brand-error)] text-sm rounded">{error}</div>}

                <div id="currentImagesContainer" className="current-images-section">
                    <div className="flex items-center justify-between mb-2">
                        <div className="text-sm text-gray-600">
                            {isLoading ? 'Loading images...' : `Current Images (${images.length}):`}
                        </div>
                        {isEdit && !isReadOnly && (
                            <button
                                type="button"
                                className="btn btn-secondary px-3 py-1 bg-transparent border-0 text-amber-700 hover:bg-amber-100 transition-all text-xs font-bold uppercase tracking-wider"
                                onClick={() => { void handleProcessExistingImagesWithAI(); }}
                                disabled={isProcessingAll || isLoading || images.length === 0}
                            >
                                {isProcessingAll ? 'Processing...' : 'AI Crop/Compress All'}
                            </button>
                        )}
                    </div>

                    <div id="currentImagesList" className="wf-img-grid grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        {images.map((image) => (
                            <div key={`${image.id}-${image.image_path}`} className="wf-img-card group relative border rounded-lg overflow-hidden bg-white shadow-sm hover:shadow-md transition-shadow">
                                <button
                                    type="button"
                                    className="wf-img-wrap aspect-square relative cursor-pointer w-full text-left"
                                    onClick={() => openViewer(image)}
                                    aria-label={`View ${image.image_path.split('/').pop()}`}
                                >
                                    <img
                                        src={`/${image.image_path}`}
                                        alt={image.alt_text || `Inventory image for item ${sku}`}
                                        className="w-full h-full object-cover"
                                        loading="lazy"
                                        onError={(e) => {
                                            (e.target as HTMLImageElement).src = '/images/items/placeholder.webp';
                                        }}
                                    />
                                    {image.is_primary && (
                                        <div className="wf-badge-primary absolute top-1 right-1 bg-[var(--brand-accent)] text-white text-[10px] px-1.5 py-0.5 rounded shadow-sm">
                                            Primary
                                        </div>
                                    )}
                                </button>
                                <div className="wf-img-meta p-1 text-[10px] text-gray-500 truncate border-t" data-help-id="inventory-image-path">
                                    {image.image_path.split('/').pop()}
                                </div>
                                {!isReadOnly && (
                                    <div className="wf-img-actions absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center gap-2 transition-opacity">
                                        {!image.is_primary && (
                                            <button
                                                type="button"
                                                onClick={() => setPrimaryImage(image.id)}
                                                className="admin-action-btn btn-icon--star"
                                                data-help-id={image.is_primary ? 'inventory-image-primary' : 'inventory-image-make-primary'}
                                            />
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => openViewer(image)}
                                            className="admin-action-btn btn-icon--view"
                                            data-help-id="common-view"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => { void handleDeleteImage(image.id); }}
                                            className="admin-action-btn btn-icon--delete"
                                            data-help-id="inventory-image-delete"
                                        />
                                    </div>
                                )}
                            </div>
                        ))}
                        {images.length === 0 && !isLoading && (
                            <div className="text-gray-500 text-sm col-span-full py-8 text-center bg-gray-50 rounded-lg border-2 border-dashed">
                                No images uploaded yet
                            </div>
                        )}
                    </div>
                </div>

                {!isReadOnly && (
                    <div className="multi-image-upload-section mt-4 pt-4 border-t border-gray-200">
                        <input
                            type="file"
                            id="multiImageUpload"
                            multiple
                            accept="image/*"
                            className="hidden"
                            onChange={handleFileChange}
                        />
                        <div className="upload-controls">
                            <div className="flex gap-3 flex-wrap items-center mb-3">
                                <label
                                    htmlFor="multiImageUpload"
                                    className="btn btn-primary cursor-pointer flex items-center gap-2 bg-transparent border-0 text-emerald-700 hover:bg-emerald-100 px-4 py-2 rounded-lg transition-all font-bold uppercase tracking-widest text-xs"
                                >
                                    Upload Images
                                </label>
                                <div className="text-sm text-gray-500">
                                    Maximum file size: 10MB per image. Supported formats: PNG, JPG, JPEG, WebP, GIF
                                </div>
                            </div>

                            {uploadProgress > 0 && (
                                <div id="uploadProgress" className="mt-3">
                                    <div className="text-sm text-gray-600 mb-2">Uploading images ({uploadProgress}%)...</div>
                                    <div className="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            className="bg-[var(--brand-primary)] h-2 rounded-full transition-all duration-300 wf-upload-progress-bar"
                                            style={{ '--upload-width': `${uploadProgress}%` } as React.CSSProperties}
                                        />
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* Simple Image Viewer Modal */}
            {isViewerOpen && selectedImage && (
                <div
                    className="fixed inset-0 z-[var(--wf-z-topmost)] flex items-center justify-center p-4 bg-black/80"
                    onClick={closeViewer}
                    role="presentation"
                >
                    <div
                        className="viewer-content relative bg-black/90 p-4 rounded-xl shadow-2xl max-w-4xl w-full"
                        onClick={(e) => e.stopPropagation()}
                        role="presentation"
                    >
                        <div className="flex items-center justify-between p-3 border-b">
                            <h3 className="text-sm font-medium truncate">{selectedImage.image_path.split('/').pop()}</h3>
                            <button
                                onClick={closeViewer}
                                className="admin-action-btn btn-icon--close"
                                type="button"
                                data-help-id="common-close"
                            />
                        </div>
                        <div className="p-4 flex items-center justify-center bg-gray-100 min-h-[300px]">
                            <img
                                src={`/${selectedImage.image_path}`}
                                alt={selectedImage.alt_text || `Full view of inventory image for item ${sku}`}
                                className="max-h-[70vh] object-contain"
                                loading="lazy"
                            />
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};
