import React, { useEffect, useState, useRef } from 'react';
import { useRoomCoordinates } from '../hooks/useRoomCoordinates.js';
import { useLocation, useNavigate } from 'react-router-dom';
import { ApiClient } from '../core/ApiClient.js';
import { resolveBackgroundAssetUrl } from '../utils/background-url.js';
import { showShopBootOverlay } from '../core/shop-boot-overlay.js';
import { hrefNeedsShopLoader, shopNavigationHref } from '../utils/pageRoute.js';
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

    // Seed doors + wallpaper from router-injected HTML boot so the room is interactive
    // as soon as React mounts (no waiting on door_sign_destinations / get_background).
    const [destinations, setDestinations] = useState<IDoorDestination[]>(() => {
        if (typeof window === 'undefined') return [];
        const bootDest = window.__WF_LANDING_BOOT?.destinations;
        return Array.isArray(bootDest) ? (bootDest as IDoorDestination[]) : [];
    });
    const [bgUrl, setBgUrl] = useState(() => {
        if (typeof window !== 'undefined') {
            const bootBg = window.__WF_LANDING_BOOT?.bg || window.__WF_LANDING_BOOT_BG;
            if (typeof bootBg === 'string' && bootBg.trim() !== '') {
                return bootBg.trim();
            }
        }
        return '/images/backgrounds/realistic/realistic-roomA-frogs.webp';
    });
    const [hoveredSignIdx, setHoveredSignIdx] = useState<number | null>(null);
    const {
        coordinates,
        isLoading,
        setContainerSize,
        getScaledStyles,
        roomSettings
    } = useRoomCoordinates('A');

    const doorsCanReveal = destinations.length > 0 && coordinates.length > 0;

    // Get icon panel color from database settings
    const iconPanelColor = roomSettings?.icon_panel_color || 'transparent';
    const iconVerticalAlignment = roomSettings?.icon_vertical_alignment || 'middle';
    const isMiddleAligned = iconVerticalAlignment === 'middle';
    const objectPosition = iconVerticalAlignment === 'top'
        ? 'center top'
        : iconVerticalAlignment === 'bottom'
            ? 'center bottom'
            : 'center center';
    const flexAlignment = iconVerticalAlignment === 'top'
        ? 'flex-start'
        : iconVerticalAlignment === 'bottom'
            ? 'flex-end'
            : 'center';

    useEffect(() => {
        if (!isVisible) return;
        if (typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches) {
            showShopBootOverlay();
            navigate('/shop', { replace: true });
            return;
        }

        // Keep the HTML cabin wallpaper until the React background image is decoded,
        // so we never flash black (or show doors on an empty void) during handoff.
        let cancelled = false;
        const clearLandingBoot = () => {
            if (cancelled) return;
            document.documentElement.classList.remove('wf-landing-boot');
        };
        if (bgUrl) {
            const img = new Image();
            img.decoding = 'async';
            img.onload = () => clearLandingBoot();
            img.onerror = () => clearLandingBoot();
            img.src = bgUrl;
            if (img.complete) {
                clearLandingBoot();
            } else {
                // Safety: never leave the boot class forever if decode stalls.
                window.setTimeout(clearLandingBoot, 2500);
            }
        } else {
            requestAnimationFrame(clearLandingBoot);
        }

        const hasBootDestinations = destinations.length > 0;
        const hasBootBg = Boolean(bgUrl);

        const softRevalidate = async () => {
            const tasks: Array<Promise<void>> = [];

            tasks.push((async () => {
                try {
                    const destRes = await ApiClient.get<{ destinations: IDoorDestination[] }>(
                        '/api/area_mappings.php',
                        { action: 'door_sign_destinations', room: 'A' }
                    );
                    if (destRes?.destinations) {
                        setDestinations(destRes.destinations);
                    }
                } catch (err) {
                    console.error('[LandingPage] Door sign load/revalidate failed', err);
                }
            })());

            // Skip background XHR when HTML boot already provided the live wallpaper.
            if (!hasBootBg) {
                tasks.push((async () => {
                    try {
                        const bgRes = await ApiClient.get<{ background: { webp_filename?: string; png_filename?: string; image_filename?: string } }>(
                            '/api/get_background.php',
                            { room: 'A' }
                        );
                        const fetchedBg = bgRes?.background?.webp_filename
                            || bgRes?.background?.png_filename
                            || bgRes?.background?.image_filename;
                        if (fetchedBg) {
                            setBgUrl(resolveBackgroundAssetUrl(fetchedBg));
                        }
                    } catch (err) {
                        console.error('[LandingPage] Failed to load background', err);
                    }
                })());
            }

            await Promise.allSettled(tasks);
        };

        if (hasBootDestinations) {
            const ric = (window as Window & {
                requestIdleCallback?: (cb: () => void, opts?: { timeout: number }) => number;
            }).requestIdleCallback;
            if (typeof ric === 'function') {
                ric(() => { void softRevalidate(); }, { timeout: 2500 });
            } else {
                window.setTimeout(() => { void softRevalidate(); }, 1200);
            }
        } else {
            void softRevalidate();
        }

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
            cancelled = true;
            window.removeEventListener('resize', handleResize);
            document.body.classList.remove('mode-fullscreen');
        };
        // Intentionally mount-once for this visibility cycle: soft-revalidate reads initial boot
        // values and must not re-fire when destinations/bgUrl update from that revalidate.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isVisible, setContainerSize, navigate]);

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

    const handleDestinationClick = (event: React.MouseEvent<HTMLAnchorElement>, href: string) => {
        if (!hrefNeedsShopLoader(href)) return;
        event.preventDefault();
        showShopBootOverlay();
        navigate(shopNavigationHref(href));
    };

    return (
        <div
            ref={containerRef}
            id="landingPage-react"
            className="fixed inset-0 w-full h-full overflow-hidden bg-black z-base transition-opacity duration-700"
        >
            <h1 className="sr-only">Whimsical Frog</h1>
            <section className="sr-only" aria-label="Homepage highlights">
                <h2>Custom gifts and handmade decor</h2>
                <h3>Custom tumblers, personalized shirts, and resin decor</h3>
            </section>
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
                            onClick={(event) => handleDestinationClick(event, resolveHref(dest.target))}
                            className={`room-item-icon absolute group transition-opacity duration-300 overflow-visible ${isShortcutType ? 'room-item-shortcut' : ''} ${(doorsCanReveal || (!isLoading && coordinates.length > 0)) ? 'opacity-100' : 'opacity-0'}`}
                            data-mapping-type={mappingType || undefined}
                            aria-label={dest.label || 'Explore'}
                            style={{
                                ...styles,
                                height: 'var(--door-height)',
                                backgroundColor: iconPanelColor || 'transparent',
                                borderRadius: hasPanelColor ? '10px' : undefined,
                                padding: hasPanelColor ? '6px' : '0',
                                boxSizing: 'border-box',
                                overflow: (isMiddleAligned && !isShortcutType) ? 'hidden' : 'visible',
                                display: 'flex',
                                alignItems: flexAlignment,
                                justifyContent: 'center',
                                zIndex: hoveredSignIdx === idx ? 'calc(var(--wf-z-sticky) + 100)' : undefined
                            }}
                            onMouseEnter={() => setHoveredSignIdx(idx)}
                            onMouseLeave={() => setHoveredSignIdx(null)}
                            onFocus={() => setHoveredSignIdx(idx)}
                            onBlur={() => setHoveredSignIdx(null)}
                        >
                            <picture
                                className="block w-full"
                                style={{ height: isMiddleAligned ? '100%' : 'auto' }}
                            >
                                <source srcSet={imgWebp} type="image/webp" />
                                <img
                                    src={imgUrl}
                                    alt={dest.label || 'Whimsical Frog'}
                                    className="w-full room-item-icon-img room-item-shortcut-img"
                                    style={{
                                        height: isMiddleAligned ? '100%' : 'auto',
                                        objectFit: isMiddleAligned ? 'contain' : undefined,
                                        objectPosition,
                                        willChange: 'filter, transform'
                                    }}
                                    loading="eager"
                                    fetchPriority="high"
                                    decoding="async"
                                />
                            </picture>
                        </a>
                    );
                })}
            </div>
            <footer className="sr-only">
                <nav aria-label="Support">
                    <a href="/policy">Policy</a>
                    <a href="/privacy">Privacy</a>
                    <a href="/contact">Contact</a>
                </nav>
            </footer>

        </div>
    );
};
