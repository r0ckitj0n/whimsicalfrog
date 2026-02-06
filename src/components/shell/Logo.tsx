import React from 'react';

interface LogoProps {
    siteName: string;
    siteTagline?: string;
    logoImage?: string;
    onClick?: (e: React.MouseEvent) => void;
}

export const Logo: React.FC<LogoProps> = ({ siteName, siteTagline, logoImage, onClick }) => {
    const handleClick = (e: React.MouseEvent) => {
        if (onClick) {
            e.preventDefault();
            onClick(e);
        }
    };

    return (
        <a href="/" className="logo-link" aria-label={`${siteName} - Home`} onClick={handleClick}>
            {logoImage && (
                <picture>
                    <source srcSet={logoImage.replace(/\.(png|jpe?g)$/i, '.webp')} type="image/webp" />
                    <img
                        src={logoImage}
                        alt={siteName}
                        className="header-logo"
                        loading="lazy"
                    />
                </picture>
            )}

            <div className="logo-text-container">
                <div className="logo-text font-title-primary italic">{siteName}</div>
                {siteTagline && <div className="logo-tagline font-title-secondary opacity-90">{siteTagline}</div>}
            </div>
        </a>
    );
};
