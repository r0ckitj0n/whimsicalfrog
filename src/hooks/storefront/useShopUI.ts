import { useState, useMemo, useEffect, useCallback } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { ApiClient } from '../../core/ApiClient.js';
import { CATEGORY } from '../../core/constants.js';
import { IShopCategory as Category, IShopItem as Item } from '../../types/index.js';

interface UseShopUIProps {
    categories: Record<string, Category>;
    isVisible: boolean;
}

export const useShopUI = ({ categories, isVisible }: UseShopUIProps) => {
    const location = useLocation();
    const navigate = useNavigate();
    const [activeCategory, setActiveCategory] = useState<string>(CATEGORY.ALL);
    const [searchQuery, setSearchQuery] = useState('');
    const [bgUrl, setBgUrl] = useState('');
    const [expandedSkus, setExpandedSkus] = useState<Set<string>>(new Set());

    useEffect(() => {
        if (!isVisible) return;
        const loadBackground = async () => {
            try {
                const bgData = await ApiClient.get<{ background: { webp_filename?: string; png_filename?: string; image_filename?: string } }>(
                    '/api/get_background.php',
                    { room: 'S' }
                );
                const fetchedBg = bgData?.background?.webp_filename || bgData?.background?.png_filename || bgData?.background?.image_filename;
                if (fetchedBg) {
                    const cleanPath = fetchedBg.replace(/^backgrounds\//, '').replace(/^\//, '');
                    setBgUrl(/^https?:\/\//i.test(fetchedBg) ? fetchedBg : `/images/backgrounds/${cleanPath}`);
                }
            } catch (err) {
                console.error('[ShopView] Failed to load background', err);
            }
        };
        loadBackground();
    }, [isVisible]);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const q = params.get('q');
        if (q) setSearchQuery(q);
    }, [location.search]);

    const categoryList = useMemo(() => Object.values(categories), [categories]);

    const filteredItems = useMemo(() => {
        const query = searchQuery.toLowerCase().trim();
        const results: Array<{ item: Item; categoryLabel: string; categorySlug: string }> = [];

        categoryList.forEach(cat => {
            const catSlug = cat.slug || 'uncategorized';
            if (activeCategory !== CATEGORY.ALL && activeCategory !== catSlug) return;
            cat.items.forEach(item => {
                const stock = Number((item as Item & { stock_quantity?: number }).stock ?? (item as Item & { stock_quantity?: number }).stock_quantity ?? 0);
                const normalizedItem: Item = {
                    ...item,
                    stock: Number.isFinite(stock) ? stock : 0
                };
                if (!query || item.item_name.toLowerCase().includes(query) || item.sku.toLowerCase().includes(query) || item.description.toLowerCase().includes(query) || cat.label.toLowerCase().includes(query)) {
                    results.push({ item: normalizedItem, categoryLabel: cat.label, categorySlug: cat.slug });
                }
            });
        });

        return results.sort((a, b) => {
            if (a.item.stock > 0 && b.item.stock <= 0) return -1;
            if (a.item.stock <= 0 && b.item.stock > 0) return 1;
            return a.item.item_name.localeCompare(b.item.item_name);
        });
    }, [categoryList, activeCategory, searchQuery]);

    const getRowSkus = useCallback((clickedSku: string, gridRef: React.RefObject<HTMLDivElement>) => {
        const grid = gridRef.current;
        if (!grid) return [clickedSku];
        const cards = Array.from(grid.querySelectorAll('[data-sku]')) as HTMLElement[];
        const clickedCard = cards.find(c => c.dataset.sku === clickedSku);
        if (!clickedCard) return [clickedSku];
        const clickedTop = clickedCard.offsetTop;
        const tolerance = 10;
        return cards.filter(card => Math.abs(card.offsetTop - clickedTop) < tolerance).map(card => card.dataset.sku).filter((s): s is string => !!s);
    }, []);

    const toggleExpand = useCallback((sku: string, gridRef: React.RefObject<HTMLDivElement>) => {
        setExpandedSkus(prev => {
            const next = new Set(prev);
            const rowSkus = getRowSkus(sku, gridRef);
            if (next.has(sku)) rowSkus.forEach(s => next.delete(s));
            else rowSkus.forEach(s => next.add(s));
            return next;
        });
    }, [getRowSkus]);

    return {
        activeCategory, setActiveCategory,
        searchQuery, setSearchQuery,
        bgUrl, categoryList, filteredItems,
        expandedSkus, toggleExpand,
        navigate, handleClear: () => { setSearchQuery(''); setActiveCategory(CATEGORY.ALL); }
    };
};
