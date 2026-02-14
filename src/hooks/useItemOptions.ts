import { useState, useMemo, useEffect } from 'react';
import { IItemDetails, IItemOption } from './useItemDetails.js';
import { IEffectiveOptionLists } from '../types/inventory.js';

/**
 * useItemOptions v1.0.73
 * Handles variant parsing, filtering, and selection logic for detailed item modal.
 */
export const useItemOptions = (item: IItemDetails | null, options: IItemOption[], effectiveLists: IEffectiveOptionLists | null = null) => {
    const [selectedGender, setSelectedGender] = useState('');
    const [selectedColor, setSelectedColor] = useState('');
    const [selectedSize, setSelectedSize] = useState('');

    // Reset state when item sku changes
    useEffect(() => {
        setSelectedGender('');
        setSelectedColor('');
        setSelectedSize('');
    }, [item?.sku]);

    // Keep cascade behavior predictable when lists are large.
    useEffect(() => {
        setSelectedColor('');
        setSelectedSize('');
    }, [selectedGender]);

    useEffect(() => {
        setSelectedSize('');
    }, [selectedColor]);

    const fallbackColors = useMemo(() => {
        if (!item?.color_options) return [];
        const unique = Array.from(new Set(item.color_options.split(',').map(c => c.trim()).filter(Boolean)));
        return unique.map((c, index) => ({
            id: c,
            name: c,
            code: '#ccc'
        }));
    }, [item]);

    const fallbackSizes = useMemo(() => {
        if (!item?.size_options) return [];
        const unique = Array.from(new Set(item.size_options.split(',').map(s => s.trim()).filter(Boolean)));
        return unique.map((s) => ({
            code: s,
            name: s,
            stock: Number(item.stock_quantity || 0),
            priceAdj: 0
        }));
    }, [item]);

    const availableGenders = useMemo(() => {
        if (effectiveLists && Array.isArray(effectiveLists.genders) && effectiveLists.genders.length > 0) {
            return effectiveLists.genders.slice();
        }
        if (!Array.isArray(options) || options.length === 0) return [];
        const genders = new Set(options
            .filter(opt => Number(opt.stock_level) > 0)
            .map((opt) => opt.gender)
            .filter(Boolean)
        );
        return Array.from(genders) as string[];
    }, [effectiveLists, options]);

    const availableColors = useMemo(() => {
        if (effectiveLists && Array.isArray(effectiveLists.colors) && effectiveLists.colors.length > 0) {
            return effectiveLists.colors.map((c) => ({
                id: c.name,
                name: c.name,
                code: c.code || '',
            }));
        }
        if (Array.isArray(options) && options.length > 0) {
            return options.filter((opt) =>
                Number(opt.stock_level) > 0 &&
                (!selectedGender || opt.gender === selectedGender) &&
                (!selectedSize || opt.size_code === selectedSize)
            ).reduce((acc: Array<{ id: string; name: string; code: string }>, opt) => {
                const colorName = (opt.color_name || '').trim();
                if (!colorName) return acc;
                if (!acc.find(c => c.id === colorName)) {
                    acc.push({
                        id: colorName,
                        name: colorName,
                        code: opt.color_code || ''
                    });
                }
                return acc;
            }, []);
        }
        return fallbackColors;
    }, [effectiveLists, options, selectedGender, selectedSize, fallbackColors]);

    const availableSizes = useMemo(() => {
        if (effectiveLists && Array.isArray(effectiveLists.sizes) && effectiveLists.sizes.length > 0) {
            const master = Number(item?.stock_quantity || 0);
            return effectiveLists.sizes.map((s) => ({
                code: s.code,
                name: s.name,
                stock: master,
                priceAdj: Number(s.price_adjustment || 0),
            }));
        }
        if (Array.isArray(options) && options.length > 0) {
            return options.filter((opt) => 
                Number(opt.stock_level) > 0 &&
                (!selectedGender || opt.gender === selectedGender) &&
                (!selectedColor || String(opt.color_name || '') === String(selectedColor))
            ).reduce((acc: Array<{ code: string; name: string; stock: number; priceAdj: number }>, opt) => {
                const sizeCode = opt.size_code || 'NOSIZE';
                const sizeName = opt.size_name || 'One Size';
                if (!acc.find(s => s.code === sizeCode)) {
                    acc.push({ 
                        code: sizeCode, 
                        name: sizeName,
                        stock: Number(opt.stock_level || 0),
                        priceAdj: Number(opt.price_adjustment || 0)
                    });
                }
                return acc;
            }, []);
        }
        return fallbackSizes;
    }, [effectiveLists, item?.stock_quantity, options, selectedGender, selectedColor, fallbackSizes]);

    const currentVariant = useMemo(() => {
        if (item && effectiveLists && Array.isArray(effectiveLists.sizes) && effectiveLists.sizes.length > 0) {
            if (!(selectedGender || selectedColor || selectedSize)) return undefined;
            const matchSize = selectedSize
                ? effectiveLists.sizes.find((s) => String(s.code) === String(selectedSize))
                : null;
            return {
                id: 'virtual-template',
                sku: item.sku,
                gender: selectedGender || undefined,
                color_name: selectedColor || undefined,
                size_code: selectedSize || undefined,
                stock_level: Number(item.stock_quantity || 0),
                price_adjustment: Number(matchSize?.price_adjustment || 0),
            } as IItemOption;
        }
        if (Array.isArray(options) && options.length > 0) {
            const match = options.find((opt) => 
                (!selectedGender || opt.gender === selectedGender) &&
                (!selectedColor || String(opt.color_name || '') === String(selectedColor)) &&
                (!selectedSize || opt.size_code === selectedSize)
            );
            if (match) return match;

            if (!selectedSize && (selectedColor || selectedGender)) {
                return options.find((opt) => 
                    (!selectedGender || opt.gender === selectedGender) &&
                    (!selectedColor || String(opt.color_name || '') === String(selectedColor))
                );
            }
        }
        if (item && (selectedColor || selectedSize)) {
            return {
                id: 'virtual',
                sku: item.sku,
                stock_level: item.stock_quantity || 99,
                price_adjustment: 0
            } as IItemOption;
        }
        return undefined;
    }, [effectiveLists, item, options, selectedGender, selectedColor, selectedSize]);

    return {
        selectedGender, setSelectedGender,
        selectedColor, setSelectedColor,
        selectedSize, setSelectedSize,
        availableGenders,
        availableColors,
        availableSizes,
        currentVariant
    };
};
