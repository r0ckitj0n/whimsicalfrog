import type { FC } from 'react';
import { AISuggestions as AISuggestionsImpl } from '../../../hooks/admin/useAISuggestionsView.js';

type AISuggestionsProps = {
    onClose?: () => void;
    title?: string;
};

export const AISuggestions: FC<AISuggestionsProps> = (props) => <AISuggestionsImpl {...props} />;
