import React from 'react';
import { IMapArea } from '../../../../../types/room.js';

interface ISignDestination {
    area_selector: string;
    label?: string;
    target?: string;
    image: string;
}

interface SignLayerProps {
    areas: IMapArea[];
    signDestinations: ISignDestination[];
    dims: { w: number, h: number };
    iconPanelColor?: string;
    iconVerticalAlignment?: 'top' | 'middle' | 'bottom';
}

export const SignLayer: React.FC<SignLayerProps> = ({
    areas,
    signDestinations,
    dims,
    iconPanelColor = 'transparent',
    iconVerticalAlignment = 'middle'
}) => {
    const isMiddleAligned = iconVerticalAlignment === 'middle';
    const objectPosition = iconVerticalAlignment === 'top'
        ? 'center top'
        : iconVerticalAlignment === 'bottom'
            ? 'center bottom'
            : 'center center';

    const getSignForArea = (area: IMapArea): ISignDestination | undefined => {
        const areaSelector = area.selector.startsWith('.') ? area.selector : `.${area.selector}`;
        return signDestinations.find(s => {
            const destSelector = s.area_selector.startsWith('.') ? s.area_selector : `.${s.area_selector}`;
            return destSelector.toLowerCase() === areaSelector.toLowerCase();
        });
    };

    return (
        <>
            {areas.map(area => {
                const sign = getSignForArea(area);
                if (!sign) return null;

                const imgUrl = sign.image.startsWith('/') ? sign.image : `/${sign.image}`;
                const imgWebp = imgUrl.replace(/\.png$/, '.webp');

                const leftPercent = (area.left / dims.w) * 100;
                const topPercent = (area.top / dims.h) * 100;
                const widthPercent = (area.width / dims.w) * 100;
                const heightPercent = (area.height / dims.h) * 100;

                return (
                    <div
                        key={`sign-${area.id}`}
                        className="absolute pointer-events-none"
                        style={{
                            left: `${leftPercent}%`,
                            top: `${topPercent}%`,
                            width: `${widthPercent}%`,
                            height: `${heightPercent}%`,
                            zIndex: 15,
                            backgroundColor: iconPanelColor,
                            borderRadius: '10px',
                            padding: iconPanelColor === 'transparent' ? 0 : '6px',
                            display: 'flex',
                            alignItems: isMiddleAligned ? 'center' : (iconVerticalAlignment === 'top' ? 'flex-start' : 'flex-end'),
                            justifyContent: 'center',
                            overflow: isMiddleAligned ? 'hidden' : 'visible'
                        }}
                    >
                        <picture
                            className="block w-full"
                            style={{ height: isMiddleAligned ? '100%' : 'auto' }}
                        >
                            <source srcSet={imgWebp} type="image/webp" />
                            <img
                                src={imgUrl}
                                alt=""
                                style={{
                                    width: '100%',
                                    height: isMiddleAligned ? '100%' : 'auto',
                                    objectFit: isMiddleAligned ? 'contain' : undefined,
                                    objectPosition,
                                    display: 'block'
                                }}
                            />
                        </picture>
                    </div>
                );
            })}
        </>
    );
};
