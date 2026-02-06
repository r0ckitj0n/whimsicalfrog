import React from 'react';
import { MarketingAnalytics } from './MarketingAnalytics.js';

export const MarketingSection: React.FC = () => {
    return (
        <div id="marketing-react-root" className="flex-1 min-h-0 overflow-y-auto space-y-12 p-8 w-full !max-w-none">
            <MarketingAnalytics />
        </div>
    );
};
