import React, { useState } from 'react';

export const SearchBar: React.FC = () => {
    const [query, setQuery] = useState('');

    const handleSubmit = (e: React.FormEvent) => {
        // For now, let it perform a standard GET to /shop?q=...
    };

    return (
        <div className="search-bar-container relative block">
            <form action="/shop" method="GET" role="search" onSubmit={handleSubmit} className="relative flex items-center justify-center w-full">
                <div className="relative w-full flex items-center">
                    <input
                        type="text"
                        name="q"
                        placeholder="Search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        className="search-input w-full h-[48px] bg-transparent border-2 border-[var(--brand-primary)] rounded-[30px] px-4 text-[var(--brand-primary)] placeholder-[var(--brand-primary)] font-title-primary italic text-lg focus:outline-none transition-all"
                    />
                </div>
            </form>
        </div>
    );
};
