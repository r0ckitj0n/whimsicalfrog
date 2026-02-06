import React, { useEffect, useState } from 'react';
import { useSizeColorRedesign, IRedesignItem, IRedesignAnalysis, IRedesignProposal, IProposedSize, IProposedColor } from '../../../../hooks/admin/useSizeColorRedesign.js';
import { RedesignControls } from './redesign/RedesignControls.js';
import { AnalysisCard } from './redesign/AnalysisCard.js';
import { ProposalCard } from './redesign/ProposalCard.js';
import { OutputConsole } from './redesign/OutputConsole.js';

type RedesignOutput = IRedesignProposal | IRedesignAnalysis | Record<string, unknown> | null;

interface SizeColorRedesignProps {
    onClose: () => void;
    initialSku?: string;
}

export const SizeColorRedesign: React.FC<SizeColorRedesignProps> = ({ onClose, initialSku }) => {
    const {
        isLoading,
        error: apiError,
        fetchItems,
        checkIfBackwards,
        analyzeStructure,
        proposeStructure,
        getRestructuredView,
        migrateStructure
    } = useSizeColorRedesign();

    const [items, setItems] = useState<IRedesignItem[]>([]);
    const [selectedSku, setSelectedSku] = useState(initialSku || '');
    const [analysis, setAnalysis] = useState<IRedesignAnalysis | null>(null);
    const [proposal, setProposal] = useState<IRedesignProposal | null>(null);
    const [output, setOutput] = useState<RedesignOutput>(null);
    const [preserveStock, setPreserveStock] = useState(true);
    const [dryRun, setDryRun] = useState(false);
    const [status, setStatus] = useState<{ msg: string; ok: boolean } | null>(null);

    useEffect(() => {
        const load = async () => {
            const data = await fetchItems();
            setItems(data);
            if (!initialSku) {
                const last = localStorage.getItem('wf_last_redesign_sku');
                if (last) setSelectedSku(last);
            }
        };
        load();
    }, [fetchItems, initialSku]);

    const handleAnalyze = async () => {
        if (!selectedSku) return;
        localStorage.setItem('wf_last_redesign_sku', selectedSku);
        const quick = await checkIfBackwards(selectedSku);
        if (quick) setStatus({ msg: quick.is_backwards ? 'Backwards' : 'Good', ok: !quick.is_backwards });
        const res = await analyzeStructure(selectedSku);
        if (res) {
            setAnalysis(res.analysis ?? null);
            setOutput(res as RedesignOutput);
        }
    };

    const handlePropose = async () => {
        if (!selectedSku) return;
        const res = await proposeStructure(selectedSku);
        if (res && res.proposedSizes) {
            const prop: IRedesignProposal = { success: res.success, message: res.message || '', proposedSizes: res.proposedSizes };
            setProposal(prop);
            setOutput(prop);
        }
    };

    const handleViewRestructured = async () => {
        if (!selectedSku) return;
        const res = await getRestructuredView(selectedSku);
        if (res) setOutput(res as RedesignOutput);
    };

    const handleMigrate = async () => {
        if (!selectedSku || !proposal) return;
        const newStructure: IProposedSize[] = proposal.proposedSizes.map((s: IProposedSize) => ({
            size_name: s.name || s.size_name || '',
            size_code: s.code || s.size_code || '',
            price_adjustment: s.price_adjustment || 0,
            colors: (s.colors || []).map((c: IProposedColor) => ({
                color_name: c.color_name || '',
                color_code: c.color_code || '#000000',
                stock_level: c.stock_level || 0
            }))
        }));

        const res = await migrateStructure(selectedSku, newStructure, preserveStock, dryRun);
        if (res) {
            setOutput(res as RedesignOutput);
            if (res.success && !dryRun && window.WFToast) window.WFToast.success('Migration completed successfully');
        }
    };

    return (
        <div className="admin-modal-overlay show flex items-center justify-center p-4 md:p-10 z-[1000]">
            <div className="admin-modal admin-modal-content show bg-white rounded-3xl shadow-2xl w-full max-w-6xl h-full max-h-[90vh] flex flex-col overflow-hidden border border-slate-100 animate-in zoom-in-95 duration-300">
                <div className="modal-header flex items-center justify-between px-8 py-6 border-b border-slate-100 sticky top-0 bg-white/80 backdrop-blur-md z-10">
                    <div className="flex items-center gap-4">
                        <div className="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-2xl">🧩</div>
                        <div>
                            <h2 className="text-xl font-black text-slate-800 uppercase tracking-tight">Size/Color Redesign</h2>
                            <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Attribute System Optimization</p>
                        </div>
                    </div>
                    <button onClick={onClose} className="admin-action-btn btn-icon--close">
                        <span className="sr-only">Close</span>
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto p-8 space-y-8 bg-slate-50/30">
                    <RedesignControls items={items} selectedSku={selectedSku} onSkuChange={setSelectedSku} onAnalyze={handleAnalyze} onPropose={handlePropose} onViewLive={handleViewRestructured} isLoading={isLoading} status={status} />

                    {apiError && <div className="p-4 bg-red-50 border border-red-100 text-red-600 rounded-2xl text-xs font-bold flex items-center gap-3 animate-in fade-in"><span className="text-xl">⚠️</span> {apiError}</div>}

                    <div className="grid lg:grid-cols-2 gap-8">
                        <AnalysisCard analysis={analysis} />
                        <ProposalCard proposal={proposal} preserveStock={preserveStock} setPreserveStock={setPreserveStock} dryRun={dryRun} setDryRun={setDryRun} onMigrate={handleMigrate} isLoading={isLoading} />
                    </div>

                    <OutputConsole output={output} onClear={() => setOutput(null)} />
                </div>
            </div>
        </div>
    );
};

export default SizeColorRedesign;
