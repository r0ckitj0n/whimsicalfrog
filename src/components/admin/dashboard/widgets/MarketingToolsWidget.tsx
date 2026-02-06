import React from 'react';

interface MarketingToolsWidgetProps {
    stats: {
        email_campaigns: number;
        active_discounts: number;
        scheduled_posts: number;
    };
}

export const MarketingToolsWidget: React.FC<MarketingToolsWidgetProps> = ({ stats }) => {
    return (
        <div className="space-y-3">
            <div className="grid grid-cols-1 gap-2">
                <div className="bg-orange-50 rounded text-center p-3">
                    <div className="text-lg font-bold text-orange-600">{stats.email_campaigns}</div>
                    <div className="text-xs text-orange-800">Email Campaigns</div>
                </div>
                <div className="bg-indigo-50 rounded text-center p-3">
                    <div className="text-lg font-bold text-indigo-600">{stats.active_discounts}</div>
                    <div className="text-xs text-indigo-800">Active Discounts</div>
                </div>
            </div>
        </div>
    );
};
