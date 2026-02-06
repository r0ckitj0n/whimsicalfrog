import { useState, useEffect, useCallback } from 'react';
import ApiClient from '../core/ApiClient.js';
import logger from '../core/logger.js';
import { useApp } from '../context/AppContext.js';
import { IRoomMetadata, IRoomBackground, IRoomState, IRoomContentResponse, IRoomManagerHook } from '../types/room.js';

// Re-export for backward compatibility
export type { IRoomMetadata, IRoomBackground, IRoomState } from '../types/room.js';

/**
 * Hook for managing room modal state and content loading.
 * Standardized v1.2.8 with wf-modal-open scroll lock.
 */
export const useRoomManager = (): IRoomManagerHook => {
    const { roomState: state, setRoomState: setState } = useApp();

    const closeRoom = useCallback(() => {
        setState(prev => ({ ...prev, isOpen: false, currentRoom: null }));
        document.body.classList.remove('room-modal-open', 'modal-open');
        document.documentElement.classList.remove('modal-open');

        // Clear room-related parameters from URL to prevent automatic re-opening by hooks
        if (typeof window !== 'undefined' && window.location.search) {
            const params = new URLSearchParams(window.location.search);
            const needsUpdate = params.has('room_id') || params.has('room');
            if (needsUpdate) {
                params.delete('room_id');
                params.delete('room');
                const newSearch = params.toString();
                const newUrl = window.location.pathname + (newSearch ? `?${newSearch}` : '') + window.location.hash;
                window.history.replaceState({ ...window.history.state }, '', newUrl);
            }
        }
    }, [setState]);

    const openRoom = useCallback(async (room_number: string | number) => {
        const key = String(room_number);

        setState(prev => ({
            ...prev,
            isOpen: true,
            currentRoom: key,
            isLoading: true,
            content: '<div class="loading">Loading room...</div>',
            background: null,
            metadata: {},
            panelColor: '',
            renderContext: 'modal',
            targetAspectRatio: null
        }));

        // Track last room for receipt redirection
        if (key && key !== '0') {
            localStorage.setItem('wf_last_room', key);
        }

        document.body.classList.add('room-modal-open', 'modal-open');

        try {
            const resp = await ApiClient.get<IRoomContentResponse | string>('/api/load_room_content.php', {
                room: key,
                modal: 1,
                _t: Date.now() // Cache buster
            });

            let content = '';
            let metadata: IRoomMetadata = {};
            let background: IRoomBackground | null = null;
            let panelColor = '';

            if (typeof resp === 'string') {
                content = resp;
            } else if (resp) {
                content = resp.content || '';
                metadata = resp.metadata || {};
                background = resp.background || null;
                panelColor = resp.panel_color || '';
                const renderContext = resp.render_context || 'modal';
                const targetAspectRatio = resp.target_aspect_ratio || null;

                if (content.includes('room-item')) {
                    // Room contains items
                } else {
                    console.warn(`[RoomManager] Room ${key} does NOT contain any .room-item elements`);
                }

                setState(prev => ({
                    ...prev,
                    isLoading: false,
                    content,
                    metadata,
                    background,
                    panelColor,
                    renderContext,
                    targetAspectRatio
                }));
            } else {
                throw new Error('Failed to load room content');
            }

        } catch (err) {
            const errorMsg = err instanceof Error ? err.message : String(err);
            console.error(`[RoomManager] Error loading room ${room_number}:`, err);

            setState(prev => ({
                ...prev,
                isLoading: false,
                content: `
                    <div class="error text-center p-8">
                        <h3 class="text-xl font-bold mb-4">Unable to load room ${room_number}</h3>
                        <div class="bg-red-900/20 p-4 rounded mb-4 inline-block text-left border border-red-500/30">
                            <p class="text-red-500 font-mono text-xs">Error: ${errorMsg}</p>
                            <div class="mt-2 text-[9px] opacity-40">Build: v1.2.8</div>
                        </div>
                        <p class="mb-4">Please try again later.</p>
                        <button class="btn btn-secondary" onclick="window.location.reload()">Refresh Page</button>
                    </div>
                `
            }));
        }
    }, [setState]);

    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && state.isOpen) {
                closeRoom();
            }
        };

        const handleGlobalOpen = (e: Event) => {
            const detail = (e as CustomEvent).detail;
            if (detail?.room_number) {
                openRoom(detail.room_number);
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        window.addEventListener('wf:room:open', handleGlobalOpen);

        return () => {
            window.removeEventListener('keydown', handleKeyDown);
            window.removeEventListener('wf:room:open', handleGlobalOpen);
        };
    }, [state.isOpen, closeRoom, openRoom]);

    return {
        ...state,
        openRoom,
        closeRoom
    };
};

export default useRoomManager;
