export interface IPostAuthRedirectPlan {
    openCart: boolean;
    redirectPath: string | null;
}

export const buildPostAuthRedirectPlan = (returnTo: string | null): IPostAuthRedirectPlan => {
    if (returnTo === 'cart') {
        return { openCart: true, redirectPath: null };
    }

    if (typeof returnTo === 'string' && returnTo.trim().startsWith('/')) {
        return { openCart: false, redirectPath: returnTo.trim() };
    }

    if (window.location.pathname.toLowerCase().includes('/login')) {
        return { openCart: false, redirectPath: '/room_main' };
    }

    return { openCart: false, redirectPath: null };
};
