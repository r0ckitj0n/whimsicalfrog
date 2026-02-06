import React from 'react';

interface ReceiptItem {
    sku: string;
    item_name: string;
    quantity: number;
    price: string;
    ext_price: string;
}

interface ReceiptTableProps {
    items: ReceiptItem[];
    subtotal: string;
    shipping: string;
    tax: string;
    total: string;
}

export const ReceiptTable: React.FC<ReceiptTableProps> = ({
    items,
    subtotal,
    shipping,
    tax,
    total
}) => {
    return (
        <div className="overflow-x-auto mb-8">
            <table className="receipt-table w-full text-sm border-collapse">
                <thead>
                    <tr className="bg-brand-light border-b-2 border-gray-300">
                        <th className="text-left p-2"><span className="font-mono text-xs">Item ID</span></th>
                        <th className="text-left p-2">Item</th>
                        <th className="text-center p-2">Qty</th>
                        <th className="text-right p-2">Unit Price</th>
                        <th className="text-right p-2">Ext. Price</th>
                    </tr>
                </thead>
                <tbody>
                    {items.map((it, idx) => (
                        <tr key={idx} className="border-b border-gray-200">
                            <td className="font-mono text-xs p-2">{it.sku}</td>
                            <td className="p-2">{it.item_name || it.sku}</td>
                            <td className="text-center p-2">{it.quantity}</td>
                            <td className="text-right p-2">${it.price}</td>
                            <td className="text-right p-2">${it.ext_price}</td>
                        </tr>
                    ))}
                </tbody>
                <tfoot className="font-semibold">
                    <tr>
                        <td colSpan={3}></td>
                        <td className="text-right p-2">Subtotal</td>
                        <td className="text-right p-2">${subtotal}</td>
                    </tr>
                    <tr>
                        <td colSpan={3}></td>
                        <td className="text-right p-2">Shipping</td>
                        <td className="text-right p-2">${shipping}</td>
                    </tr>
                    <tr>
                        <td colSpan={3}></td>
                        <td className="text-right p-2">Tax</td>
                        <td className="text-right p-2">${tax}</td>
                    </tr>
                    <tr className="text-lg">
                        <td colSpan={3}></td>
                        <td className="text-right p-2 receipt-total-label"><strong>Total</strong></td>
                        <td className="text-right p-2"><span className="receipt-total">${total}</span></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
};
