import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { IItemDetails } from '../../../hooks/useItemDetails.js';

interface ImageColumnProps {
    item: IItemDetails;
    uniqueImages: string[];
    isMobileLayout?: boolean;
}

export const ImageColumn: React.FC<ImageColumnProps> = ({ item, uniqueImages, isMobileLayout = false }) => {
    const [carouselIndex, setCarouselIndex] = useState(0);
    const [previewImage, setPreviewImage] = useState<string | null>(null);

    const galleryImages = useMemo(() => uniqueImages.filter(Boolean), [uniqueImages]);
    const activeImage = galleryImages[carouselIndex] || '';

    useEffect(() => {
        if (carouselIndex > galleryImages.length - 1) {
            setCarouselIndex(0);
        }
    }, [carouselIndex, galleryImages.length]);

    const handleNextImage = (e: React.MouseEvent<HTMLButtonElement>) => {
        e.stopPropagation();
        if (galleryImages.length <= 1) return;
        setCarouselIndex((prev) => (prev + 1) % galleryImages.length);
    };

    const handlePrevImage = (e: React.MouseEvent<HTMLButtonElement>) => {
        e.stopPropagation();
        if (galleryImages.length <= 1) return;
        setCarouselIndex((prev) => (prev - 1 + galleryImages.length) % galleryImages.length);
    };

    const openImagePreview = (imagePath: string) => {
        if (!imagePath) return;
        setPreviewImage(imagePath);
    };

    useEffect(() => {
        if (!previewImage) return;
        const handleEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setPreviewImage(null);
            }
        };
        window.addEventListener('keydown', handleEscape);
        return () => window.removeEventListener('keydown', handleEscape);
    }, [previewImage]);

    const lightboxPreview = previewImage && typeof document !== 'undefined'
        ? createPortal(
            <div
                className="fixed inset-0 flex items-center justify-center bg-black/90 p-4"
                style={{ zIndex: 'calc(var(--wf-z-modal) + 1)' }}
                onClick={() => setPreviewImage(null)}
            >
                <button
                    type="button"
                    className="absolute right-4 top-4 rounded-full border border-white/25 bg-black/60 px-3 py-2 text-sm font-bold uppercase tracking-[0.08em] text-white transition hover:bg-black/80"
                    onClick={() => setPreviewImage(null)}
                    aria-label="Close image preview"
                >
                    Close
                </button>
                <img
                    src={previewImage}
                    alt={`${item.name} enlarged preview`}
                    className="max-h-[90vh] max-w-[90vw] rounded-xl object-contain shadow-2xl"
                    onClick={(event) => event.stopPropagation()}
                />
            </div>,
            document.body
        )
        : null;

    if (!isMobileLayout) {
        const slot1 = galleryImages[0];
        const carouselItems = galleryImages.slice(1);

        return (
            <>
                <div className="image-column" style={{
                    width: '50%',
                    flex: '0 0 50%',
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    justifyContent: 'flex-start',
                    padding: '60px',
                    borderRight: '1px solid #f0f0f0'
                }}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '30px', width: '100%' }}>
                        <div style={{ width: '100%', height: '480px', position: 'relative', display: 'flex', alignItems: 'center', justifyContent: 'center', backgroundColor: 'white', borderRadius: '24px', overflow: 'hidden', border: '1px solid #f0f0f0', flexShrink: 0, boxShadow: '0 4px 12px rgba(0,0,0,0.03)' }}>
                            {slot1 ? (
                                <img
                                    src={slot1}
                                    alt={item.name}
                                    style={{ maxWidth: '90%', maxHeight: '90%', objectFit: 'contain', display: 'block', cursor: 'zoom-in' }}
                                    loading="eager"
                                    onClick={() => openImagePreview(slot1)}
                                />
                            ) : (
                                <div style={{ color: '#e5e7eb', textAlign: 'center' }}>
                                    <span className="btn-icon--shopping-cart" style={{ fontSize: '80px', margin: '0 auto 20px', display: 'block' }} aria-hidden="true" />
                                    <div style={{ fontSize: '12px', fontWeight: '900', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.1em' }}>Image Not Available</div>
                                </div>
                            )}
                            <div style={{ position: 'absolute', top: '20px', left: '20px', backgroundColor: 'rgba(135, 172, 58, 0.9)', color: 'white', padding: '6px 14px', borderRadius: '12px', fontSize: '10px', fontWeight: '900', textTransform: 'uppercase', letterSpacing: '0.1em', backdropFilter: 'blur(4px)' }}>Primary View</div>
                        </div>

                        {carouselItems.length > 0 && (
                            <div style={{ width: '100%', height: '480px', position: 'relative', display: 'flex', alignItems: 'center', justifyContent: 'center', backgroundColor: 'white', borderRadius: '24px', overflow: 'hidden', border: '1px solid #f0f0f0', flexShrink: 0, boxShadow: '0 4px 12px rgba(0,0,0,0.03)' }}>
                                <img
                                    src={carouselItems[carouselIndex % carouselItems.length]}
                                    alt={`${item.name} detail ${carouselIndex + 1}`}
                                    style={{ maxWidth: '85%', maxHeight: '85%', objectFit: 'contain', display: 'block', transition: 'all 0.4s ease', cursor: 'zoom-in' }}
                                    onClick={() => openImagePreview(carouselItems[carouselIndex % carouselItems.length])}
                                />

                                <div style={{ position: 'absolute', top: '20px', left: '20px', backgroundColor: 'rgba(75, 85, 99, 0.9)', color: 'white', padding: '6px 14px', borderRadius: '12px', fontSize: '10px', fontWeight: '900', textTransform: 'uppercase', letterSpacing: '0.1em', backdropFilter: 'blur(4px)' }}>
                                    {carouselItems.length > 1 ? `Image ${(carouselIndex % carouselItems.length) + 1} of ${carouselItems.length}` : 'Detail View'}
                                </div>

                                {carouselItems.length > 1 && (
                                    <>
                                        <div style={{ position: 'absolute', inset: '0', display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 15px', pointerEvents: 'none' }}>
                                            <button
                                                onClick={handlePrevImage}
                                                style={{ pointerEvents: 'auto', backgroundColor: 'rgba(255,255,255,0.9)', border: 'none', borderRadius: '50%', width: '40px', height: '40px', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}
                                                type="button"
                                            >
                                                <span className="btn-icon--previous" style={{ fontSize: '20px' }} aria-hidden="true" />
                                            </button>
                                            <button
                                                onClick={handleNextImage}
                                                style={{ pointerEvents: 'auto', backgroundColor: 'rgba(255,255,255,0.9)', border: 'none', borderRadius: '50%', width: '40px', height: '40px', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}
                                                type="button"
                                            >
                                                <span className="btn-icon--next" style={{ fontSize: '20px' }} aria-hidden="true" />
                                            </button>
                                        </div>
                                        <div style={{ position: 'absolute', bottom: '20px', left: '50%', transform: 'translateX(-50%)', display: 'flex', gap: '6px' }}>
                                            {carouselItems.map((_, i) => (
                                                <div key={i} style={{ width: '6px', height: '6px', borderRadius: '50%', backgroundColor: i === (carouselIndex % carouselItems.length) ? '#87ac3a' : 'rgba(0,0,0,0.2)', transition: 'all 0.3s ease' }} />
                                            ))}
                                        </div>
                                    </>
                                )}
                            </div>
                        )}

                        {(item.usage_tips || item.features) && (
                            <div style={{ padding: '24px', backgroundColor: '#ffffff', borderRadius: '24px', border: '1px dashed #e5e7eb', color: '#4b5563', fontSize: '15px', textAlign: 'center', fontStyle: 'italic', width: '100%', lineHeight: '1.6', position: 'relative', marginTop: '10px' }}>
                                <div style={{ position: 'absolute', top: '-10px', left: '50%', transform: 'translateX(-50%)', backgroundColor: '#f9fafb', padding: '0 12px', color: '#9ca3af', fontSize: '10px', fontWeight: '900', textTransform: 'uppercase', letterSpacing: '0.2em' }}>Whimsical Note</div>
                                "{item.usage_tips || (item.features ? item.features.split('\n')[0] : 'Hand-crafted quality.')}"
                            </div>
                        )}
                    </div>
                </div>
                {lightboxPreview}
            </>
        );
    }

    return (
        <>
            <div className="image-column w-full border-b border-slate-200 bg-slate-50/60 md:w-1/2 md:flex-[0_0_50%] md:border-b-0 md:border-r">
                <div className="image-column-inner flex w-full flex-col gap-4 p-4 sm:gap-5 sm:p-6 md:gap-6 md:p-10">
                    <div className="image-slot-card image-slot-primary relative flex h-64 w-full shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:h-80 md:h-[460px]">
                        {activeImage ? (
                            <img
                                src={activeImage}
                                alt={item.name}
                                className="block max-h-[90%] max-w-[90%] cursor-zoom-in object-contain"
                                loading="eager"
                                onClick={() => openImagePreview(activeImage)}
                            />
                        ) : (
                            <div className="text-center text-slate-300">
                                <span className="btn-icon--shopping-cart mx-auto mb-4 block text-6xl" aria-hidden="true" />
                                <div className="text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">Image Not Available</div>
                            </div>
                        )}
                        <div className="absolute left-3 top-3 rounded-full bg-[var(--brand-primary)]/90 px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-white">
                            {galleryImages.length > 1 ? `Image ${carouselIndex + 1} of ${galleryImages.length}` : 'Primary View'}
                        </div>
                        {galleryImages.length > 1 && (
                            <div className="pointer-events-none absolute inset-x-0 top-1/2 flex -translate-y-1/2 items-center justify-between px-2 sm:px-3">
                                <button
                                    onClick={handlePrevImage}
                                    className="pointer-events-auto flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white/90 text-slate-600 shadow-sm transition hover:bg-white"
                                    type="button"
                                    aria-label="Previous image"
                                >
                                    <span className="btn-icon--previous text-lg" aria-hidden="true" />
                                </button>
                                <button
                                    onClick={handleNextImage}
                                    className="pointer-events-auto flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white/90 text-slate-600 shadow-sm transition hover:bg-white"
                                    type="button"
                                    aria-label="Next image"
                                >
                                    <span className="btn-icon--next text-lg" aria-hidden="true" />
                                </button>
                            </div>
                        )}
                    </div>

                    {galleryImages.length > 1 && (
                        <div className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                            {galleryImages.map((img, index) => (
                                <button
                                    key={`${img}-${index}`}
                                    type="button"
                                    onClick={() => {
                                        setCarouselIndex(index);
                                        openImagePreview(img);
                                    }}
                                    aria-label={`View image ${index + 1}`}
                                    className={`h-16 w-16 shrink-0 overflow-hidden rounded-xl border-2 transition sm:h-20 sm:w-20 ${
                                        carouselIndex === index
                                            ? 'border-[var(--brand-primary)] shadow-md'
                                            : 'border-slate-200 hover:border-slate-300'
                                    }`}
                                >
                                    <img src={img} alt={`${item.name} thumbnail ${index + 1}`} className="h-full w-full object-cover" loading="lazy" />
                                </button>
                            ))}
                        </div>
                    )}

                    {(item.usage_tips || item.features) && (
                        <div className="relative rounded-2xl border border-dashed border-slate-300 bg-white p-4 text-center text-sm italic leading-relaxed text-slate-600 sm:p-5 sm:text-[15px]">
                            <div className="absolute -top-2 left-1/2 -translate-x-1/2 bg-slate-50 px-2 text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">
                                Whimsical Note
                            </div>
                            "{item.usage_tips || (item.features ? item.features.split('\n')[0] : 'Hand-crafted quality.')}"
                        </div>
                    )}
                </div>
            </div>
            {lightboxPreview}
        </>
    );
};
