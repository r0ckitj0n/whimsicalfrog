import React from 'react';

interface BusinessInfo {
    name: string;
    tagline: string;
    phone: string;
    domain: string;
    url: string;
}

interface ReceiptHeaderProps {
    business_info: BusinessInfo;
}

export const ReceiptHeader: React.FC<ReceiptHeaderProps> = ({ business_info }) => {
    return (
        <div className="text-center receipt-message-center">
            <div className="brand-header-row wf-brand-font flex flex-col items-center mb-4">
                <img 
                    src="/images/logos/logo-whimsicalfrog.webp" 
                    alt="Whimsical Frog Brand Logo" 
                    className="header-logo receipt-logo mb-2" 
                    loading="lazy"
                />
                <div>
                    <h1 className="text-brand-primary wf-brand-font brand-title text-3xl font-bold">{business_info.name}</h1>
                    <p className="text-brand-secondary wf-brand-font brand-tagline italic">{business_info.tagline}</p>
                </div>
            </div>
            <div className="text-sm text-brand-secondary receipt-message-center mb-6">
                {(business_info.phone || business_info.domain) && (
                    <p>
                        {business_info.phone && (
                            <a href={`tel:${business_info.phone.replace(/[^0-9+]/g, '')}`} className="link-brand">
                                {business_info.phone}
                            </a>
                        )}
                        {business_info.phone && business_info.domain && <span className="mx-2">|</span>}
                        {business_info.domain && (
                            <a href={business_info.url} target="_blank" rel="noopener" className="link-brand">
                                {business_info.domain || business_info.url}
                            </a>
                        )}
                    </p>
                )}
            </div>
        </div>
    );
};
