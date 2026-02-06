import React, { useState, useEffect, useCallback } from 'react';
import { ApiClient } from '../../../core/ApiClient.js';

interface BusinessInfo {
    business_address?: string;
    business_address2?: string;
    business_city?: string;
    business_state?: string;
    business_postal?: string;
}

interface DistanceResult {
    miles: number | null;
    cached?: boolean;
    estimated?: boolean;
}

interface AddressDiagnosticsProps {
    onClose?: () => void;
}

export function AddressDiagnostics({ onClose }: AddressDiagnosticsProps) {
    const [businessInfo, setBusinessInfo] = useState<BusinessInfo | null>(null);
    const [loading, setLoading] = useState(true);
    const [computing, setComputing] = useState(false);
    const [result, setResult] = useState<DistanceResult | null>(null);
    const [debugData, setDebugData] = useState<Record<string, unknown> | null>(null);
    const [error, setError] = useState<string | null>(null);

    // Target address fields
    const [toAddress, setToAddress] = useState('');
    const [toCity, setToCity] = useState('');
    const [toState, setToState] = useState('');
    const [toZip, setToZip] = useState('');

    const fetchBusinessInfo = useCallback(async () => {
        setLoading(true);
        try {
            const json = await ApiClient.get<{ success?: boolean; data?: BusinessInfo } & BusinessInfo>('/api/business_settings.php', { action: 'get_business_info' });
            const data = json.data || json || {};
            setBusinessInfo(data);
        } catch (err) {
            setError('Failed to load business info');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchBusinessInfo();
    }, [fetchBusinessInfo]);

    const useSampleAddress = () => {
        setToAddress('91 Singletree Ln');
        setToCity('Dawsonville');
        setToState('GA');
        setToZip('30534');
    };

    const computeDistance = async () => {
        if (!businessInfo) {
            setError('Business info not loaded');
            return;
        }

        setComputing(true);
        setResult(null);
        setDebugData(null);
        setError(null);

        try {
            const json = await ApiClient.post<{ success?: boolean; data?: { miles?: number; cached?: boolean; estimated?: boolean } } & { miles?: number; cached?: boolean; estimated?: boolean }>('/api/distance.php', {
                from: {
                    address: businessInfo.business_address || '',
                    city: businessInfo.business_city || '',
                    state: businessInfo.business_state || '',
                    zip: businessInfo.business_postal || '',
                },
                to: {
                    address: toAddress,
                    city: toCity,
                    state: toState,
                    zip: toZip,
                },
                debug: true,
            });
            const data = json.data || json;
            setResult({
                miles: data.miles ?? null,
                cached: !!data.cached,
                estimated: !!data.estimated,
            });
            setDebugData(data);
        } catch (err) {
            setError('Error computing distance');
        } finally {
            setComputing(false);
        }
    };

    const formatBusinessAddress = () => {
        if (!businessInfo) return '';
        const lines = [];
        if (businessInfo.business_address) lines.push(businessInfo.business_address);
        if (businessInfo.business_address2) lines.push(businessInfo.business_address2);

        let cityLine = '';
        if (businessInfo.business_city) cityLine += businessInfo.business_city;
        if (businessInfo.business_state) cityLine += (cityLine ? ', ' : '') + businessInfo.business_state;
        if (businessInfo.business_postal) cityLine += (cityLine ? ' ' : '') + businessInfo.business_postal;
        if (cityLine) lines.push(cityLine);

        return lines.join('\n');
    };

    return (
        <div
            className="admin-modal-overlay over-header show topmost"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose?.();
            }}
        >
            <div
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1200px] max-w-[95vw] h-auto max-h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">🏠</span> Address Check
                    </h2>
                    <div className="flex-1" />
                    <button
                        onClick={onClose}
                        className="admin-action-btn btn-icon--close"
                        data-help-id="common-close"
                        type="button"
                    />
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto p-6 bg-white min-h-[400px]">
                    {error && (
                        <div className="bg-red-50 border border-red-200 text-red-700 p-3 rounded mb-4">
                            {error}
                        </div>
                    )}

                    {loading ? (
                        <div className="flex items-center justify-center py-12">
                            <span className="text-gray-500">Loading business info...</span>
                        </div>
                    ) : (
                        <>
                            {/* Business Address */}
                            <div className="admin-card mb-4">
                                <h2 className="admin-card-title mb-2">Canonical Business Address</h2>
                                <pre className="font-mono text-sm bg-gray-50 p-3 rounded border border-gray-200">
                                    {formatBusinessAddress() || 'Not configured'}
                                </pre>
                                <p className="text-sm text-gray-500 mt-2">
                                    Sourced from business_info settings.
                                </p>
                            </div>

                            {/* Distance Calculator */}
                            <div className="admin-card">
                                <h2 className="admin-card-title mb-2">Compute Miles To Target</h2>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                                    <div>
                                        <label className="block font-semibold text-gray-700 mb-1">Address Line 1</label>
                                        <input
                                            type="text"
                                            className="form-input w-full"
                                            value={toAddress}
                                            onChange={(e) => setToAddress(e.target.value)}
                                            placeholder="91 Singletree Ln"
                                        />
                                    </div>
                                    <div>
                                        <label className="block font-semibold text-gray-700 mb-1">City</label>
                                        <input
                                            type="text"
                                            className="form-input w-full"
                                            value={toCity}
                                            onChange={(e) => setToCity(e.target.value)}
                                            placeholder="Dawsonville"
                                        />
                                    </div>
                                    <div>
                                        <label className="block font-semibold text-gray-700 mb-1">State</label>
                                        <input
                                            type="text"
                                            className="form-input w-full"
                                            value={toState}
                                            onChange={(e) => setToState(e.target.value)}
                                            placeholder="GA"
                                        />
                                    </div>
                                    <div>
                                        <label className="block font-semibold text-gray-700 mb-1">ZIP</label>
                                        <input
                                            type="text"
                                            className="form-input w-full"
                                            value={toZip}
                                            onChange={(e) => setToZip(e.target.value)}
                                            placeholder="30534"
                                        />
                                    </div>
                                </div>

                                <div className="flex gap-2 mb-4">
                                    <button
                                        type="button"
                                        onClick={computeDistance}
                                        disabled={computing}
                                        className="btn-text-primary disabled:opacity-50"
                                    >
                                        {computing ? 'Computing...' : 'Compute Miles'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={useSampleAddress}
                                        className="btn-text-secondary"
                                    >
                                        Use Sample Address
                                    </button>
                                </div>

                                {result && (
                                    <div className="bg-gray-50 p-3 rounded border border-gray-200 mb-4">
                                        <strong>Result:</strong>{' '}
                                        {result.miles === null ? (
                                            'miles = null (ineligible)'
                                        ) : (
                                            <>
                                                {result.miles.toFixed(2)} miles
                                                {(result.cached || result.estimated) && ' [as the crow flies]'}
                                            </>
                                        )}
                                    </div>
                                )}

                                {debugData && (
                                    <details className="mt-2 text-xs">
                                        <summary className="cursor-pointer text-gray-600">Debug</summary>
                                        <pre className="bg-gray-50 p-3 rounded border border-gray-200 mt-2 overflow-auto max-h-64">
                                            {JSON.stringify(debugData, null, 2)}
                                        </pre>
                                    </details>
                                )}
                            </div>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}

export default AddressDiagnostics;
