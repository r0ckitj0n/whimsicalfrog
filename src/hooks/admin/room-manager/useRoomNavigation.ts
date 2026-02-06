import { useState, useCallback } from 'react';
import { ApiClient } from '../../../core/ApiClient.js';
import {
    IRoomConnection as IConnection,
    IRoomHeaderLink as IHeaderLink,
    IRoomConnectionsResponse,
    IRoomNavigationHook
} from '../../../types/room.js';

export const useRoomNavigation = (): IRoomNavigationHook => {
    const [connections, setConnections] = useState<IConnection[]>([]);
    const [externalLinks, setExternalLinks] = useState<IConnection[]>([]);
    const [headerLinks, setHeaderLinks] = useState<IHeaderLink[]>([]);
    const [isDetecting, setIsDetecting] = useState(false);

    const fetchConnections = useCallback(async () => {
        try {
            const data = await ApiClient.get<IRoomConnectionsResponse>('/api/room_connections.php', { action: 'get_all' });
            const responseData = data?.data || data;
            setConnections(responseData?.connections || []);
            setExternalLinks(responseData?.external_links || []);
            setHeaderLinks(responseData?.header_links || []);
        } catch (err) {
            console.error('[Navigation] Failed to fetch connections', err);
        }
    }, []);

    const handleDetectConnections = useCallback(async () => {
        setIsDetecting(true);
        try {
            const data = await ApiClient.get<IRoomConnectionsResponse>('/api/room_connections.php', { action: 'detect_connections' });
            if (data?.success) {
                if (window.WFToast) window.WFToast.success('Connections detected');
                fetchConnections();
            } else {
                if (window.WFToast) window.WFToast.error(data?.error || 'Detection failed');
            }
        } catch (err) {
            if (window.WFToast) window.WFToast.error('Network error during detection');
        } finally {
            setIsDetecting(false);
        }
    }, [fetchConnections]);

    const handleSaveConnections = useCallback(async (roomId: string, currentTabConnections: IConnection[]) => {
        try {
            const data = await ApiClient.post<IRoomConnectionsResponse>('/api/room_connections.php', {
                action: 'save_room_connections',
                room_number: roomId,
                connections: JSON.stringify(currentTabConnections)
            });
            if (data?.success) {
                if (window.WFToast) window.WFToast.success('Connections saved');
                fetchConnections();
            } else {
                if (window.WFToast) window.WFToast.error(data?.error || 'Failed to save');
            }
        } catch (err) {
            if (window.WFToast) window.WFToast.error('Network error saving connections');
        }
    }, [fetchConnections]);

    return {
        connections,
        setConnections,
        externalLinks,
        setExternalLinks,
        headerLinks,
        setHeaderLinks,
        isDetecting,
        fetchConnections,
        handleDetectConnections,
        handleSaveConnections
    };
};
