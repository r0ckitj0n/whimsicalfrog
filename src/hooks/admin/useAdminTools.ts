import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IAdminTool, IAdminToolCategory } from '../../types/admin.js';

// Re-export for backward compatibility
export type { IAdminTool, IAdminToolCategory } from '../../types/admin.js';

export const useAdminTools = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // This is a static list for now, but could be fetched from an API
    const categories: IAdminToolCategory[] = [
        {
            title: 'System Health',
            tools: [
                { name: 'Activity Logs', inline_modal: 'logs', icon: '📋', desc: 'Real-time system events and error tracking.', tooltip: 'Read the history of everything that went wrong.' },
                { name: 'Cron Job Manager', inline_modal: 'cron-manager', icon: '⏱️', desc: 'Manage scheduled background tasks.', tooltip: 'Poke the background gnomes.' },
                { name: 'DB Query Console', inline_modal: 'db-query-console', icon: '💻', desc: 'Interactive SQL console for diagnostics.', tooltip: 'Play with fire. Try not to burn the house down.' },
                { name: 'DB Status Dashboard', inline_modal: 'db-status', icon: '📊', desc: 'Current database health and rule counts.', tooltip: 'Checking if the foundation is made of sand.' },
                { name: 'DB Migrations Audit', inline_modal: 'db-migrations-audit', icon: '🧱', desc: 'Report missing DB tables/columns expected by this build.', tooltip: 'Catch dev vs live drift before it bites.' },
                { name: 'Session Viewer', inline_modal: 'session-viewer', icon: '👤', desc: 'Inspect current session and auth cookies.', tooltip: 'Stalk your guests (metaphorically).' },
                { name: 'Site Maintenance', inline_modal: 'site-maintenance', icon: '🛡️', desc: 'Backups, database repair, and system configuration.', tooltip: 'Duct tape and WD-40 for your website.' },
                { name: 'System Secrets', inline_modal: 'secrets', icon: '🔑', desc: 'Manage API keys and environment variables.', tooltip: "Shhh. Don't tell anyone." }
            ]
        },
        {
            title: 'Commerce & Logic',
            tools: [
                { name: 'Automation Manager', inline_modal: 'automation', icon: '⚙️', desc: 'Monitor and configure automated background tasks and event triggers.', tooltip: 'Watch the robots pretend to work.' },
                { name: 'Cart Simulation', inline_modal: 'cart-simulation', icon: '🛒', desc: 'Test cart logic and shopper profiles.', tooltip: 'Shopping for imaginary items.' },
                { name: 'Distance Diagnostics', inline_modal: 'address-check', icon: '📍', desc: 'Verify geolocation and delivery distance APIs.', tooltip: 'How far is it? Far enough.' },
                { name: 'Intent Heuristics', inline_modal: 'intent-heuristics', icon: '🧠', desc: 'Configure AI customer intent and behavior heuristics.', tooltip: 'Trying to figure out what they want before they do.' },
                { name: 'Marketing AI Check', inline_modal: 'marketing-check', icon: '🤖', desc: 'Verify AI marketing wiring and data logic.', tooltip: 'Let the robots tell you how to sell more junk.' },
                { name: 'Sizing Tools', inline_modal: 'sizing-tools', icon: '📐', desc: 'Manage weights and AI dimension backfills.', tooltip: "Measuring things so you don't have to." }
            ]
        },
        {
            title: 'Visuals & UI',
            tools: [
                { name: 'Icon Buttons', inline_modal: 'action-icons', icon: '🔘', desc: 'Manage global UI action icons.', tooltip: 'Managing the tiny pictures that make you click.' },
                { name: 'CSS Catalog', inline_modal: 'css-catalog', icon: '📚', desc: 'Technical reference for available CSS tokens.', tooltip: 'A book of rules nobody reads.' },
                { name: 'CSS Override Rules', inline_modal: 'css-rules', icon: '🎨', desc: 'Browse and edit global CSS classes and tokens.', tooltip: 'Style it until it breaks.' }
            ]
        }
    ];

    return {
        categories,
        isLoading,
        error
    };
};
