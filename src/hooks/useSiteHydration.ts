import { useEffect, useState } from 'react';
import { useItemModal } from './useItemModal.js';
import { useAuthModal } from './useAuthModal.js';
import { useApp } from '../context/AppContext.js';
import { IShopData, IReceiptData, IAboutData, IContactData, ISiteSettings } from '../types/index.js';
import { ApiClient } from '../core/ApiClient.js';

/**
 * useSiteHydration Hook
 * Handles extracting initial data from the DOM and exposing global bridges.
 */
export const useSiteHydration = () => {
    const { open: openItemModal } = useItemModal();
    const { openLogin, openAccountSettings } = useAuthModal();

    const { receiptOrderId, setReceiptOrderId } = useApp();
    const [shop_data, setShopData] = useState<IShopData | null>(null);
    const [receipt_data, setReceiptData] = useState<IReceiptData | null>(null);
    const [about_data, setAboutData] = useState<IAboutData | null>(null);
    const [contact_data, setContactData] = useState<IContactData | null>(null);
    const [site_settings, setSiteSettings] = useState<ISiteSettings | null>(null);
    const [is_payment_modal_open, set_is_payment_modal_open] = useState(false);

    const loadFromDOM = () => {
        // Load global site settings
        const siteSettingsEl = document.getElementById('site-settings');
        if (siteSettingsEl) {
            try {
                setSiteSettings(JSON.parse(siteSettingsEl.textContent || '{}'));
            } catch (e) {
                console.error('[SiteHydration] Site settings parse failed', e);
            }
        }

        // Load data for specialized views if present in DOM
        const shopDataEl = document.getElementById('shop-data');
        if (shopDataEl) {
            try {
                const parsed = JSON.parse(shopDataEl.textContent || '{}');
                setShopData(parsed);
            } catch (e) {
                console.error('[SiteHydration] Shop data parse failed', e);
            }
        }

        const receiptDataEl = document.getElementById('receipt-data');
        if (receiptDataEl) {
            try {
                setReceiptData(JSON.parse(receiptDataEl.textContent || '{}'));
            } catch (e) {
                console.error('Receipt data parse failed', e);
            }
        }

        const aboutDataEl = document.getElementById('about-data');
        if (aboutDataEl) {
            try {
                setAboutData(JSON.parse(aboutDataEl.textContent || '{}'));
            } catch (e) {
                console.error('[SiteHydration] About data parse failed', e);
            }
        }

        const contactDataEl = document.getElementById('contact-data');
        if (contactDataEl) {
            try {
                setContactData(JSON.parse(contactDataEl.textContent || '{}'));
            } catch (e) {
                console.error('[SiteHydration] Contact data parse failed', e);
            }
        } else {
            // Fallback for legacy hidden button hydration
            const contactBtn = document.getElementById('wf-reveal-company-btn');
            if (contactBtn) {
                const data: IContactData = {
                    email: contactBtn.getAttribute('data-enc-email') ?? '',
                    phone: contactBtn.getAttribute('data-enc-phone') ?? '',
                    address: contactBtn.getAttribute('data-enc-address') ?? '',
                    owner: contactBtn.getAttribute('data-enc-owner') ?? '',
                    name: contactBtn.getAttribute('data-enc-name') ?? '',
                    site: contactBtn.getAttribute('data-enc-site') ?? '',
                    hours: contactBtn.getAttribute('data-enc-hours') ?? '',
                    page_title: contactBtn.getAttribute('data-page-title') ?? '',
                    page_intro: contactBtn.getAttribute('data-page-intro') ?? ''
                };
                setContactData(data);
            }
        }
    };

    useEffect(() => {
        // Synchronous page detection for immediate UI response
        const path = window.location.pathname.toLowerCase();
        const searchParams = new URLSearchParams(window.location.search);
        const section = searchParams.get('section') || '';
        const segments = path.split('/').filter(Boolean);
        const pageSlug = segments[0] || 'landing';

        let detectedPage = pageSlug;
        const isAdminPath = pageSlug === 'admin' || path.includes('/admin') || path.includes('admin_router.php');

        if (isAdminPath) {
            detectedPage = section ? `admin/${section}` : 'admin';
        } else if (!segments[0] && section) {
            detectedPage = `admin/${section}`;
        } else if (searchParams.get('room_id') === 'X') {
            detectedPage = 'admin/settings';
        }

        const is_bare = searchParams.get('bare') === '1';
        const hide_items = searchParams.get('hide_items') === '1';

        if (hide_items) {
            document.body.classList.add('hide-map-items');
            // Inject nuclear style for immediate effect
            let style = document.getElementById('wf-hide-items-style');
            if (!style) {
                style = document.createElement('style');
                style.id = 'wf-hide-items-style';
                style.textContent = '.room-items-container, .room-item, .room-item-icon { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; }';
                document.head.appendChild(style);
            }
        } else {
            document.body.classList.remove('hide-map-items');
            const style = document.getElementById('wf-hide-items-style');
            if (style) style.remove();
        }

        if (!document.body.getAttribute('data-page')) {
            document.body.setAttribute('data-page', detectedPage);
        }

        // Initial DOM load for specialized views (receipts, etc.)
        loadFromDOM();

        // Detect order_id from URL (for redirects from checkout or direct links)
        const orderIdFromUrl = searchParams.get('order_id');
        if (orderIdFromUrl) {
            setReceiptOrderId(orderIdFromUrl);
        }

        const fetchBootstrap = async () => {
            try {
                // Pass current path to API for correct background resolution
                const currentPath = window.location.pathname;
                const data = await ApiClient.get<{
                    site_settings?: ISiteSettings;
                    shop_data?: IShopData;
                    about_data?: IAboutData;
                    contact_data?: IContactData;
                    background_url?: string;
                    branding?: { style?: string };
                    auth?: { isLoggedIn?: boolean; user_id?: string | number; userData?: { role?: string } };
                }>('/api/bootstrap.php', { path: currentPath });

                if (data.site_settings) setSiteSettings(data.site_settings);
                if (data.shop_data) setShopData(data.shop_data);
                if (data.about_data) setAboutData(data.about_data);
                if (data.contact_data) setContactData(data.contact_data);
                if (data.background_url && !document.body.getAttribute('data-bg-url') && !is_bare) {
                    document.body.setAttribute('data-bg-url', data.background_url);
                    document.body.setAttribute('data-bg-applied', '1');
                    document.body.style.setProperty('--wf-body-bg', `url("${data.background_url}")`);
                    document.body.style.setProperty('--body-bg', `url("${data.background_url}")`);
                    document.body.style.backgroundImage = `url("${data.background_url}")`;
                    document.body.style.backgroundSize = 'cover';
                    document.body.style.backgroundPosition = 'center';
                    document.body.style.backgroundRepeat = 'no-repeat';
                    document.body.style.backgroundAttachment = 'fixed';
                }

                // Nuclear option for the mysterious vignette
                const vignette = document.getElementById('preact-border-shadow-host');
                if (vignette) vignette.style.display = 'none';
                if (data.branding?.style) {
                    // Inject branding style if not already present
                    if (!document.getElementById('wf-branding-vars')) {
                        const style = document.createElement('style');
                        style.id = 'wf-branding-vars';
                        style.textContent = data.branding.style.replace(/<\/?style[^>]*>/g, '');
                        document.head.appendChild(style);
                    }
                }
                if (data.auth) {
                    document.body.setAttribute('data-is-logged-in', data.auth.isLoggedIn ? 'true' : 'false');
                    if (data.auth.user_id) document.body.setAttribute('data-user-id', String(data.auth.user_id));

                    const role = String(data.auth.userData?.role || '').toLowerCase().trim();
                    const isAdmin = role === 'admin' || role === 'administrator' || role === 'superadmin' || role === 'devops';
                    document.body.setAttribute('data-is-admin', isAdmin ? 'true' : 'false');
                    if (isAdmin) document.body.classList.add('admin-view');
                }

                // Set page attribute based on path and section
                const path = window.location.pathname.toLowerCase();
                const searchParams = new URLSearchParams(window.location.search);
                const section = searchParams.get('section') || '';
                const segments = path.split('/').filter(Boolean);
                const pageSlug = segments[0] || 'landing';

                let finalPage = pageSlug;
                // If we are in admin path OR have a section param, we are likely in an admin view
                const isAdminPath = pageSlug === 'admin' || path.includes('/admin') || path.includes('admin_router.php');

                if (isAdminPath) {
                    if (section) {
                        finalPage = `admin/${section}`;
                    } else {
                        finalPage = 'admin';
                    }
                } else if (!segments[0] && section) {
                    // Root path with section (e.g. /?section=settings)
                    finalPage = `admin/${section}`;
                } else if (searchParams.get('room_id') === 'X') {
                    finalPage = 'admin/settings';
                }

                document.body.setAttribute('data-page', finalPage);
                document.body.setAttribute('data-path', path);

                if (finalPage === 'about' || finalPage === 'contact' || finalPage === 'room_main' || finalPage === 'admin/settings') {
                    document.body.classList.add('room-bg-main');
                }
                if (finalPage === 'login') {
                    // document.body.classList.add('wf-modal-open'); // Removed: Centralized in App.tsx
                }

                // Handle auth if needed (though AuthProvider might handle its own fetch)
                // For now, site_settings and shop_data are the main ones needed here.

                // Always try to load specialized data from DOM after bootstrap
                loadFromDOM();
            } catch (e) {
                console.error('[SiteHydration] API fetch failed, falling back to DOM', e);
                loadFromDOM();
                // Ensure we at least have empty settings to break the loading loop if fallback also fails
                setSiteSettings(prev => prev || ({} as ISiteSettings));
            }
        };

        fetchBootstrap();
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') return;

        // Expose modal functions to legacy code
        window.showGlobalItemModal = openItemModal;
        window.showDetailedModal = openItemModal;
        window.openLoginModal = openLogin;
        window.openAccountSettings = openAccountSettings;
        window.openPaymentModal = () => set_is_payment_modal_open(true);

        window.WF_PaymentModal = {
            open: () => set_is_payment_modal_open(true),
            close: () => set_is_payment_modal_open(false)
        };

        window.WF_ReceiptModal = {
            open: (order_id: string | number) => setReceiptOrderId(order_id),
            close: () => setReceiptOrderId(null)
        };

        return () => {
            // Optional: clean up globals on unmount if needed
        };
    }, [openItemModal, openLogin, openAccountSettings]);

    const is_bare = typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('bare') === '1';

    return {
        shop_data,
        receipt_data,
        about_data,
        contact_data,
        site_settings,
        receipt_order_id: receiptOrderId,
        setReceiptOrderId,
        is_payment_modal_open,
        set_is_payment_modal_open,
        is_bare
    };
};
