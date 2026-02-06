import React from 'react';
import { IItemSize, IItemColor } from '../../../types/index.js';

interface ColorGroupProps {
    color: IItemColor;
    colorSizes: IItemSize[];
    isReadOnly: boolean;
    isLoading: boolean;
    onStockUpdate?: (sizeId: number, newStock: number) => void;
}

export const ColorGroup: React.FC<ColorGroupProps> = ({
    color,
    colorSizes,
    isReadOnly,
    isLoading,
    onStockUpdate
}) => {
    const colorTotal = colorSizes.reduce((sum, s) => sum + s.stock_level, 0);

    return (
        <div className="border rounded-lg overflow-hidden">
            <div className="px-3 py-2 bg-white border-b flex items-center justify-between">
                <div className="flex items-center gap-2">
                    {color.color_code && (
                        <div
                            className="w-4 h-4 rounded-full border shadow-sm wf-color-preview"
                            style={{ '--preview-color': color.color_code } as React.CSSProperties}
                        />
                    )}
                    <span className="font-medium text-gray-800">{color.color_name}</span>
                </div>
                <div className="text-xs font-medium text-gray-900">
                    Total: {colorTotal}
                </div>
            </div>
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Stock</th>
                    </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                    {colorSizes.map(size => (
                        <tr key={size.id} className={size.is_active ? '' : 'bg-gray-50 opacity-60'}>
                            <td className="px-4 py-2 text-sm text-gray-900">{size.size_name}</td>
                            <td className="px-4 py-2 text-sm text-gray-500">{size.size_code}</td>
                            <td className="px-4 py-2 text-right">
                                <input
                                    type="number"
                                    min="0"
                                    defaultValue={size.stock_level}
                                    className="w-20 text-right border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-[var(--brand-primary)]/20"
                                    disabled={isReadOnly || isLoading}
                                    onBlur={(e) => onStockUpdate?.(size.id, parseInt(e.target.value, 10))}
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
};
