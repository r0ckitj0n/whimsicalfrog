import { useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import { useModalContext } from '../../context/ModalContext.js';
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
}

const formatUsd = (value: number): string =>
    `$${Number(value || 0).toFixed(4)}`;

export const useAICostEstimateConfirm = () => {
    const { confirm } = useModalContext();

    const confirmWithEstimate = useCallback(async (options: IAICostConfirmOptions): Promise<boolean> => {
        const {
            action_key,
            action_label,
            operations,
            context,
            confirmText = 'Generate'
        } = options;

        if (window.WFToast?.info) {
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

        const lineSummary = estimate.line_items
            .slice(0, 4)
            .map((line) => `${line.label}: ${formatUsd(line.expected_cost)}`)
            .join(' | ');

        return confirm({
            title: 'Confirm AI Generation',
            subtitle: `${estimate.provider} • ${estimate.model}`,
            message: `${action_label} will run ${estimate.operation_count} AI operation(s). Estimated total: ${formatUsd(estimate.expected_cost)} (range ${formatUsd(estimate.min_cost)} to ${formatUsd(estimate.max_cost)}).`,
            details: lineSummary || 'No line-item estimate available.',
            confirmText,
            cancelText: 'Cancel',
            confirmStyle: 'warning',
            iconKey: 'info'
        });
    }, [confirm]);

    return { confirmWithEstimate };
};
