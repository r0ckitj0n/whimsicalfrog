import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IIntentWeights, IBudgetRanges, IIntentHeuristics, IHeuristicsResponse } from '../../types/ai.js';

// Re-export for backward compatibility
export type { IIntentWeights, IBudgetRanges, IIntentHeuristics, IHeuristicsResponse } from '../../types/ai.js';



export const useIntentHeuristics = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [config, setConfig] = useState<IIntentHeuristics | null>(null);
    const [error, setError] = useState<string | null>(null);

    const fetchConfig = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IHeuristicsResponse>('/api/business_settings.php', {
                action: 'get_setting',
                key: 'cart_intent_heuristics'
            });

            // ApiClient returns the 'data' property if success:true and 'data' exists
            // We need to check if 'res' itself has the 'setting' property or if it's nested
            interface IResponseShape {
                setting?: { setting_value?: string | IIntentHeuristics };
                setting_value?: string | IIntentHeuristics;
            }
            const data = res as IResponseShape;
            let val: string | IIntentHeuristics | undefined = data?.setting?.setting_value || data?.setting_value;

            if (val) {
                if (typeof val === 'string') {
                    try {
                        val = JSON.parse(val);
                    } catch (_) {
                        val = getDefaultHeuristics();
                    }
                }

                if (val && typeof val === 'object' && val.weights) {
                    setConfig(val as IIntentHeuristics);
                    return;
                }
            }

            setConfig(getDefaultHeuristics());
        } catch (err) {
            logger.error('[IntentHeuristics] fetch failed', err);
            setConfig(getDefaultHeuristics());
            setError('Unable to load heuristics configuration. Using defaults.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveConfig = async (newConfig: IIntentHeuristics) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<any>('/api/business_settings.php?action=upsert_settings', {
                category: 'ecommerce',
                settings: { cart_intent_heuristics: newConfig }
            });
            // JsonResponseParser automatically unwraps 'data' and throws on success:false
            if (res) {
                setConfig(newConfig);
                return true;
            }
        } catch (err) {
            logger.error('[IntentHeuristics] save failed', err);
        } finally {
            setIsLoading(false);
        }
        return false;
    };

    useEffect(() => {
        fetchConfig();
    }, [fetchConfig]);

    return {
        config,
        isLoading,
        error,
        fetchConfig,
        saveConfig,
        setConfig
    };
};

export const getDefaultHeuristics = (): IIntentHeuristics => ({
    weights: {
        popularity_cap: 3.0,
        kw_positive: 2.5,
        cat_positive: 3.5,
        seasonal: 2.0,
        same_category: 2.0,
        upgrade_price_ratio_threshold: 1.25,
        upgrade_price_boost: 3.0,
        upgrade_label_boost: 2.5,
        replacement_label_boost: 3.0,
        gift_set_boost: 1.0,
        gift_price_boost: 1.5,
        teacher_price_ceiling: 30.0,
        teacher_price_boost: 1.5,
        budget_proximity_mult: 2.0,
        neg_keyword_penalty: 2.0,
        intent_badge_threshold: 2.0
    },
    budget_ranges: {
        low: [8.0, 20.0],
        mid: [15.0, 40.0],
        high: [35.0, 120.0]
    },
    keywords: {
        positive: {
            gift: ["gift", "set", "bundle", "present", "pack", "box"],
            replacement: ["refill", "replacement", "spare", "recharge", "insert"],
            upgrade: ["upgrade", "pro", "deluxe", "premium", "xl", "plus", "pro+", "ultimate"],
            "diy-project": ["diy", "kit", "project", "starter", "make your own", "how to"],
            "home-decor": ["decor", "wall", "frame", "sign", "plaque", "art", "canvas"]
        },
        negative: {
            gift: ["refill", "replacement"],
            replacement: ["gift", "decor"],
            upgrade: ["refill"]
        },
        categories: {
            gift: ["gifts", "gift sets", "bundles"],
            replacement: ["supplies", "refills", "consumables"],
            "diy-project": ["diy", "kits", "craft kits", "projects"],
            "home-decor": ["home decor", "decor", "wall art", "signs"]
        }
    },
    seasonal_months: {
        "1": ["valentine"], "2": ["valentine"], "12": ["christmas"]
    }
});
