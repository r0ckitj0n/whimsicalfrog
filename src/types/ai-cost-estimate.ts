export interface IAICostEstimateOperation {
    key: string;
    label?: string;
    count?: number;
    image_count?: number;
    image_generations?: number;
}

export interface IAICostEstimateRequest {
    action_key: string;
    action_label?: string;
    operations?: IAICostEstimateOperation[];
    context?: {
        image_count?: number;
        prompt_length?: number;
        name_length?: number;
        description_length?: number;
        category_length?: number;
    };
}

export interface IAICostEstimateLineItem {
    key: string;
    label: string;
    estimated_input_tokens: number;
    estimated_output_tokens: number;
    image_count: number;
    image_generations: number;
    expected_cost: number;
    min_cost: number;
    max_cost: number;
    reasoning?: string;
}

export interface IAICostEstimatePayload {
    provider: string;
    model: string;
    currency: 'USD';
    source: 'ai' | 'heuristic';
    expected_cost: number;
    min_cost: number;
    max_cost: number;
    operation_count: number;
    line_items: IAICostEstimateLineItem[];
    assumptions: string[];
}

export interface IAICostEstimateResponse {
    success: boolean;
    estimate?: IAICostEstimatePayload;
    error?: string;
}
