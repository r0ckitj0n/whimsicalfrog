import React, { useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { RoomModalOverlay } from './room/RoomModalOverlay.js';
import { useRoomModalEffects } from '../../hooks/modals/useRoomModalEffects.js';
import { IRoomMetadata as RoomMetadata, IRoomBackground as RoomBackground } from '../../types/index.js';

interface RoomModalProps {
    isOpen: boolean;
    onClose: () => void;
    room_number: string | null;
    content: string;
    metadata: RoomMetadata;
    background: RoomBackground | null;
    panelColor?: string;
    renderContext?: 'modal' | 'fullscreen';
    targetAspectRatio?: number | string | null;
    isLoading?: boolean;
    onOpenItem?: (sku: string, data?: ItemPopupState) => void;
    is_bare?: boolean;
}

interface ItemPopupState {
    visible: boolean;
    sku: string;
    name: string;
    price: string | number;
    image: string;
    stock: number;
    x: number;
    y: number;
}

export const RoomModal: React.FC<RoomModalProps> = ({
    isOpen,
    onClose,
    room_number,
    content,
    metadata,
    background,
    panelColor,
    renderContext = 'modal',
    targetAspectRatio,
    isLoading,
    onOpenItem,
    is_bare = false
}) => {
    const bodyRef = useRef<HTMLDivElement>(null);
    const [popup, setPopup] = useState<ItemPopupState | null>(null);

    useRoomModalEffects({
        isOpen,
        content,
        bodyRef,
        popup,
        setPopup,
        onClose,
        onOpenItem
    });

    if (!isOpen) return null;

    const getBgUrl = (bg: RoomBackground | null) => {
        if (!bg) return '';
        const file = String(bg.webp_filename || bg.image_filename || '').trim();
        if (!file) return '';
        if (file.startsWith('https') || file.startsWith('http') || file.startsWith('/')) return file;
        // Handle stored values like `images/backgrounds/foo.webp` (missing leading slash).
        if (file.startsWith('images/')) return `/${file}`;
        if (file.startsWith('backgrounds/')) return `/images/${file}`;
        return `/images/backgrounds/${file}`;
    };

    const bgUrl = getBgUrl(background);
    const bgStyle = bgUrl ? { backgroundImage: `url(${bgUrl})`, backgroundSize: '100% 100%', backgroundPosition: 'center', backgroundRepeat: 'no-repeat' } : {};

    const tar = typeof targetAspectRatio === 'number'
        ? targetAspectRatio
        : (parseFloat(String(targetAspectRatio)) || (renderContext === 'fullscreen' ? 1280 / 896 : 1024 / 768));

    const isFullScale = room_number === 'A' || room_number === '0' || tar > 1.4;
    const dims = isFullScale ? { w: 1280, h: 896 } : { w: 1024, h: 768 };
    const ratio = dims.w / dims.h;

    const modalContent = (
        <RoomModalOverlay
            isOpen={isOpen}
            onClose={onClose}
            room_number={room_number}
            content={content}
            metadata={metadata}
            bgStyle={bgStyle}
            panelColor={panelColor}
            renderContext={renderContext}
            targetAspectRatio={targetAspectRatio}
            isLoading={isLoading}
            onOpenItem={onOpenItem}
            is_bare={is_bare}
            bodyRef={bodyRef}
            ratio={ratio}
            popup={popup}
            setPopup={setPopup}
        />
    );

    return createPortal(modalContent, document.body);
};

export default RoomModal;
