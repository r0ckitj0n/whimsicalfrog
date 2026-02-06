import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useDbQueryConsole } from '../../../hooks/admin/useDbQueryConsole.js';

interface DbQueryConsoleProps {
    onClose?: () => void;
    title?: string;
}

export const DbQueryConsole: React.FC<DbQueryConsoleProps> = ({ onClose, title }) => {
    const {
        isLoading,
        results,
        error,
        executeQuery,
        setResults
    } = useDbQueryConsole();

    const [sql, setSql] = useState('');
    const [env, setEnv] = useState('local');

    const presets = [
        { label: 'Latest Price Suggestions', sql: "SELECT id, sku, suggested_price, confidence, created_at FROM price_suggestions ORDER BY created_at DESC LIMIT 10" },
        { label: 'Latest Cost Suggestions', sql: "SELECT sku, suggested_cost, confidence, created_at FROM cost_suggestions ORDER BY created_at DESC LIMIT 10" },
        { label: 'Recent Items', sql: "SELECT sku, name, retail_price, stock_quantity, created_at FROM items ORDER BY created_at DESC LIMIT 20" },
        { label: 'Recent Orders', sql: "SELECT id, total_amount, status, created_at FROM orders ORDER BY created_at DESC LIMIT 10" },
        { label: 'Low Stock Items', sql: "SELECT sku, name, stock_quantity FROM items WHERE stock_quantity < 5 ORDER BY stock_quantity ASC" },
        { label: 'Recent Page Views', sql: "SELECT page_url, session_id, viewed_at FROM page_views ORDER BY viewed_at DESC LIMIT 20" },
        { label: 'Active Sessions', sql: "SELECT session_id, user_agent, last_activity FROM analytics_sessions ORDER BY last_activity DESC LIMIT 10" },
        { label: 'Top Viewed Items', sql: "SELECT item_sku, COUNT(*) as views FROM item_analytics GROUP BY item_sku ORDER BY views DESC LIMIT 10" },
        { label: 'Recent Error Logs', sql: "SELECT message, error_type, created_at FROM error_logs ORDER BY created_at DESC LIMIT 10" },
        { label: 'Admin Activity', sql: "SELECT admin_user_id, action_type, created_at FROM admin_activity_logs ORDER BY created_at DESC LIMIT 10" },
        { label: 'Business Settings', sql: "SELECT setting_key, setting_value FROM business_settings" },
        { label: 'Active Coupons', sql: "SELECT code, value, expires_at FROM coupons WHERE is_active = 1" },
        { label: 'Recent Subscribers', sql: "SELECT email, subscribe_date FROM email_subscribers ORDER BY subscribe_date DESC LIMIT 10" },
        { label: 'Social Accounts', sql: "SELECT platform, account_name FROM social_accounts" },
        { label: 'Table Counts', sql: "SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_rows DESC" },
        { label: 'Theme Words', sql: "SELECT base_word, category, created_at FROM theme_words ORDER BY created_at DESC LIMIT 20" },
        { label: 'Recent Tooltip Updates', sql: "SELECT element_id, content, created_at FROM help_tooltips ORDER BY created_at DESC LIMIT 10" },
        { label: 'SKU Rules', sql: "SELECT category_name, sku_prefix FROM sku_rules" },
        { label: 'Brand Voice Options', sql: "SELECT label, value FROM brand_voice_options" },
        { label: 'Recent Activity Logs', sql: "SELECT action_type, admin_user_id, created_at FROM admin_activity_logs ORDER BY created_at DESC LIMIT 10" }
    ];

    const handleExecute = (e?: React.FormEvent) => {
        e?.preventDefault();
        if (!sql.trim()) return;
        executeQuery(sql, env);
    };

    const handleClear = () => {
        setSql('');
        setResults(null);
    };

    const modalContent = (
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
                        <span className="text-2xl">⌨️</span> {title || 'DB Query Console'}
                    </h2>
                    <div className="flex-1" />
                    <button
                        onClick={onClose}
                        className="admin-action-btn btn-icon--close"
                        data-help-id="db-query-close"
                        type="button"
                    />
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto">
                    <div className="p-10 space-y-6">
                        <div className="bg-white border rounded-xl shadow-sm overflow-hidden">
                            <div className="px-4 py-3 bg-gray-50 border-b flex items-center justify-between">
                                <div className="flex items-center gap-2 font-bold text-gray-700">
                                    SQL Console (SELECT Only)
                                </div>
                                <div className="flex items-center gap-3">
                                    <select
                                        value={env}
                                        onChange={(e) => setEnv(e.target.value)}
                                        className="form-input text-xs py-1"
                                    >
                                        <option value="local">Local</option>
                                        <option value="live">Live</option>
                                    </select>
                                    <button
                                        onClick={handleClear}
                                        className="btn-text-secondary"
                                        data-help-id="db-query-clear"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>

                            <div className="p-4 space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div className="md:col-span-1 space-y-2 flex flex-col h-[400px]">
                                        <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Presets</label>
                                        <div className="flex flex-col gap-2 overflow-y-auto pr-2 custom-scrollbar flex-1">
                                            {presets.map((p, i) => (
                                                <button
                                                    key={i}
                                                    onClick={() => setSql(p.sql)}
                                                    className="text-left text-[16px] p-3 rounded hover:bg-gray-100 text-gray-700 border border-transparent hover:border-gray-200 transition-all font-bold whitespace-normal flex-shrink-0 leading-tight"
                                                >
                                                    {p.label}
                                                </button>
                                            ))}
                                        </div>
                                    </div>

                                    <form onSubmit={handleExecute} className="md:col-span-3 space-y-3">
                                        <textarea
                                            value={sql}
                                            onChange={(e) => setSql(e.target.value)}
                                            placeholder="SELECT * FROM items LIMIT 10..."
                                            className="w-full h-32 p-3 font-mono text-sm border rounded-lg focus:ring-2 focus:ring-[var(--brand-primary)]/20 focus:border-[var(--brand-primary)]/20 bg-gray-900 text-[var(--brand-accent)]/80"
                                        />
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2 text-[10px] text-gray-400 italic font-medium">
                                                Only SELECT, SHOW, DESCRIBE allowed.
                                            </div>
                                            <button
                                                type="submit"
                                                disabled={isLoading || !sql.trim()}
                                                className="btn-wf px-6 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest disabled:opacity-50"
                                            >
                                                {isLoading ? 'Running...' : 'Run Query'}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        {error && (
                            <div className="p-4 bg-[var(--brand-error-bg)] border border-[var(--brand-error-border)] text-[var(--brand-error)] text-sm rounded-lg flex items-center gap-3">
                                <span className="text-xl">⚠️</span> {error}
                            </div>
                        )}

                        {results && (
                            <div className="bg-white border rounded-xl shadow-sm overflow-hidden flex flex-col">
                                <div className="px-4 py-3 bg-gray-50 border-b flex items-center justify-between text-xs">
                                    <span className="font-bold text-gray-700">Results: <span className="text-gray-500">{results.rowCount} rows</span></span>
                                    {results.executionTime && <span className="text-gray-400 font-mono">{results.executionTime}ms</span>}
                                </div>
                                <div className="overflow-x-auto max-h-[500px]">
                                    <table className="w-full text-xs text-left">
                                        <thead className="bg-gray-50 text-gray-600 font-bold border-b sticky top-0">
                                            <tr>
                                                {results.columns.map((col, i) => (
                                                    <th key={i} className="px-4 py-2 border-r last:border-0 uppercase tracking-tighter">{col}</th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100 font-mono">
                                            {results.rows.map((row, i) => (
                                                <tr key={i} className="hover:bg-gray-50">
                                                    {results.columns.map((col, j) => (
                                                        <td key={j} className="px-4 py-2 border-r last:border-0 truncate max-w-[200px]" title={String(row[col])}>
                                                            {row[col] === null ? <span className="text-gray-300 italic">NULL</span> : String(row[col])}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
