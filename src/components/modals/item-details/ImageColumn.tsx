import React, { useEffect, useMemo, useState } from 'react';
import { IItemDetails } from '../../../hooks/useItemDetails.js';

interface ImageColumnProps {
    item: IItemDetails;
    uniqueImages: string[];
}

export const ImageColumn: React.FC<ImageColumnProps> = ({ item, uniqueImages }) => {
    const [carouselIndex, setCarouselIndex] = useState(0);

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

    return (
        <div className="image-column w-full border-b border-slate-200 bg-slate-50/60 md:w-1/2 md:flex-[0_0_50%] md:border-b-0 md:border-r">
            <div className="image-column-inner flex w-full flex-col gap-4 p-4 sm:gap-5 sm:p-6 md:gap-6 md:p-10">
                <div className="image-slot-card image-slot-primary relative flex h-64 w-full shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:h-80 md:h-[460px]">
                    {activeImage ? (
                        <img
                            src={activeImage}
                            alt={item.name}
                            className="block max-h-[90%] max-w-[90%] object-contain"
                            loading="eager"
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
                                onClick={() => setCarouselIndex(index)}
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
    );
};
