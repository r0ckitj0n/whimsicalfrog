import React, { useEffect, useState, useRef } from 'react';
import { useRoomCoordinates } from '../hooks/useRoomCoordinates.js';
import { ApiClient } from '../core/ApiClient.js';
import { useLocation } from 'react-router-dom';
import logger from '../core/logger.js';
import useRoomManager from '../hooks/use-room-manager.js';

interface IDoorDestination {
    area_selector: string;
    target: string;
    label: string;
    image: string;
}

/**
 * MainRoom - Visual Golden Master Refactor
 * Recreates the interactive navigation room with clean Tailwind patterns.
 */
export const MainRoom: React.FC = () => {
    const location = useLocation();
    const params = new URLSearchParams(location.search);
    const section = params.get('section');
    const roomIdParam = params.get('room_id');
    const isVisible = (roomIdParam === '0') || (!roomIdParam && location.pathname.includes('/room_main') && !section);

    const [destinations, setDestinations] = useState<IDoorDestination[]>([]);
    const [bgUrl, setBgUrl] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);
    const { openRoom } = useRoomManager();

    const {
        coordinates,
        isLoading,
        setContainerSize,
        getScaledStyles,
        roomSettings
    } = useRoomCoordinates('0');

    // Get icon panel color from database settings
    const iconPanelColor = roomSettings?.icon_panel_color || 'transparent';

    useEffect(() => {
        if (!isVisible) return;

        const loadData = async () => {
            try {
                // Load destinations
                const data = await ApiClient.get<{ destinations: IDoorDestination[] }>('/api/area_mappings.php', { action: 'door_sign_destinations' });
                if (data?.destinations) {
                    setDestinations(data.destinations);
                }

                // Fetch background
                const bgData = await ApiClient.get<{ background: { webp_filename?: string; png_filename?: string; image_filename?: string } }>('/api/get_background.php', { room: '0' });
                const fetchedBg = bgData?.background?.webp_filename || bgData?.background?.png_filename || bgData?.background?.image_filename;

                if (fetchedBg) {
                    const buildUrl = (v: string) => {
                        if (!v) return '';
                        if (/^https?:\/\//i.test(v)) return v;
                        // Clean up redundant path segments from DB
                        const cleanPath = v.replace(/^backgrounds\//, '').replace(/^\//, '');
                        return `/images/backgrounds/${cleanPath}`;
                    };
                    setBgUrl(buildUrl(fetchedBg));
                }
            } catch (err) {
                logger.error('[MainRoom] Failed to load room data', err);
            }
        };

        loadData();

        const handleResize = () => {
            if (containerRef.current) {
                setContainerSize({
                    width: containerRef.current.clientWidth,
                    height: containerRef.current.clientHeight
                });
            }
        };

        window.addEventListener('resize', handleResize);
        handleResize();

        return () => window.removeEventListener('resize', handleResize);
    }, [isVisible, setContainerSize]);

    const handleDoorClick = (dest: IDoorDestination) => {
        const target = String(dest.target || '');
        if (!target) return;

        if (/^\d+$/.test(target)) {
            openRoom(target);
        } else if (target.startsWith('room:')) {
            const num = target.split(':')[1];
            openRoom(num);
        } else if (target.startsWith('.') || target.startsWith('#')) {
            const el = document.querySelector(target) as HTMLElement;
            if (el) el.click();
        } else {
            window.location.href = target;
        }
    };

    if (!isVisible) return null;

    const worldCoord: { top: number; left: number; width: number; height: number; selector: string } = { top: 0, left: 0, width: 1280, height: 896, selector: 'world' };
    const worldStyles = getScaledStyles(worldCoord);

    return (
        <section
            ref={containerRef}
            id="mainRoomPage-react"
            className="fixed inset-0 w-full h-full overflow-hidden bg-black z-base transition-opacity duration-700"
        >
            <div
                className="absolute inset-0 pointer-events-none"
                style={{
                    ...worldStyles,
                    zIndex: 0,
                    backgroundImage: bgUrl ? `url(${bgUrl})` : 'none',
                    backgroundSize: '100% 100%',
                    backgroundPosition: 'center',
                    backgroundRepeat: 'no-repeat'
                }}
            />
            <div
                className="relative w-full h-full flex items-center justify-center room-items-container"
                style={{ '--icon-panel-color': iconPanelColor } as React.CSSProperties}
            >
                {destinations.map((dest, idx) => {
                    const selector = dest.area_selector.startsWith('.') ? dest.area_selector : `.${dest.area_selector}`;
                    const coord = coordinates.find(c => c.selector.toLowerCase() === selector.toLowerCase());

                    if (!coord) return null;

                    const styles = getScaledStyles(coord);
                    const imgUrl = dest.image.startsWith('/') ? dest.image : `/${dest.image}`;
                    const imgWebp = imgUrl.replace(/\.png$/, '.webp');
                    const hasPanelColor = !!iconPanelColor && iconPanelColor !== 'transparent';

                    return (
                        <div
                            key={idx}
                            className={`room-item-icon absolute group cursor-pointer transition-opacity duration-300 overflow-visible ${!isLoading && coordinates.length > 0 ? 'opacity-100' : 'opacity-0'}`}
                            style={{
                                ...styles,
                                height: 'var(--door-height)',
                                backgroundColor: iconPanelColor || 'transparent',
                                borderRadius: hasPanelColor ? '10px' : undefined,
                                padding: hasPanelColor ? '6px' : '0',
                                boxSizing: 'border-box',
                                overflow: 'visible',
                                display: 'flex',
                                alignItems: 'flex-start',
                                justifyContent: 'center'
                            }}
                            onClick={() => handleDoorClick(dest)}
                        >
                            <picture
                                className="block w-full h-full"
                                style={{
                                    display: 'flex',
                                    alignItems: 'flex-start',
                                    justifyContent: 'center',
                                    overflow: 'visible'
                                }}
                            >
                                <source srcSet={imgWebp} type="image/webp" />
                                <img
                                    src={imgUrl}
                                    alt=""
                                    className="block"
                                    style={{
                                        width: '100%',
                                        height: 'auto',
                                        maxWidth: '100%',
                                        transition: 'filter 0.3s ease',
                                        willChange: 'filter'
                                    }}
                                    onMouseEnter={(e) => (e.currentTarget.style.filter = 'brightness(1.1) drop-shadow(0 0 15px rgba(135,172,58,0.5))')}
                                    onMouseLeave={(e) => (e.currentTarget.style.filter = 'none')}
                                    loading="lazy"
                                />
                            </picture>
                        </div>
                    );

                })}
            </div>
        </section>
    );
};
