import React, { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { ADMIN_SECTION } from '../../core/constants.js';
import { useApp } from '../../context/AppContext.js';

interface AdminTab {
    key: string;
    label: string;
    icon?: React.ReactNode;
}

const TABS: AdminTab[] = [
    { key: 'pos', label: 'POS' },
    { key: '', label: 'Dashboard' },
    { key: 'customers', label: 'Customers' },
    { key: 'inventory', label: 'Inventory' },
    { key: 'orders', label: 'Orders' },
    { key: 'reports', label: 'Reports' },
    { key: 'marketing', label: 'Marketing' },
    { key: 'settings', label: 'Settings' },
];

/**
 * AdminNavigation Component
 * Modern React replacement for admin_nav_tabs.php.
 */
export const AdminNavigation: React.FC = () => {
    const [searchParams, setSearchParams] = useSearchParams();
    const currentSection = searchParams.get('section') || '';
    const { toggleHints, hintsEnabled } = useApp();

    const handleTabClick = (key: string) => {
        setSearchParams(prev => {
            if (key === '') {
                prev.delete('section');
            } else {
                prev.set('section', key);
            }
            // Clear other params when switching sections
            prev.delete('edit');
            prev.delete('view');
            prev.delete('add');
            return prev;
        });
    };

    return (
        <div className="admin-tab-navigation">
            <div className="wf-admin-nav-row">
                {/* Left Section (POS) */}
                <div className="wf-nav-left">
                    <button
                        id="navTabPos"
                        onClick={() => handleTabClick('pos')}
                        className={`admin-nav-tab pill-ring admin-tab-pos ${currentSection === 'pos' ? 'active' : ''}`}
                    >
                        POS
                    </button>
                </div>

                {/* Center Section (Tabs) */}
                <div className="wf-nav-center">
                    {TABS.filter(t => t.key !== 'pos').map(tab => (
                        <button
                            key={tab.key}
                            id={`navTab${tab.key.charAt(0).toUpperCase() + tab.key.slice(1) || 'Dashboard'}`}
                            onClick={() => handleTabClick(tab.key)}
                            className={`admin-nav-tab pill-ring admin-tab-${tab.key || 'dashboard'} ${currentSection === tab.key ? 'active' : ''}`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                {/* Right Section (Help/Utilities) */}
                <div className="wf-nav-right">
                    <div className="admin-help-container">
                        <button
                            type="button"
                            id="adminHelpDocsBtn"
                            className="admin-tab-help admin-help-docs"
                            onClick={() => handleTabClick(ADMIN_SECTION.HELP_GUIDES)}
                            data-help-id="admin-nav-help-docs"
                        >
                            <span className="help-q">?</span>
                        </button>
                        <button
                            type="button"
                            id="adminHelpToggleBtn"
                            className="admin-tab-help admin-help-toggle"
                            onClick={toggleHints}
                            data-help-id="admin-nav-help-toggle"
                            aria-pressed={hintsEnabled}
                        >
                            <span className="wf-toggle"><span className="wf-knob"></span></span>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    );
};

export default AdminNavigation;
