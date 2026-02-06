import React from 'react';

interface CaptchaModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSubmit: (e: React.FormEvent) => void;
    captchaValue: string;
    setCaptchaValue: (val: string) => void;
    captcha?: {
        a: number;
        b: number;
    };
    isRevealing?: boolean;
    revealedDetails?: {
        email: string;
        phone: string;
        address: string;
        owner: string;
        name: string;
        site: string;
        hours: string;
    } | null;
}

export const CaptchaModal: React.FC<CaptchaModalProps> = ({
    isOpen,
    onClose,
    onSubmit,
    captchaValue,
    setCaptchaValue,
    captcha,
    isRevealing,
    revealedDetails
}) => {
    if (!isOpen) return null;

    if (revealedDetails) {
        return (
            <div className="wf-revealco-overlay show" onClick={(e) => e.target === e.currentTarget && onClose()}>
                <div className="wf-revealco-modal">
                    <div className="wf-revealco-card">
                        <button
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            aria-label="Close"
                            data-help-id="modal-close"
                        />
                        <h3 className="wf-revealco-title">Company Information</h3>
                        <div className="wf-revealco-details pos-receipt">
                            <div className="wf-revealco-row">
                                <span className="wf-revealco-label">Name:</span>
                                <span className="wf-revealco-value">{revealedDetails.name}</span>
                            </div>
                            <div className="wf-revealco-row">
                                <span className="wf-revealco-label">Owner:</span>
                                <span className="wf-revealco-value">{revealedDetails.owner}</span>
                            </div>
                            <div className="wf-revealco-row">
                                <span className="wf-revealco-label">Email:</span>
                                <a href={`mailto:${revealedDetails.email}`} className="wf-revealco-link">{revealedDetails.email}</a>
                            </div>
                            <div className="wf-revealco-row">
                                <span className="wf-revealco-label">Phone:</span>
                                <a href={`tel:${revealedDetails.phone.replace(/[^\d+]/g, '')}`} className="wf-revealco-link">{revealedDetails.phone}</a>
                            </div>
                            <div className="wf-revealco-row">
                                <span className="wf-revealco-label">Address:</span>
                                <span className="wf-revealco-value whitespace-pre-wrap">{revealedDetails.address}</span>
                            </div>
                            {revealedDetails.hours && (
                                <div className="wf-revealco-row">
                                    <span className="wf-revealco-label">Hours:</span>
                                    <span className="wf-revealco-value whitespace-pre-wrap">{revealedDetails.hours}</span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="wf-revealco-overlay show" onClick={(e) => e.target === e.currentTarget && onClose()}>
            <div className="wf-revealco-modal">
                <div className="wf-revealco-card">
                    <button
                        onClick={onClose}
                        className="admin-action-btn btn-icon--close"
                        aria-label="Close"
                        data-help-id="modal-close"
                    />
                    <h3 className="wf-revealco-title">Human Check</h3>
                    <p className="wf-revealco-hint">Please solve this quick check to reveal our details:</p>

                    <form onSubmit={onSubmit} className="space-y-4">
                        <div className="wf-revealco-q">
                            <span className="wf-revealco-qtext">{captcha?.a} + {captcha?.b} =</span>
                            <input
                                type="text" inputMode="numeric" required autoFocus
                                value={captchaValue}
                                onChange={e => setCaptchaValue(e.target.value)}
                                className="wf-revealco-input w-24"
                                autoComplete="off"
                            />
                        </div>
                        <div className="wf-revealco-actions">
                            <button
                                type="submit"
                                className="wf-submit-btn w-full"
                            >
                                Confirm
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
};
