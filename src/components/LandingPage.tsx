import React, { useEffect, useState, useRef } from 'react';
import { useRoomCoordinates } from '../hooks/useRoomCoordinates.js';
import { useLocation, useNavigate } from 'react-router-dom';
import { ApiClient } from '../core/ApiClient.js';
import { resolveBackgroundAssetUrl } from '../utils/background-url.js';
import type { IDoorDestination } from '../types/room.js';

/**
 * LandingPage - Visual Golden Master Refactor
 * Recreates the exact look and feel of https://whimsicalfrog.us
 * Uses Tailwind for layout and brand-matching effects.
 * Sign images are fetched from the database (door_sign_destinations API).
 */
export const LandingPage: React.FC = () => {
    const location = useLocation();
    const navigate = useNavigate();
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
        if (typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches) {
            navigate('/shop', { replace: true });
            return;
        }

        const loadData = async () => {
            const [destRes, bgRes] = await Promise.allSettled([
                ApiClient.get<{ destinations: IDoorDestination[] }>(
                    '/api/area_mappings.php',
                    { action: 'door_sign_destinations', room: 'A' }
                ),
                ApiClient.get<{ background: { webp_filename?: string; png_filename?: string; image_filename?: string } }>(
                    '/api/get_background.php',
                    { room: 'A' }
                )
            ]);

            if (destRes.status === 'fulfilled') {
                if (destRes.value?.destinations) {
                    setDestinations(destRes.value.destinations);
                }
            } else {
                console.error('[LandingPage] Failed to load door sign destinations', destRes.reason);
            }

            if (bgRes.status === 'fulfilled') {
                const fetchedBg = bgRes.value?.background?.webp_filename
                    || bgRes.value?.background?.png_filename
                    || bgRes.value?.background?.image_filename;

                if (fetchedBg) {
                    setBgUrl(resolveBackgroundAssetUrl(fetchedBg));
                }
            } else {
                console.error('[LandingPage] Failed to load background', bgRes.reason);
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

    const resolveHref = (target: string): string => {
        const t = (target || '').trim();
        if (!t) return '/room_main';

        if (/^https?:\/\//i.test(t)) return t;
        // Common legacy PHP entrypoints (accept both '/shop.php' and 'shop.php')
        const php = t.replace(/^\//, '');
        if (php === 'shop.php' || t === 'shop') return '/shop';
        if (php === 'about.php' || t === 'about') return '/about';
        if (php === 'contact.php' || t === 'contact') return '/contact';
        if (php === 'room_main.php' || t === 'room_main') return '/room_main';

        if (t.startsWith('/')) return t;

        if (t.startsWith('room:')) {
            const room = t.slice('room:'.length).trim();
            return room ? `/room_main?room_id=${encodeURIComponent(room)}` : '/room_main';
        }

        // Common mapping conventions
        if (t.startsWith('item:')) return '/shop';
        if (t.startsWith('category:')) return '/shop';

        // Default: treat target as a room_id value
        return `/room_main?room_id=${encodeURIComponent(t)}`;
    };

    return (
        <div
            ref={containerRef}
            id="landingPage-react"
            className="fixed inset-0 w-full h-full overflow-hidden bg-black z-base transition-opacity duration-700"
        >
            <h1 className="sr-only">Whimsical Frog</h1>
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
                    const mappingType = String(dest.mapping_type || '').toLowerCase();
                    const isShortcutType = mappingType === 'content';

                    if (!coord) return null;

                    const styles = getScaledStyles(coord);
                    const imgUrl = dest.image.startsWith('/') ? dest.image : `/${dest.image}`;
                    const imgWebp = imgUrl.replace(/\.png$/, '.webp');
                    const hasPanelColor = !!iconPanelColor && iconPanelColor !== 'transparent';

                    return (
                        <a
                            key={idx}
                            href={resolveHref(dest.target)}
                            className={`room-item-icon absolute group transition-opacity duration-300 overflow-visible ${isShortcutType ? 'room-item-shortcut' : ''} ${!isLoading && coordinates.length > 0 ? 'opacity-100' : 'opacity-0'}`}
                            data-mapping-type={mappingType || undefined}
                            aria-label={dest.label || 'Explore'}
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
                                    alt={dest.label || 'Whimsical Frog'}
                                    className="w-full h-full room-item-icon-img room-item-shortcut-img"
                                    style={{
                                        objectFit: 'contain',
                                        willChange: 'filter, transform'
                                    }}
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
