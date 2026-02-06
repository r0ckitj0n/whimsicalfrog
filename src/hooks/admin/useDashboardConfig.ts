import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { API_ACTION } from '../../core/constants.js';
import type { IDashboardSection, IActionIcon } from '../../types/dashboard.js';

// Re-export for backward compatibility
export type { IDashboardSection, IActionIcon } from '../../types/dashboard.js';

export const useDashboardConfig = () => {
    const [sections, setSections] = useState<IDashboardSection[]>([]);
    const [icons, setIcons] = useState<IActionIcon[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchConfig = useCallback(async () => {
        setIsLoading(true);
        try {
            const [secRes, iconRes] = await Promise.all([
                ApiClient.get<{ success?: boolean; sections?: Array<{ section_key: string; display_title?: string; width_class?: string; is_active: string | number; display_order: string | number }>; data?: { sections: Array<{ section_key: string; display_title?: string; width_class?: string; is_active: string | number; display_order: string | number }> } }>(`/api/dashboard_sections.php?action=get_sections`),
                ApiClient.get<{ success?: boolean; icons?: IActionIcon[]; data?: { icons: IActionIcon[] } }>(`/api/action_icons.php?action=list`)
            ]);

            // Handle unwrapped sections from dashboard_sections.php
            const rawSections = secRes?.sections || secRes?.data?.sections || [];
            const mappedSections = rawSections.map((s) => ({
                key: s.section_key,
                title: s.display_title || s.section_key,
                width: (s.width_class?.replace('-width', '') || 'half') as 'full' | 'half' | 'third',
                is_visible: !!parseInt(String(s.is_active)),
                order: parseInt(String(s.display_order))
            }));

            setSections(mappedSections);

            // Handle icons (unwrapped or direct)
            const mappedIcons = iconRes?.icons || iconRes?.data?.icons || [];
            setIcons(mappedIcons);
        } catch (err) {
            logger.error('fetchDashboardConfig failed', err);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const updateSection = useCallback(async (key: string, patch: Partial<IDashboardSection>) => {
        setIsLoading(true);
        try {
            const updatedSections = sections.map(s => {
                const combined = s.key === key ? { ...s, ...patch } : s;
                return {
                    section_key: combined.key,
                    display_order: combined.order,
                    is_active: combined.is_visible ? 1 : 0,
                    width_class: combined.width.endsWith('-width') ? combined.width : `${combined.width}-width`
                };
            });

            const res = await ApiClient.post<{ success?: boolean }>(`/api/dashboard_sections.php?action=update_sections`, {
                sections: updatedSections
            });
            if (res) await fetchConfig();
            return res;
        } finally { setIsLoading(false); }
    }, [fetchConfig, sections]);

    return {
        sections,
        icons,
        isLoading,
        error,
        fetchConfig,
        updateSection
    };
};
