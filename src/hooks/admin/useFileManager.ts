import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { API_ACTION, FILE_TYPE } from '../../core/constants.js';
import type { IFileItem, IFileContent, IFileDirectoryResponse, IFileReadResponse } from '../../types/files.js';

// Re-export for backward compatibility
export type { IFileItem, IFileContent, IFileDirectoryResponse, IFileReadResponse } from '../../types/files.js';



export const useFileManager = () => {
    const [currentDirectory, setCurrentDirectory] = useState('');
    const [files, setFiles] = useState<IFileItem[]>([]);
    const [parentDirectory, setParentDirectory] = useState<string | null>(null);
    const [currentFile, setCurrentFile] = useState<IFileContent | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const loadDirectory = useCallback(async (path: string = '') => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IFileDirectoryResponse>('/api/file_manager.php', { action: API_ACTION.LIST, path });
            if (res?.success) {
                setFiles(res.items || []);
                setCurrentDirectory(res.path || '');
                setParentDirectory(res.parent ?? null);
            } else {
                setError(res?.error || 'Failed to load directory');
            }
        } catch (err: unknown) {
            logger.error('loadDirectory failed', err);
            setError('Failed to connect to file service');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const viewFile = useCallback(async (path: string) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IFileReadResponse>('/api/file_manager.php', { action: API_ACTION.READ, path });
            if (res?.success) {
                setCurrentFile(res);
                return res;
            } else {
                setError(res?.error || 'Failed to read file');
                return null;
            }
        } catch (err: unknown) {
            logger.error('viewFile failed', err);
            setError('Failed to connect to file service');
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveFile = useCallback(async (path: string, content: string) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string }>(`/api/file_manager.php?action=${API_ACTION.WRITE}`, { path, content });
            if (res?.success) {
                if (currentFile && currentFile.path === path) {
                    setCurrentFile({ ...currentFile, content });
                }
                return true;
            } else {
                setError(res?.error || 'Failed to save file');
                return false;
            }
        } catch (err: unknown) {
            logger.error('saveFile failed', err);
            setError('Failed to connect to file service');
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [currentFile]);

    const deleteItem = useCallback(async (path: string, type: typeof FILE_TYPE[keyof typeof FILE_TYPE]) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.delete<{ success: boolean; error?: string }>(`/api/file_manager.php?action=${API_ACTION.DELETE}&path=${encodeURIComponent(path)}`);
            if (res?.success) {
                await loadDirectory(currentDirectory);
                if (currentFile && currentFile.path === path) setCurrentFile(null);
                return true;
            } else {
                setError(res?.error || 'Failed to delete item');
                return false;
            }
        } catch (err: unknown) {
            logger.error('deleteItem failed', err);
            setError('Failed to connect to file service');
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [currentDirectory, currentFile, loadDirectory]);

    const createFolder = useCallback(async (name: string) => {
        setIsLoading(true);
        setError(null);
        try {
            const path = currentDirectory ? `${currentDirectory}/${name}` : name;
            const res = await ApiClient.post<{ success: boolean; error?: string }>(`/api/file_manager.php?action=${API_ACTION.MKDIR}`, { path });
            if (res?.success) {
                await loadDirectory(currentDirectory);
                return true;
            } else {
                setError(res?.error || 'Failed to create folder');
                return false;
            }
        } catch (err: unknown) {
            logger.error('createFolder failed', err);
            setError('Failed to connect to file service');
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [currentDirectory, loadDirectory]);

    const createFile = useCallback(async (name: string) => {
        setIsLoading(true);
        setError(null);
        try {
            const path = currentDirectory ? `${currentDirectory}/${name}` : name;
            const res = await ApiClient.post<{ success: boolean; error?: string }>(`/api/file_manager.php?action=${API_ACTION.WRITE}`, { path, content: '' });
            if (res?.success) {
                await loadDirectory(currentDirectory);
                return true;
            } else {
                setError(res?.error || 'Failed to create file');
                return false;
            }
        } catch (err: unknown) {
            logger.error('createFile failed', err);
            setError('Failed to connect to file service');
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [currentDirectory, loadDirectory]);

    const navigateUp = useCallback(() => {
        if (currentDirectory !== '' && parentDirectory !== null) {
            loadDirectory(parentDirectory === '.' ? '' : parentDirectory);
        }
    }, [currentDirectory, parentDirectory, loadDirectory]);

    return {
        currentDirectory,
        files,
        currentFile,
        isLoading,
        error,
        loadDirectory,
        viewFile,
        saveFile,
        deleteItem,
        createFolder,
        createFile,
        navigateUp,
        refreshDirectory: () => loadDirectory(currentDirectory)
    };
};
