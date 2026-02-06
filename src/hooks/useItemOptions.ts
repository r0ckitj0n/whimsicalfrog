import { useState, useMemo, useEffect } from 'react';
import { IItemDetails, IItemOption } from './useItemDetails.js';

/**
 * useItemOptions v1.0.73
 * Handles variant parsing, filtering, and selection logic for detailed item modal.
 */
export const useItemOptions = (item: IItemDetails | null, options: IItemOption[]) => {
    const [selectedGender, setSelectedGender] = useState('');
    const [selectedColor, setSelectedColor] = useState('');
    const [selectedSize, setSelectedSize] = useState('');

    // Reset state when item sku changes
    useEffect(() => {
        setSelectedGender('');
        setSelectedColor('');
        setSelectedSize('');
    }, [item?.sku]);

    const fallbackColors = useMemo(() => {
        if (!item?.color_options) return [];
        const unique = Array.from(new Set(item.color_options.split(',').map(c => c.trim()).filter(Boolean)));
        return unique.map((c, index) => ({
            id: `fallback-color-${index}`,
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
            stock: 99,
            priceAdj: 0
        }));
    }, [item]);

    const availableGenders = useMemo(() => {
        if (!Array.isArray(options) || options.length === 0) return [];
        const genders = new Set(options
            .filter(opt => Number(opt.stock_level) > 0)
            .map((opt) => opt.gender)
            .filter(Boolean)
        );
        return Array.from(genders) as string[];
    }, [options]);

    const availableColors = useMemo(() => {
        if (Array.isArray(options) && options.length > 0) {
            return options.filter((opt) => 
                Number(opt.stock_level) > 0 &&
                (!selectedGender || opt.gender === selectedGender) &&
                (!selectedSize || opt.size_code === selectedSize)
            ).reduce((acc: Array<{ id: string; name: string; code: string }>, opt) => {
                const colorId = String(opt.color_id);
                if (opt.color_id && !acc.find(c => c.id === colorId)) {
                    acc.push({ 
                        id: colorId, 
                        name: opt.color_name || '', 
                        code: opt.color_code || '' 
                    });
                }
                return acc;
            }, []);
        }
        return fallbackColors;
    }, [options, selectedGender, selectedSize, fallbackColors]);

    const availableSizes = useMemo(() => {
        if (Array.isArray(options) && options.length > 0) {
            return options.filter((opt) => 
                Number(opt.stock_level) > 0 &&
                (!selectedGender || opt.gender === selectedGender) &&
                (!selectedColor || String(opt.color_id) === String(selectedColor))
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
    }, [options, selectedGender, selectedColor, fallbackSizes]);

    const currentVariant = useMemo(() => {
        if (Array.isArray(options) && options.length > 0) {
            const match = options.find((opt) => 
                (!selectedGender || opt.gender === selectedGender) &&
                (!selectedColor || String(opt.color_id) === String(selectedColor)) &&
                (!selectedSize || opt.size_code === selectedSize)
            );
            if (match) return match;

            if (!selectedSize && (selectedColor || selectedGender)) {
                return options.find((opt) => 
                    (!selectedGender || opt.gender === selectedGender) &&
                    (!selectedColor || String(opt.color_id) === String(selectedColor))
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
    }, [options, selectedGender, selectedColor, selectedSize, item]);

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
