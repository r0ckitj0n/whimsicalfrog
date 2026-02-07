import React, { useState } from 'react';
import { IAboutData } from '../../types/index.js';
import { PAGE } from '../../core/constants.js';
import { VersionInfoModal } from '../modals/VersionInfoModal.js';
import { useVersionInfo } from '../../hooks/useVersionInfo.js';

interface AboutViewProps {
    data: IAboutData | null;
}

/**
 * AboutView Component
 * Renders the "Our Story" page using data from the BusinessSettings API.
 * Replaces: about.php
 */
export const AboutView: React.FC<AboutViewProps> = ({ data }) => {
    const [isVersionModalOpen, setIsVersionModalOpen] = useState(false);
    const { versionInfo, isLoading, error, refreshVersionInfo } = useVersionInfo(isVersionModalOpen);
    const page = document.body.getAttribute('data-page');

    // Only render if on the about page and data is available
    if (page !== PAGE.ABOUT || !data) {
        return null;
    }

    return (
        <>
            <div className="page-content bg-transparent container mx-auto px-4 pt-8 pb-0">
                <div className="prose max-w-none">
                    <div className="wf-cloud-card relative">
                        <div className="content leading-relaxed text-gray-800">
                            <h1 className="wf-cloud-title">Our Story</h1>
                            <div
                                className="about-page-rich-content"
                                dangerouslySetInnerHTML={{ __html: data.content }}
                            />
                        </div>

                        <div id="aboutButtonsRow" className="flex flex-wrap gap-2 justify-center mt-8 pt-4">
                            <a className="btn btn-secondary" href="/privacy" data-open-policy="1">Privacy Policy</a>
                            <a className="btn btn-secondary" href="/terms" data-open-policy="1">Terms of Service</a>
                            <a className="btn btn-secondary" href="/policy" data-open-policy="1">Store Policies</a>
                        </div>
                    </div>
                </div>
            </div>

            <div
                className="group fixed bottom-3 right-3"
                style={{ zIndex: 'var(--wf-z-modal)' }}
            >
                <div
                    className="absolute bottom-1 right-1 h-2 w-2 rounded-full bg-white/30 transition-colors duration-200 group-hover:bg-white/70"
                    aria-hidden="true"
                />
                <button
                    type="button"
                    className="rounded-full border border-white/35 bg-black/45 px-3 py-1 text-xs font-medium text-white backdrop-blur-sm opacity-0 transition-opacity duration-200 hover:bg-black/60 group-hover:opacity-100 focus:opacity-100 focus:outline-none focus:ring-2 focus:ring-white/60"
                    onClick={() => setIsVersionModalOpen(true)}
                    aria-label="Show code version details"
                    title="Show build and deploy version info"
                >
                    Version Info
                </button>
            </div>

            <VersionInfoModal
                isOpen={isVersionModalOpen}
                onClose={() => setIsVersionModalOpen(false)}
                versionInfo={versionInfo}
                isLoading={isLoading}
                error={error}
                onRefresh={refreshVersionInfo}
            />
        </>
    );
};
