import React from 'react';

interface LogoProps {
    siteName: string;
    siteTagline?: string;
    logoImage?: string;
    imageHref?: string;
    titleHref?: string;
    isTitleMenuButton?: boolean;
    onTitleClick?: (e: React.MouseEvent) => void;
}

export const Logo: React.FC<LogoProps> = ({
    siteName,
    siteTagline,
    logoImage,
    imageHref = '/',
    titleHref = '/room_main',
    isTitleMenuButton = false,
    onTitleClick
}) => {
    const handleTitleClick = (e: React.MouseEvent) => {
        if (onTitleClick) {
            e.preventDefault();
            onTitleClick(e);
        }
    };

    const textContent = (
        <div className="logo-text-container">
            <div className="logo-text font-title-primary italic">{siteName}</div>
            {siteTagline && <div className="logo-tagline font-title-secondary opacity-90">{siteTagline}</div>}
        </div>
    );

    return (
        <div className="logo-link">
            {logoImage && (
                <a href={imageHref} className="logo-image-link" aria-label={`${siteName} - Landing Page`}>
                    <picture>
                        <source srcSet={logoImage.replace(/\.(png|jpe?g)$/i, '.webp')} type="image/webp" />
                        <img
                            src={logoImage}
                            alt={siteName}
                            className="header-logo"
                            loading="lazy"
                        />
                    </picture>
                </a>
            )}

            {isTitleMenuButton ? (
                <button
                    type="button"
                    className="logo-text-trigger"
                    aria-label={`Open ${siteName} menu`}
                    onClick={handleTitleClick}
                >
                    {textContent}
                </button>
            ) : (
                <a href={titleHref} className="logo-text-trigger" aria-label={`${siteName} - Main Room`} onClick={handleTitleClick}>
                    {textContent}
                </a>
            )}
        </div>
    );
};
