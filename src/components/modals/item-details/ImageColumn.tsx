import React, { useState } from 'react';
import { IItemDetails } from '../../../hooks/useItemDetails.js';

interface ImageColumnProps {
    item: IItemDetails;
    uniqueImages: string[];
}

export const ImageColumn: React.FC<ImageColumnProps> = ({ item, uniqueImages }) => {
    const [carouselIndex, setCarouselIndex] = useState(0);

    const slot1 = uniqueImages[0];
    const carouselItems = uniqueImages.slice(1);

    const handleNextImage = (e: React.MouseEvent) => {
        e.stopPropagation();
        setCarouselIndex(prev => (prev + 1) % carouselItems.length);
    };

    const handlePrevImage = (e: React.MouseEvent) => {
        e.stopPropagation();
        setCarouselIndex(prev => (prev - 1 + carouselItems.length) % carouselItems.length);
    };

    return (
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
            <div className="image-column-inner" style={{ display: 'flex', flexDirection: 'column', gap: '30px', width: '100%' }}>
                {/* Slot 1: Fixed Primary */}
                <div className="image-slot-card image-slot-primary" style={{ width: '100%', height: '480px', position: 'relative', display: 'flex', alignItems: 'center', justifyContent: 'center', backgroundColor: 'white', borderRadius: '24px', overflow: 'hidden', border: '1px solid #f0f0f0', flexShrink: 0, boxShadow: '0 4px 12px rgba(0,0,0,0.03)' }}>
                    {slot1 ? (
                        <img
                            src={slot1}
                            alt={item.name}
                            style={{ maxWidth: '90%', maxHeight: '90%', objectFit: 'contain', display: 'block' }}
                            loading="eager"
                        />
                    ) : (
                        <div style={{ color: '#e5e7eb', textAlign: 'center' }}>
                            <span className="btn-icon--shopping-cart" style={{ fontSize: '80px', margin: '0 auto 20px', display: 'block' }} aria-hidden="true" />
                            <div style={{ fontSize: '12px', fontWeight: '900', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.1em' }}>Image Not Available</div>
                        </div>
                    )}
                    <div style={{ position: 'absolute', top: '20px', left: '20px', backgroundColor: 'rgba(135, 172, 58, 0.9)', color: 'white', padding: '6px 14px', borderRadius: '12px', fontSize: '10px', fontWeight: '900', textTransform: 'uppercase', letterSpacing: '0.1em', backdropFilter: 'blur(4px)' }}>Primary View</div>
                </div>

                {/* Slot 2: Carousel for remaining (if available) */}
                {carouselItems.length > 0 && (
                    <div className="image-slot-card image-slot-carousel" style={{ width: '100%', height: '480px', position: 'relative', display: 'flex', alignItems: 'center', justifyContent: 'center', backgroundColor: 'white', borderRadius: '24px', overflow: 'hidden', border: '1px solid #f0f0f0', flexShrink: 0, boxShadow: '0 4px 12px rgba(0,0,0,0.03)' }}>
                        <img
                            src={carouselItems[carouselIndex]}
                            alt={`${item.name} detail ${carouselIndex + 1}`}
                            style={{ maxWidth: '85%', maxHeight: '85%', objectFit: 'contain', display: 'block', transition: 'all 0.4s ease' }}
                        />

                        <div style={{ position: 'absolute', top: '20px', left: '20px', backgroundColor: 'rgba(75, 85, 99, 0.9)', color: 'white', padding: '6px 14px', borderRadius: '12px', fontSize: '10px', fontWeight: '900', textTransform: 'uppercase', letterSpacing: '0.1em', backdropFilter: 'blur(4px)' }}>
                            {carouselItems.length > 1 ? `Image ${carouselIndex + 1} of ${carouselItems.length}` : 'Detail View'}
                        </div>

                        {carouselItems.length > 1 && (
                            <>
                                <div style={{ position: 'absolute', inset: '0', display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 15px', pointerEvents: 'none' }}>
                                    <button
                                        onClick={handlePrevImage}
                                        style={{ pointerEvents: 'auto', backgroundColor: 'rgba(255,255,255,0.9)', border: 'none', borderRadius: '50%', width: '40px', height: '40px', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}
                                    >
                                        <span className="btn-icon--previous" style={{ fontSize: '20px' }} aria-hidden="true" />
                                    </button>
                                    <button
                                        onClick={handleNextImage}
                                        style={{ pointerEvents: 'auto', backgroundColor: 'rgba(255,255,255,0.9)', border: 'none', borderRadius: '50%', width: '40px', height: '40px', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}
                                    >
                                        <span className="btn-icon--next" style={{ fontSize: '20px' }} aria-hidden="true" />
                                    </button>
                                </div>
                                <div style={{ position: 'absolute', bottom: '20px', left: '50%', transform: 'translateX(-50%)', display: 'flex', gap: '6px' }}>
                                    {carouselItems.map((_, i) => (
                                        <div key={i} style={{ width: '6px', height: '6px', borderRadius: '50%', backgroundColor: i === carouselIndex ? '#87ac3a' : 'rgba(0,0,0,0.2)', transition: 'all 0.3s ease' }} />
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
    );
};
