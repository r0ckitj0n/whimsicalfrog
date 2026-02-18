import React from 'react';

interface BusinessDetails {
    email?: string;
    phone?: string;
    address?: string;
    owner?: string;
    name?: string;
    site?: string;
    hours?: string;
}

interface BusinessInfoProps {
    revealed: boolean;
    onReveal: () => void;
}

export const BusinessInfo: React.FC<BusinessInfoProps> = ({ revealed, onReveal }) => {
    if (revealed) return null;

    return (
        <div className="wf-reveal-company-wrap">
            <button
                type="button"
                onClick={onReveal}
                className="wf-reveal-company-btn"
                aria-label="Open detailed company card in a secure modal"
                data-help-id="captcha-explanation"
            >
                View Detailed Company Card
            </button>
        </div>
    );
};
