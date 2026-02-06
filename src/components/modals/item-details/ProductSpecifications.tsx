import React, { useState } from 'react';
import { IItemDetails } from '../../../hooks/useItemDetails.js';

interface ProductSpecificationsProps {
    item: IItemDetails;
}

export const ProductSpecifications: React.FC<ProductSpecificationsProps> = ({ item }) => {
    const [isDetailsOpen, setIsDetailsOpen] = useState(false);

    return (
        <div style={{ marginTop: '40px' }}>
            <button
                onClick={() => setIsDetailsOpen(!isDetailsOpen)}
                style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '20px 28px', border: '2px solid #f3f4f6', borderRadius: '16px', fontSize: '17px', fontWeight: 'bold', color: '#4b5563', background: '#ffffff', cursor: 'pointer', transition: 'all 0.2s', outline: 'none' }}
            >
                <span>Product Specifications</span>
                <span className="btn-icon--down" style={{ transition: 'transform 0.3s', transform: isDetailsOpen ? 'rotate(180deg)' : 'none', fontSize: '16px' }} />
            </button>
            {isDetailsOpen && (
                <div style={{ marginTop: '16px', padding: '32px', backgroundColor: '#f9fafb', borderRadius: '20px', border: '1px solid #f3f4f6', fontSize: '15px', color: '#4b5563', boxShadow: 'inset 0 2px 8px rgba(0,0,0,0.03)' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '32px', marginBottom: '32px' }}>
                        <div>
                            <div style={{ fontWeight: '900', color: '#111827', marginBottom: '8px', textTransform: 'uppercase', letterSpacing: '0.05em', fontSize: '12px' }}>Reference SKU</div>
                            <div style={{ fontFamily: 'monospace', color: '#111827', fontSize: '14px', background: '#ffffff', padding: '8px 12px', borderRadius: '8px', border: '1px solid #eee' }}>{item.sku}</div>
                        </div>
                        <div>
                            <div style={{ fontWeight: '900', color: '#111827', marginBottom: '8px', textTransform: 'uppercase', letterSpacing: '0.05em', fontSize: '12px' }}>Category</div>
                            <div style={{ color: '#111827', fontSize: '14px', background: '#ffffff', padding: '8px 12px', borderRadius: '8px', border: '1px solid #eee' }}>{item.category || 'Uncategorized'}</div>
                        </div>
                    </div>

                    {[
                        { label: 'Key Features', content: item.features },
                        { label: 'Materials', content: item.materials },
                        { label: 'Care Instructions', content: item.care_instructions }
                    ].map((sec, i) => sec.content && (
                        <div key={i} style={{ marginBottom: i === 2 ? 0 : '24px' }}>
                            <div style={{ fontWeight: '900', color: '#111827', marginBottom: '8px', textTransform: 'uppercase', letterSpacing: '0.05em', fontSize: '12px' }}>{sec.label}</div>
                            <div style={{ lineHeight: '1.7', color: '#4b5563', background: '#ffffff', padding: '16px 20px', borderRadius: '12px', border: '1px solid #eee', whiteSpace: 'pre-line' }}>{sec.content}</div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
};
