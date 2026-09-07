import React from 'react';

/**
 * Lightweight Suspense fallback for storefront route transitions.
 * Intentionally silent — never show "Loading Admin Section" outside admin.
 */
export const PageLoadingFallback: React.FC = () => (
    <div className="wf-page-loading" role="status" aria-live="polite" aria-busy="true">
        <span className="sr-only">Loading…</span>
    </div>
);

export default PageLoadingFallback;
