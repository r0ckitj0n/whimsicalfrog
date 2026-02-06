import React from 'react';

export const ItemDetailsLoading: React.FC = () => {
    return (
        <div style={{ height: '100%', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: '20px', padding: '100px' }}>
            <div className="wf-emoji-loader" style={{ fontSize: '48px' }}>⏳</div>
            <p style={{ color: '#6b7280', fontWeight: 'bold', fontStyle: 'italic', fontSize: '18px' }}>Crafting your view...</p>
        </div>
    );
};
