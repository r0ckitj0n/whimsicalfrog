import React from 'react';

interface ReceiptSalesVerbiageProps {
    sales_verbiage: Record<string, string>;
}

export const ReceiptSalesVerbiage: React.FC<ReceiptSalesVerbiageProps> = ({ sales_verbiage }) => {
    if (Object.keys(sales_verbiage).length === 0) return null;

    return (
        <div className="border-t pt-6 space-y-4">
            {sales_verbiage.receipt_thank_you_message && (
                <div className="card-standard receipt-message-center p-3 bg-white border rounded">
                    <p className="text-sm text-brand-primary">🎉 {sales_verbiage.receipt_thank_you_message}</p>
                </div>
            )}
            {sales_verbiage.receipt_next_steps && (
                <div className="card-standard receipt-message-center p-3 bg-white border rounded">
                    <p className="text-sm text-brand-primary">📋 {sales_verbiage.receipt_next_steps}</p>
                </div>
            )}
            {sales_verbiage.receipt_social_sharing && (
                <div className="card-standard receipt-message-center p-3 bg-white border rounded">
                    <p className="text-sm text-brand-primary">📱 {sales_verbiage.receipt_social_sharing}</p>
                </div>
            )}
            {sales_verbiage.receipt_return_customer && (
                <div className="card-standard receipt-message-center p-3 bg-white border rounded">
                    <p className="text-sm text-brand-primary">🎨 {sales_verbiage.receipt_return_customer}</p>
                </div>
            )}
        </div>
    );
};
