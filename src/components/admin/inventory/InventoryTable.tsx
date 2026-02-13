import React from 'react';
import { IInventoryItem } from '../../../hooks/admin/useInventory.js';

interface InventoryTableProps {
    items: IInventoryItem[];
    sort: { column: string; direction: 'asc' | 'desc' };
    onSort: (column: string) => void;
    onView: (sku: string) => void;
    onEdit: (sku: string) => void;
    onDelete: (sku: string) => void;
    onUpdate: (sku: string, data: Partial<IInventoryItem>) => Promise<unknown>;
    categories: string[];
    isLoading: boolean;
}

export const InventoryTable: React.FC<InventoryTableProps> = ({
    items,
    sort,
    onSort,
    onView,
    onEdit,
    onDelete,
    onUpdate,
    categories,
    isLoading
}) => {
    const [editingCell, setEditingCell] = React.useState<{ sku: string, field: string } | null>(null);
    const [editValue, setEditValue] = React.useState<string>('');

    const handleEditStart = (sku: string, field: string, value: string | number) => {
        setEditingCell({ sku, field });
        setEditValue(String(value));
    };

    const handleEditBlur = async (sku: string, field: string) => {
        const item = items.find(i => i.sku === sku);
        const itemValue = item ? (item as unknown as Record<string, unknown>)[field] : undefined;
        if (item && String(itemValue) !== editValue) {
            let value: string | number = editValue;
            if (['stock_quantity', 'reorder_point'].includes(field)) value = parseInt(editValue, 10);
            if (['cost_price', 'retail_price'].includes(field)) value = parseFloat(editValue);

            try {
                await onUpdate(sku, { [field]: value });
                if (window.WFToast) window.WFToast.success(`${field.replace('_', ' ')} updated successfully`);
            } catch (err) {
                const msg = err instanceof Error && err.message ? err.message : `Failed to update ${field}`;
                if (window.WFToast) window.WFToast.error(msg);
            }
        }
        setEditingCell(null);
    };

    const handleKeyDown = (e: React.KeyboardEvent, sku: string, field: string) => {
        if (e.key === 'Enter') handleEditBlur(sku, field);
        else if (e.key === 'Escape') setEditingCell(null);
    };

    const renderEditableCell = (item: IInventoryItem, field: string, value: string | number, type: 'text' | 'number' | 'select' = 'text') => {
        const isEditing = editingCell?.sku === item.sku && editingCell?.field === field;

        if (isEditing) {
            if (field === 'category') {
                return (
                    <select
                        autoFocus
                        value={editValue}
                        onChange={(e) => setEditValue(e.target.value)}
                        onBlur={() => handleEditBlur(item.sku, field)}
                        className="text-[10px] border rounded px-1 py-0.5 w-full bg-white font-medium uppercase"
                    >
                        {categories.map(c => <option key={c} value={c}>{c}</option>)}
                    </select>
                );
            }
            return (
                <input
                    autoFocus
                    type={type}
                    step={type === 'number' && field.includes('price') ? "0.01" : "1"}
                    value={editValue}
                    onChange={(e) => setEditValue(e.target.value)}
                    onBlur={() => handleEditBlur(item.sku, field)}
                    onKeyDown={(e) => handleKeyDown(e, item.sku, field)}
                    className="text-xs border rounded px-2 py-1 w-full font-medium"
                />
            );
        }

        return (
            <div
                onClick={() => handleEditStart(item.sku, field, value)}
                className="cursor-pointer hover:bg-gray-100 px-2 -mx-2 rounded transition-colors truncate"
                data-help-id="inventory-cell-edit"
            >
                {field === 'category' ? (
                    <span className="text-gray-600 text-[10px] font-semibold uppercase tracking-widest">
                        {value}
                    </span>
                ) : field === 'sku' ? (
                    <code className="text-xs font-mono font-medium text-[var(--brand-primary)]">{value}</code>
                ) : field.includes('price') ? (
                    <div className="text-sm font-semibold text-gray-900">${Number(value).toFixed(2)}</div>
                ) : field === 'stock_quantity' ? (
                    <div className="inline-flex items-center justify-center gap-1.5 font-semibold text-xs text-gray-900">
                        {value}
                        {item.stock_quantity <= item.reorder_point && <span data-help-id="inventory-low-stock-warning">⚠️</span>}
                    </div>
                ) : (
                    <div className={field === 'name' ? "text-sm font-semibold text-gray-900 leading-tight" : "text-xs font-medium text-gray-500"}>
                        {value}
                    </div>
                )}
            </div>
        );
    };

    const SortIcon = ({ column }: { column: string }) => {
        if (sort.column !== column) return null;
        return sort.direction === 'asc' ? <span className="text-[10px]">🔼</span> : <span className="text-[10px]">🔽</span>;
    };

    return (
        <div className="bg-white border rounded-[2rem] shadow-sm overflow-visible flex flex-col min-h-[400px]">
            <div className="overflow-visible">
                <table className="w-full table-fixed text-left border-collapse border-spacing-0">
                    <colgroup><col style={{ width: '4%' }} /><col style={{ width: '3%' }} /><col style={{ width: '27%' }} /><col style={{ width: '8%' }} /><col style={{ width: '10%' }} /><col style={{ width: '8%' }} /><col style={{ width: '6%' }} /><col style={{ width: '6%' }} /><col style={{ width: '7%' }} /><col style={{ width: '7%' }} /><col style={{ width: '14%' }} /></colgroup>
                    <thead className="bg-gray-50 border-b-2 border-gray-300 sticky top-0 z-10">
                        <tr>
                            <th className="px-1 py-3 font-semibold text-gray-400 uppercase tracking-widest text-[9px]">Image</th>
                            <th className="px-1 py-3 font-semibold text-gray-400 uppercase tracking-widest text-[9px] text-center">#</th>
                            <th
                                className="px-1 py-3 font-semibold text-gray-400 uppercase tracking-widest text-[9px] cursor-pointer hover:text-[var(--brand-primary)] transition-colors"
                                onClick={() => onSort('name')}
                            >
                                <div className="flex items-center gap-1">Name <SortIcon column="name" /></div>
                            </th>
                            <th
                                className="px-1 py-3 font-semibold text-gray-400 uppercase tracking-widest text-[9px] cursor-pointer hover:text-[var(--brand-primary)] transition-colors"
                                onClick={() => onSort('category')}
                            >
                                <div className="flex items-center gap-1">Category <SortIcon column="category" /></div>
                            </th>
                            <th
                                className="px-1 py-3 font-semibold text-gray-400 uppercase tracking-widest text-[9px] cursor-pointer hover:text-[var(--brand-primary)] transition-colors"
                                onClick={() => onSort('sku')}
                            >
                                <div className="flex items-center gap-1">SKU <SortIcon column="sku" /></div>
                            </th>
                            <th className="px-1 py-3 font-semibold text-gray-400 uppercase tracking-widest text-[9px] text-center">Active</th>
                            <th
                                className="px-1 py-3 font-semibold text-gray-400 uppercase tracking-widest text-[9px] cursor-pointer hover:text-[var(--brand-primary)] transition-colors text-center"
                                onClick={() => onSort('stock_quantity')}
                            >
                                <div className="flex items-center justify-center gap-1">Stock <SortIcon column="stock_quantity" /></div>
                            </th>
                            <th className="px-1 py-3 font-semibold text-gray-400 uppercase tracking-widest text-[9px] text-center">Reorder</th>
                            <th className="px-1 py-3 font-semibold text-gray-400 uppercase tracking-widest text-[9px] text-right">Cost</th>
                            <th className="px-1 py-3 font-semibold text-gray-400 uppercase tracking-widest text-[9px] text-right">Retail</th>
                            <th className="px-1 py-3 font-semibold text-gray-400 uppercase tracking-widest text-[9px] text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white">
                        {items.length === 0 ? (
                            <tr>
                                <td colSpan={11} className="px-6 py-24 text-center">
                                    {isLoading ? (
                                        <div className="flex flex-col items-center gap-2">
                                            <span className="wf-emoji-loader">📦</span>
                                            <span className="text-sm font-medium text-gray-400 italic">Scanning warehouse...</span>
                                        </div>
                                    ) : (
                                        <div className="flex flex-col items-center gap-2">
                                            <span className="text-4xl">📦</span>
                                            <span className="text-sm font-medium text-gray-400 italic">No items found matching criteria.</span>
                                        </div>
                                    )}
                                </td>
                            </tr>
                        ) : (
                            items.map((item) => (
                                <tr key={item.sku} className="group hover:bg-gray-50/50 transition-colors">
                                    <td className="px-1 py-2 border-b-2 border-gray-300">
                                        <div className="flex items-center justify-center">
                                            <div className="wf-thumb-wrap">
                                                {item.primary_image ? (
                                                    <img
                                                        src={typeof item.primary_image === 'string'
                                                            ? (item.primary_image.startsWith('/') ? item.primary_image : `/${item.primary_image}`)
                                                            : (item.primary_image.image_path.startsWith('/') ? item.primary_image.image_path : `/${item.primary_image.image_path}`)
                                                        }
                                                        alt={item.name}
                                                        className="wf-thumb-img"
                                                        loading="lazy"
                                                    />
                                                ) : (
                                                    <span className="text-sm">🖼️</span>
                                                )}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-1 py-2 text-center border-b-2 border-gray-300">
                                        <span className="text-xs font-medium text-gray-500">{item.image_count}</span>
                                    </td>
                                    <td className="px-1 py-2 border-b-2 border-gray-300 overflow-hidden">
                                        <div className="truncate">{renderEditableCell(item, 'name', item.name)}</div>
                                    </td>
                                    <td className="px-1 py-2 border-b-2 border-gray-300 overflow-hidden">
                                        <div className="truncate">{renderEditableCell(item, 'category', item.category, 'select')}</div>
                                    </td>
                                    <td className="px-1 py-2 border-b-2 border-gray-300 overflow-hidden">
                                        <div className="truncate flex items-center gap-2">
                                            {renderEditableCell(item, 'sku', item.sku)}
                                            {Number(item.is_archived) === 1 && (
                                                <span className="px-1.5 py-0.5 bg-gray-100 text-gray-500 text-[8px] font-bold uppercase rounded tracking-tighter" data-help-id="inventory-archived-badge">
                                                    ARCHIVED
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-1 py-2 text-center border-b-2 border-gray-300">
                                        <div className="flex items-center justify-center">
                                            <input
                                                type="checkbox"
                                                checked={item.status === 'live'}
                                                onChange={async (e) => {
                                                    const newStatus = e.target.checked ? 'live' : 'draft';
                                                    try {
                                                        await onUpdate(item.sku, { status: newStatus });
                                                        if (window.WFToast) window.WFToast.success(`Item status set to ${newStatus}`);
                                                    } catch (err) {
                                                        if (window.WFToast) window.WFToast.error('Failed to update status');
                                                    }
                                                }}
                                                className="w-4 h-4 text-[var(--brand-primary)] rounded border-gray-300 focus:ring-[var(--brand-primary)] cursor-pointer"
                                                data-help-id="inventory-status-toggle"
                                            />
                                        </div>
                                    </td>
                                    <td className="px-1 py-2 text-center border-b-2 border-gray-300">
                                        {renderEditableCell(item, 'stock_quantity', item.stock_quantity, 'number')}
                                    </td>
                                    <td className="px-1 py-2 text-center border-b-2 border-gray-300">
                                        {renderEditableCell(item, 'reorder_point', item.reorder_point, 'number')}
                                    </td>
                                    <td className="px-1 py-2 text-right border-b-2 border-gray-300 overflow-hidden">
                                        <div className="truncate">{renderEditableCell(item, 'cost_price', item.cost_price, 'number')}</div>
                                    </td>
                                    <td className="px-1 py-2 text-right border-b-2 border-gray-300 overflow-hidden">
                                        <div className="truncate">{renderEditableCell(item, 'retail_price', item.retail_price, 'number')}</div>
                                    </td>
                                    <td className="px-1 py-2 text-right border-b-2 border-gray-300">
                                        <div className="flex justify-end gap-0.5">
                                            <button
                                                onClick={() => onView(item.sku)}
                                                className="admin-action-btn btn-icon--view"
                                                data-help-id="inventory-action-view"
                                            ></button>
                                            <button
                                                onClick={() => onEdit(item.sku)}
                                                className="admin-action-btn btn-icon--edit"
                                                data-help-id="inventory-action-edit"
                                            ></button>
                                            {Number(item.is_archived) === 1 ? (
                                                <button
                                                    onClick={() => onUpdate(item.sku, { is_archived: 0 })}
                                                    className="admin-action-btn btn-icon--refresh"
                                                    data-help-id="inventory-action-restore"
                                                ></button>
                                            ) : (
                                                <button
                                                    onClick={() => onDelete(item.sku)}
                                                    className="admin-action-btn btn-icon--delete"
                                                    data-help-id="inventory-action-delete"
                                                ></button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
};
