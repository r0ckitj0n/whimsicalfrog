<?php

if (!function_exists('wf_profile_required_fields')) {
    /**
     * Required profile fields for a customer account to be considered complete.
     *
     * @return array<int, string>
     */
    function wf_profile_required_fields(): array
    {
        return [
            'first_name',
            'last_name',
            'email',
            'address_line_1',
            'city',
            'state',
            'zip_code',
        ];
    }
}

if (!function_exists('wf_profile_missing_fields')) {
    /**
     * Returns list of required profile fields that are missing/blank.
     *
     * @param array<string, mixed> $user
     * @return array<int, string>
     */
    function wf_profile_missing_fields(array $user): array
    {
        $missing = [];
        foreach (wf_profile_required_fields() as $field) {
            $value = $user[$field] ?? null;
            if (!is_scalar($value) || trim((string) $value) === '') {
                $missing[] = $field;
            }
        }
        return $missing;
    }
}

