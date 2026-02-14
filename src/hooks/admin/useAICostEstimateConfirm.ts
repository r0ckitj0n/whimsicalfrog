import { useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import { useModalContext } from '../../context/ModalContext.js';
import { buildAdminUrl } from '../../core/admin-url-builder.js';
import { ADMIN_SECTION } from '../../core/constants.js';
import type {
    IAICostEstimateOperation,
    IAICostEstimateRequest,
    IAICostEstimateResponse
} from '../../types/ai-cost-estimate.js';

interface IAICostConfirmOptions {
    action_key: string;
    action_label: string;
    operations: IAICostEstimateOperation[];
    context?: IAICostEstimateRequest['context'];
    confirmText?: string;
    /** UI verbosity of the confirm modal. */
    mode?: 'full' | 'minimal';
}

const formatUsd = (value: number): string =>
    `$${Number(value || 0).toFixed(2)}`;

export const useAICostEstimateConfirm = () => {
    const { confirm } = useModalContext();

    const confirmWithEstimate = useCallback(async (options: IAICostConfirmOptions): Promise<boolean> => {
        const {
            action_key,
            action_label,
            operations,
            context,
            confirmText = 'Generate',
            mode = 'minimal'
        } = options;

        if (mode !== 'minimal' && window.WFToast?.info) {
            window.WFToast.info('Estimating AI cost...');
        }

        let estimateResponse: IAICostEstimateResponse | null = null;
        try {
            estimateResponse = await ApiClient.post<IAICostEstimateResponse>('/api/ai_cost_estimate.php', {
                action_key,
                action_label,
                operations,
                context
            });
        } catch (err) {
            console.error('[AI Cost Estimate] request failed', err);
        }

        const estimate = estimateResponse?.estimate;
        if (!estimateResponse?.success || !estimate) {
            return confirm({
                title: 'Confirm AI Generation',
                subtitle: action_label,
                message: 'Could not estimate cost right now. Continue with AI generation anyway?',
                confirmText,
                cancelText: 'Cancel',
                confirmStyle: 'warning',
                iconKey: 'warning'
            });
        }

        if (mode === 'minimal') {
            const fallbackReasons = estimate.pricing?.fallback_reasons || [];
            const primaryFallbackReason = (fallbackReasons[0] || estimate.pricing?.fallback_note || '').trim();
            const pricingNote = estimate.pricing?.is_fallback_pricing
                ? `Fallback pricing in effect.${primaryFallbackReason ? ` Reason: ${primaryFallbackReason}` : ''}`
                : '';

            const lineSummary = estimate.line_items
                .slice(0, 12)
                .map((line) => {
                    const jc = line.job_counts;
                    const jobsText = jc
                        ? ` (jobs: t${jc.text_generation}, a${jc.image_analysis}, i${jc.image_creation})`
                        : '';
                    return `${line.label}: ${formatUsd(line.expected_cost)}${jobsText}`;
                })
                .join('\n');

            const detailsText = [
                `${estimate.provider} • ${estimate.model}`,
                lineSummary ? `\n${lineSummary}` : '',
                pricingNote ? `\n${pricingNote}` : ''
            ].join('').trim();

            return confirm({
                hideHeader: true,
                title: '',
                subtitle: action_label,
                subtitleClassName: 'text-sm font-black text-gray-500 uppercase tracking-widest',
                messageClassName: 'text-base text-gray-700 leading-relaxed font-semibold',
                message: `Estimated AI cost: ${formatUsd(estimate.expected_cost)}.${pricingNote ? ` ${pricingNote}` : ''}`,
                details: detailsText || undefined,
                detailsCollapsible: Boolean(detailsText),
                detailsLabel: 'Details',
                detailsDefaultOpen: false,
                confirmText,
                showCancel: false,
                confirmStyle: 'warning',
                iconKey: estimate.pricing?.is_fallback_pricing ? 'warning' : 'info',
                detailsActions: estimate.pricing?.is_fallback_pricing
                    ? [{
                        label: 'AI Settings',
                        style: 'secondary',
                        href: buildAdminUrl(ADMIN_SECTION.AI_SETTINGS),
                        target: '_blank'
                    }]
                    : undefined
            });
        }

        const fallbackReasons = estimate.pricing?.fallback_reasons || [];
        const primaryFallbackReason = (fallbackReasons[0] || estimate.pricing?.fallback_note || '').trim();
        const pricingNote = estimate.pricing?.is_fallback_pricing
            ? `Fallback pricing in effect.${primaryFallbackReason ? ` Reason: ${primaryFallbackReason}` : ''}`
            : '';

        const lineSummary = estimate.line_items
            .slice(0, 4)
            .map((line) => {
                const jc = line.job_counts;
                const jobsText = jc
                    ? ` (jobs: t${jc.text_generation}, a${jc.image_analysis}, i${jc.image_creation})`
                    : '';
                return `${line.label}: ${formatUsd(line.expected_cost)}${jobsText}`;
            })
            .join(' | ');

        return confirm({
            title: 'Confirm AI Generation',
            subtitle: `${estimate.provider} • ${estimate.model}`,
            message: `${action_label} will run ${estimate.operation_count} AI operation(s). Estimated total: ${formatUsd(estimate.expected_cost)}.${pricingNote ? ` ${pricingNote}` : ''}`,
            details: lineSummary || 'No line-item estimate available.',
            confirmText,
            cancelText: 'Cancel',
            confirmStyle: 'warning',
            iconKey: estimate.pricing?.is_fallback_pricing ? 'warning' : 'info',
            extraActions: estimate.pricing?.is_fallback_pricing
                ? [{
                    label: 'AI Settings',
                    style: 'secondary',
                    href: buildAdminUrl(ADMIN_SECTION.AI_SETTINGS),
                    target: '_blank'
                }]
                : undefined
        });
    }, [confirm]);

    return { confirmWithEstimate };
};
