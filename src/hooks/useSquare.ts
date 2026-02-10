import { useState, useCallback, useEffect, useRef } from 'react';
import logger from '../core/logger.js';
import { ENVIRONMENT, Environment } from '../core/constants.js';
import { ISquareCard, ISquareWindow } from '../types/square.js';

// Re-export for backward compatibility  
export type { ISquareCard } from '../types/square.js';

export const useSquare = (applicationId: string | null | undefined, locationId: string | null | undefined, environment: Environment = ENVIRONMENT.SANDBOX) => {
    const [isLoaded, setIsLoaded] = useState(false);
    const cardRef = useRef<ISquareCard | null>(null);
    const [card, setCard] = useState<ISquareCard | null>(null);
    const [error, setError] = useState<string | null>(null);

    const loadSDK = useCallback(async () => {
        logger.info('[useSquare] loadSDK called', { isLoaded });
        if (isLoaded) return true;

        const scriptSrc = environment === ENVIRONMENT.PRODUCTION
            ? 'https://web.squarecdn.com/v1/square.js'
            : 'https://sandbox.web.squarecdn.com/v1/square.js';

        return new Promise((resolve, reject) => {
            const existing = document.getElementById('sq-web-payments-sdk');
            if (existing) {
                logger.info('[useSquare] SDK script already exists');
                setIsLoaded(true);
                resolve(true);
                return;
            }

            const s = document.createElement('script');
            s.id = 'sq-web-payments-sdk';
            s.src = scriptSrc;
            s.onload = () => {
                logger.info('[useSquare] SDK script loaded');
                setIsLoaded(true);
                resolve(true);
            };
            s.onerror = () => {
                logger.error('[useSquare] SDK script load failed');
                setError('Failed to load Square SDK');
                reject(new Error('Failed to load Square SDK'));
            };
            document.head.appendChild(s);
        });
    }, [environment]);

    const initializeCard = useCallback(async (containerId: string, options?: { postalCode?: boolean }) => {
        const win = window as unknown as ISquareWindow;
        logger.info('[useSquare] initializeCard called', {
            isLoaded,
            hasSquare: !!win.Square,
            applicationId,
            locationId,
            containerId
        });

        if (!applicationId || !locationId || !win.Square) {
            logger.warn('[useSquare] Missing requirements for card init', { applicationId, locationId, hasSquare: !!win.Square });
            return;
        }

        try {
            // Clean up existing card if it exists locally to avoid initialization overlaps
            if (cardRef.current) {
                logger.info('[useSquare] Cleaning up previous card instance before re-init');
                await cardRef.current.destroy();
                cardRef.current = null;
            }

            const payments = win.Square.payments(applicationId, locationId);
            const cardInstance = await payments.card(options);

            // Clear the container (removes 'Loading...' placeholder)
            const container = document.querySelector(containerId);
            if (container) {
                container.innerHTML = '';
            }

            await cardInstance.attach(containerId);

            logger.info('[useSquare] Card instance attached successfully');
            cardRef.current = cardInstance;
            setCard(cardInstance);
            return cardInstance;
        } catch (err) {
            logger.error('[useSquare] Card init failed', err);
            setError('Card input failed to initialize');
        }
    }, [applicationId, locationId]);

    const tokenize = useCallback(async (options?: {
        billingContact?: {
            addressLines?: string[];
            city?: string;
            state?: string;
            postalCode?: string;
            countryCode?: string;
        };
    }) => {
        if (!cardRef.current) return null;
        try {
            const result = await cardRef.current.tokenize(options);
            if (result.status === 'OK') {
                return result.token;
            } else {
                throw new Error(result.errors?.[0]?.message || 'Tokenization failed');
            }
        } catch (err) {
            const errorMsg = err instanceof Error ? err.message : 'Unknown tokenization error';
            setError(errorMsg);
            throw err;
        }
    }, []); // Stable identity

    const destroy = useCallback(async () => {
        if (cardRef.current) {
            logger.info('[useSquare] Destroying card instance');
            await cardRef.current.destroy();
            cardRef.current = null;
            setCard(null);
        }
    }, []); // Stable identity

    return {
        isLoaded,
        card,
        error,
        loadSDK,
        initializeCard,
        tokenize,
        destroy
    };
};
