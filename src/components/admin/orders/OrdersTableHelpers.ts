import { ORDER_STATUS, PAYMENT_STATUS } from '../../../core/constants.js';
import { formatDateTime } from '../../../core/date-utils.js';

export const getStatusBadgeClass = (status: string) => {
    const s = String(status || '');
    switch (s) {
        case ORDER_STATUS.PENDING: return 'text-[var(--brand-secondary)]';
        case ORDER_STATUS.PROCESSING: return 'text-[var(--brand-primary)]';
        case ORDER_STATUS.SHIPPED: return 'text-[var(--brand-primary)]';
        case ORDER_STATUS.DELIVERED: return 'text-[var(--brand-accent)]';
        case ORDER_STATUS.CANCELLED: return 'text-[var(--brand-error)]';
        default: return 'text-gray-700';
    }
};

export const getPaymentStatusBadgeClass = (status: string) => {
    const s = String(status || '').toLowerCase();
    switch (s) {
        case PAYMENT_STATUS.PENDING.toLowerCase(): return 'text-[var(--brand-secondary)]';
        case 'received': return 'text-[var(--brand-accent)]';
        case PAYMENT_STATUS.PAID.toLowerCase():
        case 'paid': return 'text-[var(--brand-primary)]';
        case PAYMENT_STATUS.REFUNDED.toLowerCase(): return 'text-[var(--brand-secondary)]';
        case PAYMENT_STATUS.FAILED.toLowerCase(): return 'text-[var(--brand-error)]';
        default: return 'text-gray-600';
    }
};

export { formatDate, formatDateTime } from '../../../core/date-utils.js';
