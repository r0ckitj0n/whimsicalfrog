import React, { useState, useEffect } from 'react';
import _Editor from 'react-simple-code-editor';
// Handle ESM/CJS module interop - use unknown → ComponentType to avoid as any
interface EditorProps { value: string; onValueChange: (value: string) => void; highlight: (code: string) => string; padding: number; style: React.CSSProperties; textareaClassName?: string; }
const Editor = (((_Editor as unknown as { default?: React.ComponentType<EditorProps> }).default || _Editor) as React.ComponentType<EditorProps>);
import { highlight, languages } from 'prismjs';
import 'prismjs/components/prism-css';
import 'prismjs/themes/prism-tomorrow.css';
import { ApiClient } from '../../../../core/ApiClient.js';
import logger from '../../../../core/logger.js';
import { useUnsavedChangesCloseGuard } from '../../../../hooks/useUnsavedChangesCloseGuard.js';

interface CssEditorModalProps {
    filePath: string;
    targetClass?: string;
    onClose: () => void;
}

export const CssEditorModal: React.FC<CssEditorModalProps> = ({ filePath, targetClass, onClose }) => {
    const [code, setCode] = useState('');
    const [originalCode, setOriginalCode] = useState('');
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [saveSuccess, setSaveSuccess] = useState(false);

    const isDirty = code !== originalCode;
    const fileName = filePath.split('/').pop() || filePath;

    useEffect(() => {
        loadFile();
    }, [filePath]);

    const loadFile = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const response = await ApiClient.get<{ success: boolean; content: string; path: string }>(
                `/api/css_file_editor.php?file=${encodeURIComponent(filePath)}`
            );
            if (response && response.success) {
                setCode(response.content);
                setOriginalCode(response.content);

                // Scroll to target class if specified
                if (targetClass) {
                    setTimeout(() => scrollToClass(targetClass), 100);
                }
            } else {
                setError('Failed to load file');
            }
        } catch (err) {
            logger.error('[CssEditorModal] Failed to load file', err);
            setError('Unable to load file');
        } finally {
            setIsLoading(false);
        }
    };

    const scrollToClass = (className: string) => {
        // Find the line containing the class definition
        const lines = code.split('\n');
        const targetLine = lines.findIndex(line =>
            line.includes(className) && (line.includes('{') || lines[lines.indexOf(line) + 1]?.includes('{'))
        );

        if (targetLine !== -1) {
            // Scroll to that line (approximate)
            const editorElement = document.querySelector('.css-editor-container textarea');
            if (editorElement instanceof HTMLTextAreaElement) {
                const lineHeight = 20; // Approximate line height
                editorElement.scrollTop = targetLine * lineHeight;
            }
        }
    };

    const handleSave = async (): Promise<boolean> => {
        setIsSaving(true);
        setError(null);
        setSaveSuccess(false);
        try {
            const response = await ApiClient.post<{ success: boolean; message?: string }>(
                '/api/css_file_editor.php',
                { file: filePath, content: code }
            );
            if (response && response.success) {
                setOriginalCode(code);
                setSaveSuccess(true);
                setTimeout(() => setSaveSuccess(false), 3000);
                return true;
            } else {
                setError(response?.message || 'Failed to save file');
                return false;
            }
        } catch (err) {
            logger.error('[CssEditorModal] Failed to save file', err);
            setError('Unable to save file');
            return false;
        } finally {
            setIsSaving(false);
        }
    };
    const handleClose = useUnsavedChangesCloseGuard({
        isDirty,
        isBlocked: isSaving,
        onClose,
        onSave: handleSave,
        closeAfterSave: true
    });

    return (
        <div
            className="admin-modal-overlay over-header show topmost"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) void handleClose();
            }}
        >
            <div
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1400px] max-w-[95vw] h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">📝</span> CSS Editor
                    </h2>
                    <div className="flex-1 flex items-center gap-2">
                        <span className="text-sm text-gray-500">Editing:</span>
                        <code className="text-sm font-mono bg-gray-100 px-2 py-1 rounded">{fileName}</code>
                        {isDirty && <span className="text-xs text-orange-500 font-bold">● UNSAVED</span>}
                        {saveSuccess && <span className="text-xs text-green-500 font-bold">✓ SAVED</span>}
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={handleSave}
                            disabled={!isDirty || isSaving}
                            className={`admin-action-btn btn-icon--save dirty-only ${isDirty ? 'is-dirty' : ''}`}
                            data-help-id="common-save"
                        />
                        <button
                            onClick={() => { void handleClose(); }}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="common-close"
                        />
                    </div>
                </div>

                {/* Editor Body */}
                <div className="modal-body flex-1 overflow-hidden p-0">
                    {isLoading ? (
                        <div className="flex flex-col items-center justify-center h-full text-gray-500">
                            <span className="wf-emoji-loader">📄</span>
                            <p>Loading file...</p>
                        </div>
                    ) : error ? (
                        <div className="flex flex-col items-center justify-center h-full">
                            <div className="p-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl max-w-md">
                                <span className="text-xl mr-2">⚠️</span> {error}
                            </div>
                            <button
                                onClick={loadFile}
                                className="mt-4 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-bold"
                            >
                                Retry
                            </button>
                        </div>
                    ) : (
                        <div className="css-editor-container h-full overflow-auto bg-[#2d2d2d] p-4">
                            <Editor
                                value={code}
                                onValueChange={setCode}
                                highlight={(code: string) => highlight(code, languages.css, 'css')}
                                padding={10}
                                style={{
                                    fontFamily: '"Fira Code", "Courier New", monospace',
                                    fontSize: 14,
                                    minHeight: '100%',
                                    backgroundColor: '#2d2d2d',
                                    color: '#ccc',
                                }}
                                textareaClassName="focus:outline-none"
                            />
                        </div>
                    )}
                </div>


            </div>
        </div>
    );
};
