import React, { useEffect, useState, useRef } from 'react';
import { useRoomCoordinates } from '../hooks/useRoomCoordinates.js';
import { useLocation } from 'react-router-dom';
import { ApiClient } from '../core/ApiClient.js';

interface IDoorDestination {
    area_selector: string;
    target: string;
    label: string;
    image: string;
}

/**
 * LandingPage - Visual Golden Master Refactor
 * Recreates the exact look and feel of https://whimsicalfrog.us
 * Uses Tailwind for layout and brand-matching effects.
 * Sign images are fetched from the database (door_sign_destinations API).
 */
export const LandingPage: React.FC = () => {
    const location = useLocation();
    const params = new URLSearchParams(location.search);
    const section = params.get('section');
    const roomIdParam = params.get('room_id');
    const isVisible = (roomIdParam === 'A') || (!roomIdParam && (location.pathname === '/' || location.pathname === '/index.html') && !section);
    const containerRef = useRef<HTMLDivElement>(null);

    const [destinations, setDestinations] = useState<IDoorDestination[]>([]);
    const [bgUrl, setBgUrl] = useState('');

    const {
        coordinates,
        isLoading,
        setContainerSize,
        getScaledStyles,
        roomSettings
    } = useRoomCoordinates('A');

    // Get icon panel color from database settings
    const iconPanelColor = roomSettings?.icon_panel_color || 'transparent';

    useEffect(() => {
        if (!isVisible) return;

        const loadData = async () => {
            try {
                // Fetch door sign destinations from database
                const destData = await ApiClient.get<{ destinations: IDoorDestination[] }>(
                    '/api/area_mappings.php',
                    { action: 'door_sign_destinations', room: 'A' }
                );
                if (destData?.destinations) {
                    setDestinations(destData.destinations);
                }

                // Fetch background from database
                const bgData = await ApiClient.get<{ background: { webp_filename?: string; png_filename?: string; image_filename?: string } }>(
                    '/api/get_background.php',
                    { room: 'A' }
                );
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
                console.error('[LandingPage] Failed to load data', err);
            }
        };

        loadData();

        // Fullscreen mode ensures the background covers the viewport without scrollbars
        document.body.classList.add('mode-fullscreen');

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

        return () => {
            window.removeEventListener('resize', handleResize);
            document.body.classList.remove('mode-fullscreen');
        };
    }, [isVisible, setContainerSize]);

    if (!isVisible) return null;

    const worldCoord: { top: number; left: number; width: number; height: number; selector: string } = { top: 0, left: 0, width: 1280, height: 896, selector: 'world' };
    const worldStyles = getScaledStyles(worldCoord);

    return (
        <div
            ref={containerRef}
            id="landingPage-react"
            className="fixed inset-0 w-full h-full overflow-hidden bg-black z-base transition-opacity duration-700"
        >
            <div
                className="absolute inset-0 pointer-events-none overflow-hidden"
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
                        <a
                            key={idx}
                            href="/room_main"
                            className={`room-item-icon absolute group transition-opacity duration-300 overflow-visible ${!isLoading && coordinates.length > 0 ? 'opacity-100' : 'opacity-0'}`}
                            style={{
                                ...styles,
                                height: 'var(--door-height)',
                                backgroundColor: iconPanelColor || 'transparent',
                                borderRadius: hasPanelColor ? '10px' : undefined,
                                padding: hasPanelColor ? '6px' : '0',
                                boxSizing: 'border-box',
                                overflow: 'visible',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center'
                            }}
                        >
                            <picture className="block w-full h-full">
                                <source srcSet={imgWebp} type="image/webp" />
                                <img
                                    src={imgUrl}
                                    alt=""
                                    className="w-full h-full"
                                    style={{
                                        objectFit: 'contain',
                                        transition: 'filter 0.3s ease',
                                        willChange: 'filter'
                                    }}
                                    onMouseEnter={(e) => (e.currentTarget.style.filter = 'brightness(1.1)')}
                                    onMouseLeave={(e) => (e.currentTarget.style.filter = 'none')}
                                    loading="eager"
                                />
                            </picture>
                        </a>
                    );
                })}
            </div>
        </div>
    );
};
