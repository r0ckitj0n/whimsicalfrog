import React from 'react';

interface BoundaryIndicatorsProps {
    dims: { w: number, h: number };
    headerHeight: number;
    footerHeight: number;
}

export const BoundaryIndicators: React.FC<BoundaryIndicatorsProps> = ({
    dims,
    headerHeight,
    footerHeight
}) => {
    return (
        <>
            {/* Header Boundary Indicator */}
            <svg
                className="absolute inset-0 w-full h-full pointer-events-none z-[5] overflow-visible"
                viewBox={`0 0 ${dims.w} ${dims.h}`}
                preserveAspectRatio="xMinYMin meet"
            >
                <defs>
                    <pattern id="headerHatch" patternUnits="userSpaceOnUse" width="12" height="12" patternTransform="rotate(45)">
                        <line x1="0" y1="0" x2="0" y2="12" stroke="#06b6d4" strokeWidth="2" opacity="0.3" />
                    </pattern>
                </defs>
                <rect x={4} y={4} width={dims.w - 8} height={headerHeight} fill="url(#headerHatch)" />
                <line x1={4} y1={headerHeight} x2={dims.w - 4} y2={headerHeight} stroke="#06b6d4" strokeWidth={3} strokeDasharray="12 6" strokeLinecap="round" opacity={0.9} />
                <text x={dims.w / 2} y={headerHeight / 2 + 5} fill="#06b6d4" fontSize="14" fontWeight="bold" fontFamily="system-ui, sans-serif" textAnchor="middle" opacity={0.95}>
                    ▼ HEADER AREA ▼
                </text>
            </svg>

            {/* Footer Boundary Indicator */}
            <svg
                className="absolute inset-0 w-full h-full pointer-events-none z-[5] overflow-visible"
                viewBox={`0 0 ${dims.w} ${dims.h}`}
                preserveAspectRatio="xMinYMin meet"
            >
                <defs>
                    <pattern id="footerHatch" patternUnits="userSpaceOnUse" width="12" height="12" patternTransform="rotate(45)">
                        <line x1="0" y1="0" x2="0" y2="12" stroke="#06b6d4" strokeWidth="2" opacity="0.3" />
                    </pattern>
                </defs>
                <rect x={4} y={dims.h - footerHeight} width={dims.w - 8} height={footerHeight - 4} fill="url(#footerHatch)" />
                <line x1={4} y1={dims.h - footerHeight} x2={dims.w - 4} y2={dims.h - footerHeight} stroke="#06b6d4" strokeWidth={3} strokeDasharray="12 6" strokeLinecap="round" opacity={0.9} />
                <text x={dims.w / 2} y={dims.h - footerHeight / 2 + 5} fill="#06b6d4" fontSize="14" fontWeight="bold" fontFamily="system-ui, sans-serif" textAnchor="middle" opacity={0.95}>
                    ▲ FOOTER BOUNDARY ▲
                </text>
            </svg>
        </>
    );
};
