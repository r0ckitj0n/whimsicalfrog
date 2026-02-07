import React from 'react';
import { createPortal } from 'react-dom';
import useGlobalProcessing from '../hooks/useGlobalProcessing.js';

export const GlobalProcessingOverlay: React.FC = () => {
    const { isVisible } = useGlobalProcessing();

    if (!isVisible || typeof document === 'undefined') return null;

    return createPortal(
        <div
            className="wf-global-processing-overlay"
            role="status"
            aria-live="polite"
            aria-label="Processing request"
        >
            <div className="wf-global-processing-content">
                <picture>
                    <source
                        srcSet="/images/logos/logo-whimsicalfrog-hourglass.webp"
                        type="image/webp"
                    />
                    <img
                        className="wf-global-processing-frog"
                        src="/images/logos/logo-whimsicalfrog-hourglass.png"
                        alt="Whimsical Frog"
                        width={128}
                        height={128}
                        loading="eager"
                        decoding="async"
                    />
                </picture>
                <p className="wf-global-processing-text">Working on it...</p>
            </div>
        </div>,
        document.body
    );
};

export default GlobalProcessingOverlay;
