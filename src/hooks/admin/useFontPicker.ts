import { useState, useCallback, useMemo } from 'react';
import type { IFontMeta } from '../../types/theming.js';

// Re-export for backward compatibility
export type { IFontMeta } from '../../types/theming.js';


export const FONT_LIBRARY: IFontMeta[] = [
    { id: 'system-sans', name: 'System UI', detail: 'Sans-serif', stack: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif", category: 'sans-serif', sample: 'Reliable interface typography.' },
    { id: 'inter', name: 'Inter', detail: 'Sans-serif', stack: "'Inter', 'Helvetica Neue', Arial, sans-serif", category: 'sans-serif', sample: 'Modern UI and marketing copy.', importFamily: 'Inter:wght@400;600' },
    { id: 'roboto', name: 'Roboto', detail: 'Sans-serif', stack: "'Roboto', 'Helvetica Neue', Arial, sans-serif", category: 'sans-serif', sample: 'Approachable body copy with clarity.', importFamily: 'Roboto:wght@400;500;700' },
    { id: 'open-sans', name: 'Open Sans', detail: 'Sans-serif', stack: "'Open Sans', 'Helvetica Neue', Arial, sans-serif", category: 'sans-serif', sample: 'Friendly paragraphs and marketing text.', importFamily: 'Open+Sans:wght@400;600' },
    { id: 'poppins', name: 'Poppins', detail: 'Sans-serif', stack: "'Poppins', 'Helvetica Neue', Arial, sans-serif", category: 'sans-serif', sample: 'Rounded titles with personality.', importFamily: 'Poppins:wght@400;500;700' },
    { id: 'work-sans', name: 'Work Sans', detail: 'Sans-serif', stack: "'Work Sans', 'Helvetica Neue', Arial, sans-serif", category: 'sans-serif', sample: 'Clean item descriptions and UI labels.', importFamily: 'Work+Sans:wght@400;500;600' },
    { id: 'nunito', name: 'Nunito', detail: 'Sans-serif', stack: "'Nunito', 'Helvetica Neue', Arial, sans-serif", category: 'sans-serif', sample: 'Balanced UI/body pairing.', importFamily: 'Nunito:wght@400;600;700' },
    { id: 'lato', name: 'Lato', detail: 'Sans-serif', stack: "'Lato', 'Helvetica Neue', Arial, sans-serif", category: 'sans-serif', sample: 'Lightweight marketing copy.', importFamily: 'Lato:wght@400;700' },
    { id: 'montserrat', name: 'Montserrat', detail: 'Display Sans', stack: "'Montserrat', 'Helvetica Neue', Arial, sans-serif", category: 'display', sample: 'Bold headlines with geometric flair.', importFamily: 'Montserrat:wght@500;700' },
    { id: 'raleway', name: 'Raleway', detail: 'Display Sans', stack: "'Raleway', 'Helvetica Neue', Arial, sans-serif", category: 'display', sample: 'Elegant uppercase headings.', importFamily: 'Raleway:wght@400;600' },
    { id: 'oswald', name: 'Oswald', detail: 'Display Sans', stack: "'Oswald', 'Arial Narrow', Arial, sans-serif", category: 'display', sample: 'Condensed hero headlines.', importFamily: 'Oswald:wght@400;500' },
    { id: 'merriweather', name: 'Merriweather', detail: 'Serif', stack: "'Merriweather', Georgia, serif", category: 'serif', sample: 'Editorial style paragraphs and quotes.', importFamily: 'Merriweather:wght@400;700' },
    { id: 'playfair', name: 'Playfair Display', detail: 'Serif', stack: "'Playfair Display', 'Times New Roman', serif", category: 'serif', sample: 'High-contrast headings with classic charm.', importFamily: 'Playfair+Display:wght@400;600' },
    { id: 'dm-serif', name: 'DM Serif Text', detail: 'Serif', stack: "'DM Serif Text', 'Times New Roman', serif", category: 'serif', sample: 'Refined accents for premium brands.', importFamily: 'DM+Serif+Text:ital,wght@0,400;1,400' },
    { id: 'abril', name: 'Abril Fatface', detail: 'Serif Display', stack: "'Abril Fatface', 'Times New Roman', serif", category: 'serif', sample: 'Statement logo typography.', importFamily: 'Abril+Fatface' },
    { id: 'lora', name: 'Lora', detail: 'Serif', stack: "'Lora', Georgia, serif", category: 'serif', sample: 'Literary body copy and quotes.', importFamily: 'Lora:wght@400;600' },
    { id: 'fira-code', name: 'Fira Code', detail: 'Monospace', stack: "'Fira Code', 'Courier New', monospace", category: 'monospace', sample: 'Technical snippets and code samples.', importFamily: 'Fira+Code:wght@400;500' },
    { id: 'source-code', name: 'Source Code Pro', detail: 'Monospace', stack: "'Source Code Pro', 'Courier New', monospace", category: 'monospace', sample: 'Console-style accents and UI.', importFamily: 'Source+Code+Pro:wght@400;600' },
    { id: 'space-mono', name: 'Space Mono', detail: 'Monospace', stack: "'Space Mono', 'Courier New', monospace", category: 'monospace', sample: 'Retro futurism for callouts.', importFamily: 'Space+Mono:wght@400;700' },
    { id: 'dancing-script', name: 'Dancing Script', detail: 'Handwriting', stack: "'Dancing Script', 'Brush Script MT', cursive", category: 'handwriting', sample: 'Playful handwritten callouts.', importFamily: 'Dancing+Script:wght@400;600' },
    { id: 'pacifico', name: 'Pacifico', detail: 'Handwriting', stack: "'Pacifico', 'Brush Script MT', cursive", category: 'handwriting', sample: 'Retro script for standout words.', importFamily: 'Pacifico' },
    { id: 'kalam', name: 'Kalam', detail: 'Handwriting', stack: "'Kalam', 'Comic Sans MS', cursive", category: 'handwriting', sample: 'Casual friendly signatures.', importFamily: 'Kalam:wght@400;700' },
    { id: 'shadows', name: 'Shadows Into Light', detail: 'Handwriting', stack: "'Shadows Into Light', 'Comic Sans MS', cursive", category: 'handwriting', sample: 'Loose sketch captions.', importFamily: 'Shadows+Into+Light' }
];

function normalizeFontStack(value: string) {
    if (!value) return '';
    return value.split(',').map((part) => part.trim().replace(/\s+/g, ' ')).filter(Boolean).join(',');
}

export const useFontPicker = () => {
    const [searchTerm, setSearchTerm] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('all');

    const filteredFonts = useMemo(() => {
        const query = searchTerm.toLowerCase();
        return FONT_LIBRARY.filter(font => {
            const matchesCategory = categoryFilter === 'all' || font.category === categoryFilter;
            const matchesQuery = !query ||
                font.name.toLowerCase().includes(query) ||
                font.stack.toLowerCase().includes(query) ||
                font.detail.toLowerCase().includes(query);
            return matchesCategory && matchesQuery;
        });
    }, [searchTerm, categoryFilter]);

    const categories = useMemo(() => {
        const cats = new Set(FONT_LIBRARY.map(f => f.category));
        return Array.from(cats);
    }, []);

    const getFontByStack = useCallback((stack: string) => {
        const normalized = normalizeFontStack(stack);
        return FONT_LIBRARY.find(f => normalizeFontStack(f.stack) === normalized) || null;
    }, []);

    return {
        searchTerm,
        setSearchTerm,
        categoryFilter,
        setCategoryFilter,
        filteredFonts,
        categories,
        getFontByStack
    };
};
