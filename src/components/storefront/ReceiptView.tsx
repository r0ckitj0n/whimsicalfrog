import React from 'react';
import { PAYMENT_STATUS, PAGE } from '../../core/constants.js';
import { ReceiptHeader } from './receipt/ReceiptHeader.js';
import { ReceiptTable } from './receipt/ReceiptTable.js';
import { ReceiptStatus } from './receipt/ReceiptStatus.js';
import { ReceiptSalesVerbiage } from './receipt/ReceiptSalesVerbiage.js';
import { IReceiptData } from '../../types/index.js';

interface ReceiptViewProps {
    data: IReceiptData;
}

export const ReceiptView: React.FC<ReceiptViewProps> = ({ data }) => {
    const page = document.body.getAttribute('data-page');
    const {
        order_id,
        date,
        payment_status,
        items,
        subtotal,
        discount,
        coupon_code,
        shipping,
        tax,
        total,
        receipt_message,
        sales_verbiage,
        business_info,
        policy_links
    } = data;

    // if (page !== PAGE.RECEIPT) return null;

    const isPending = payment_status === PAYMENT_STATUS.PENDING;
    const remitName = business_info.owner || business_info.name;

    return (
        <div className="container mx-auto px-4 py-8 max-w-4xl">
            <div className="receipt-container card-standard bg-white shadow-lg rounded-lg p-6">
                <ReceiptHeader business_info={business_info} />

                {/* Order Info */}
                <div className="text-center receipt-message-center mb-8 border-b pb-4">
                    <h2 className="text-brand-primary wf-brand-font text-2xl font-semibold">Order Receipt</h2>
                    <p className="text-sm text-brand-secondary">Order ID: <strong>{order_id}</strong></p>
                    <p className="text-sm text-brand-secondary">Date: {date}</p>
                </div>

                <ReceiptTable
                    items={items}
                    subtotal={subtotal}
                    discount={discount}
                    coupon_code={coupon_code}
                    shipping={shipping}
                    tax={tax}
                    total={total}
                />

                <ReceiptStatus
                    isPending={isPending}
                    business_name={business_info.name}
                    remitName={remitName}
                    address_block={business_info.address_block}
                    receipt_message={receipt_message}
                />

                <ReceiptSalesVerbiage sales_verbiage={sales_verbiage} />
            </div>

            {/* Footer Links */}
            {policy_links.length > 0 && (
                <div className="text-center text-xs text-gray-600 mt-6 flex justify-center items-center space-x-2">
                    {policy_links.map((link, idx) => (
                        <React.Fragment key={idx}>
                            <a className="link-brand hover:underline" href={link.url}>{link.label}</a>
                            {idx < policy_links.length - 1 && <span className="text-gray-400">•</span>}
                        </React.Fragment>
                    ))}
                </div>
            )}
        </div>
    );
};

export default ReceiptView;
