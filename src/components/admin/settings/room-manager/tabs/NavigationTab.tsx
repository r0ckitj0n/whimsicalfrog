import React from 'react';
import { IRoomConnection as IConnection, IRoomHeaderLink as IHeaderLink } from '../../../../../types/index.js';

interface NavigationTabProps {
    connections: IConnection[];
    externalLinks: IConnection[];
    headerLinks: IHeaderLink[];
    isDetecting: boolean;
    onDetectConnections: () => Promise<void>;
}

export const NavigationTab: React.FC<NavigationTabProps> = ({
    connections,
    externalLinks,
    headerLinks,
    isDetecting,
    onDetectConnections
}) => {
    return (
        <div className="h-full flex flex-col min-h-0 overflow-hidden">
            <div className="p-6 flex-1 min-h-0">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 h-full">
                    {/* Left Column: External Links and Header Navigation */}
                    <div className="flex flex-col min-h-0 h-full gap-6">
                        {/* External Links */}
                        <div className="bg-white rounded-2xl border border-slate-100 overflow-hidden flex flex-col min-h-0 flex-1">
                            <div className="px-4 py-3 bg-slate-50 border-b border-slate-100 flex-shrink-0">
                                <h4 className="text-xs font-black text-slate-500 uppercase tracking-widest">🌐 External Links</h4>
                            </div>
                            <div className="overflow-y-auto flex-1 min-h-0">
                                <table className="w-full">
                                    <thead className="sticky top-0 bg-white">
                                        <tr className="border-b border-slate-100">
                                            <th className="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">From Room</th>
                                            <th className="px-4 py-3 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest w-12"></th>
                                            <th className="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">External URL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {externalLinks.map((link, idx) => (
                                            <tr key={`${link.source_room}-${link.target_url}-${link.area_selector}`} className={`border-b border-slate-50 ${idx % 2 === 0 ? 'bg-white' : 'bg-slate-25'} hover:bg-blue-50/30 transition-colors`}>
                                                <td className="px-4 py-3">
                                                    <span className="font-semibold text-sm text-slate-700">{link.source_name || link.source_room}</span>
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span className="text-blue-400">↗</span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <a href={link.target_url} target="_blank" rel="noopener noreferrer" className="text-sm text-blue-600 hover:underline truncate max-w-[200px] inline-block">
                                                        {link.target_url}
                                                    </a>
                                                </td>
                                            </tr>
                                        ))}
                                        {externalLinks.length === 0 && (
                                            <tr>
                                                <td colSpan={3} className="px-4 py-6 text-center text-slate-400 text-sm">
                                                    No external links found.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* Site Header Navigation */}
                        <div className="bg-white rounded-2xl border border-slate-100 overflow-hidden flex flex-col min-h-0 flex-1">
                            <div className="px-4 py-3 bg-slate-50 border-b border-slate-100 flex-shrink-0">
                                <h4 className="text-xs font-black text-slate-500 uppercase tracking-widest">📍 Site Header Navigation</h4>
                                <p className="text-[10px] text-slate-400 mt-1">Links accessible from every page</p>
                            </div>
                            <div className="overflow-y-auto flex-1 min-h-0">
                                <table className="w-full">
                                    <thead className="sticky top-0 bg-white">
                                        <tr className="border-b border-slate-100">
                                            <th className="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Label</th>
                                            <th className="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">URL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {headerLinks.map((link, idx) => (
                                            <tr key={link.slug} className={`border-b border-slate-50 ${idx % 2 === 0 ? 'bg-white' : 'bg-slate-25'} hover:bg-blue-50/30 transition-colors`}>
                                                <td className="px-4 py-3">
                                                    <span className="font-semibold text-sm text-slate-700">{link.label}</span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <a href={link.url} target="_blank" rel="noopener noreferrer" className="text-sm text-blue-600 hover:underline">
                                                        {link.url}
                                                    </a>
                                                </td>
                                            </tr>
                                        ))}
                                        {headerLinks.length === 0 && (
                                            <tr>
                                                <td colSpan={2} className="px-4 py-6 text-center text-slate-400 text-sm">
                                                    No header links found.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {/* Right Column: Room Connections */}
                    <div className="flex flex-col min-h-0 h-full">
                        <div className="flex items-center justify-between mb-4 flex-shrink-0">
                            <div>
                                <h3 className="text-sm font-black text-slate-600 uppercase tracking-widest">Room Connections</h3>
                                <span className="text-xs text-slate-400">{connections.length} connections</span>
                            </div>
                            <button
                                onClick={onDetectConnections}
                                disabled={isDetecting}
                                className="btn btn-secondary px-4 py-2 text-sm flex items-center gap-2"
                                data-help-id="room-detect-connections"
                            >
                                {isDetecting ? (
                                    <>⏳ Detecting...</>
                                ) : (
                                    <>🔍 Detect Connections</>
                                )}
                            </button>
                        </div>

                        <div className="bg-white rounded-2xl border border-slate-100 overflow-hidden flex-1 min-h-0 flex flex-col">
                            <div className="overflow-y-auto flex-1 min-h-0">
                                <table className="w-full">
                                    <thead className="sticky top-0 bg-slate-50">
                                        <tr className="border-b border-slate-100">
                                            <th className="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">From Room</th>
                                            <th className="px-4 py-3 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest w-12"></th>
                                            <th className="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">To Room</th>
                                            <th className="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Via</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {connections.map((conn, idx) => (
                                            <tr key={`${conn.source_room}-${conn.target_room}-${conn.area_selector}`} className={`border-b border-slate-50 ${idx % 2 === 0 ? 'bg-white' : 'bg-slate-25'} hover:bg-blue-50/30 transition-colors`}>
                                                <td className="px-4 py-3">
                                                    <span className="font-semibold text-sm text-slate-700">{conn.source_name || conn.source_room}</span>
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span className="text-slate-400">→</span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="font-semibold text-sm text-slate-700">{conn.target_name || conn.target_room}</span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="text-xs text-slate-400 font-mono">{conn.area_selector}</span>
                                                </td>
                                            </tr>
                                        ))}
                                        {connections.length === 0 && (
                                            <tr>
                                                <td colSpan={4} className="px-4 py-8 text-center text-slate-400">
                                                    No room shortcuts found. Click "Detect Connections" to scan.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};
