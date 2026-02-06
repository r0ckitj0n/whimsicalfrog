import React from 'react';
import { IAboutData } from '../../types/index.js';
import { PAGE } from '../../core/constants.js';

interface AboutViewProps {
    data: IAboutData | null;
}

/**
 * AboutView Component
 * Renders the "Our Story" page using data from the BusinessSettings API.
 * Replaces: about.php
 */
export const AboutView: React.FC<AboutViewProps> = ({ data }) => {
    const page = document.body.getAttribute('data-page');

    // Only render if on the about page and data is available
    if (page !== PAGE.ABOUT || !data) {
        return null;
    }

    return (
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
    );
};
