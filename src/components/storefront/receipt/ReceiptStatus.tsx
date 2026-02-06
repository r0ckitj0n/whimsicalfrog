import React from 'react';

interface ReceiptStatusProps {
    isPending: boolean;
    business_name: string;
    remitName: string;
    address_block: string;
    receipt_message: {
        title: string;
        content: string;
    };
}

export const ReceiptStatus: React.FC<ReceiptStatusProps> = ({
    isPending,
    business_name,
    remitName,
    address_block,
    receipt_message
}) => {
    if (isPending) {
        return (
            <div className="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-8 receipt-message-center rounded" role="alert">
                <p className="font-bold text-brand-primary wf-brand-font mb-2">Thank you for choosing {business_name}!</p>
                <p className="mb-4">Your order is reserved and will be shipped as soon as we receive your payment 🙂</p>
                <div className="mb-4">
                    <p className="font-semibold">Remit payment to:</p>
                    <p>
                        <strong>{remitName}</strong><br />
                        {address_block && (
                            <span dangerouslySetInnerHTML={{ __html: address_block.replace(/\n/g, '<br/>') }} />
                        )}
                    </p>
                </div>
                <p className="text-sm">Please include your order ID on the memo line.<br />As soon as we record your payment we'll send a confirmation e-mail and get your items on their way.</p>
            </div>
        );
    }

    return (
        <div className="card-standard text-sm receipt-message-center p-4 mb-8 bg-brand-light rounded" role="alert">
            <p className="font-bold text-brand-primary wf-brand-font mb-1">{receipt_message.title}</p>
            <p className="text-brand-secondary wf-brand-font">{receipt_message.content}</p>
        </div>
    );
};
