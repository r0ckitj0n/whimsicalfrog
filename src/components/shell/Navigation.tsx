import React from 'react';
import { Link, useLocation } from 'react-router-dom';

interface NavItem {
    label: string;
    url: string;
    isImage?: boolean;
}

const NAVIGATION_ITEMS: NavItem[] = [
    { label: 'Shop', url: '/shop' },
    { label: 'About', url: '/about' },
    { label: 'Contact', url: '/contact' },
];

export const Navigation: React.FC = () => {
    const location = useLocation();
    const currentPath = location.pathname;

    return (
        <nav className="nav-links" role="navigation" aria-label="Main navigation">
            {NAVIGATION_ITEMS.map((item) => {
                const isActive = currentPath === item.url || (item.url !== '/' && currentPath.startsWith(item.url));

                return (
                    <Link
                        key={item.url}
                        to={item.url}
                        className={`font-title-primary text-lg text-[var(--brand-primary)] transition-opacity hover:opacity-80 ${isActive ? 'font-bold' : ''} ${item.isImage ? 'nav-image-link' : ''}`}
                        aria-current={isActive ? 'page' : undefined}
                    >
                        {item.label}
                    </Link>
                );
            })}
        </nav>
    );
};
