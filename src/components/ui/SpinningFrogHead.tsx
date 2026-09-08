import React from 'react';
import { BRAND_ASSET } from '../../core/constants.js';
import '../../styles/components/ui/spinning-frog-head.css';

export const SpinningFrogHead: React.FC = () => (
    <div className="wf-spinning-frog-head-wrap" aria-hidden="true">
        <span className="wf-spinning-frog-head__ring" />
        <img
            className="wf-spinning-frog-head"
            src={BRAND_ASSET.FROG_HEAD}
            alt=""
            width={160}
            height={160}
            loading="eager"
            decoding="async"
        />
    </div>
);

export default SpinningFrogHead;
