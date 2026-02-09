export interface IAIPromptTemplate {
    id: number;
    template_key: string;
    template_name: string;
    description?: string;
    context_type: string;
    prompt_text: string;
    is_active: number | boolean;
    created_at?: string;
    updated_at?: string;
}

export interface IAIPromptVariable {
    id: number;
    variable_key: string;
    display_name: string;
    description?: string;
    sample_value?: string;
    is_active: number | boolean;
    created_at?: string;
    updated_at?: string;
}

export type IAIPromptDropdownOptionsByVariable = Record<string, string[]>;

export interface IAIGenerationHistoryRow {
    id: number;
    template_key: string;
    provider?: string;
    model?: string;
    status: string;
    output_type?: string;
    output_path?: string;
    room_number?: string;
    error_message?: string;
    created_by?: string;
    created_at: string;
}

export interface IAIPromptDropdownOptionsResponse {
    success: boolean;
    options_by_variable?: IAIPromptDropdownOptionsByVariable;
    error?: string;
}

export interface IAIPromptTemplatesResponse {
    success: boolean;
    templates?: IAIPromptTemplate[];
    error?: string;
}

export interface IAIPromptVariablesResponse {
    success: boolean;
    variables?: IAIPromptVariable[];
    error?: string;
}

export interface IAIPromptTemplateActionResponse {
    success: boolean;
    message?: string;
    error?: string;
}
