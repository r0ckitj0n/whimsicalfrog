/**
 * Node smoke test for loading message / product rotation (no artificial delays).
 * Run: node --experimental-strip-types scripts/test-loading-splash-rotation.mjs
 * (or via tsx if available)
 */
import assert from 'node:assert/strict';
import { LOADING_MESSAGE_PAIRS } from '../src/data/loadingMessages.ts';

// Minimal localStorage mock
const store = new Map();
globalThis.window = {
    localStorage: {
        getItem: (k) => (store.has(k) ? store.get(k) : null),
        setItem: (k, v) => {
            store.set(k, String(v));
        },
        removeItem: (k) => {
            store.delete(k);
        }
    }
};

const { selectNextLoadingMessage } = await import('../src/utils/loadingMessageRotation.ts');
const { selectFeaturedProduct } = await import('../src/utils/loadingProductRotation.ts');

assert.equal(LOADING_MESSAGE_PAIRS.length, 50, 'expected 50 message pairs');
const ids = new Set(LOADING_MESSAGE_PAIRS.map((p) => p.id));
assert.equal(ids.size, 50, 'message ids must be unique');
for (const pair of LOADING_MESSAGE_PAIRS) {
    assert.ok(pair.line1 && pair.line1.length > 0, `line1 missing for id ${pair.id}`);
    assert.ok(pair.line2 && pair.line2.length > 0, `line2 missing for id ${pair.id}`);
}

const seen = [];
for (let i = 0; i < 50; i++) {
    const msg = selectNextLoadingMessage();
    assert.ok(!seen.includes(msg.id), `duplicate message id ${msg.id} within cycle`);
    seen.push(msg.id);
}
assert.equal(seen.length, 50);

const lastOfFirstCycle = seen[49];
const firstOfSecondCycle = selectNextLoadingMessage().id;
assert.notEqual(
    firstOfSecondCycle,
    lastOfFirstCycle,
    'new cycle must not start with previous cycle final message'
);

const secondCycleRest = new Set([firstOfSecondCycle]);
for (let i = 1; i < 50; i++) {
    secondCycleRest.add(selectNextLoadingMessage().id);
}
assert.equal(secondCycleRest.size, 50, 'second cycle should cover all 50 ids');

const products = [
    { sku: 'A', item_name: 'A', price: 1, stock: 1 },
    { sku: 'B', item_name: 'B', price: 1, stock: 1 },
    { sku: 'C', item_name: 'C', price: 1, stock: 1 }
];
const productSeen = [];
for (let i = 0; i < 3; i++) {
    const p = selectFeaturedProduct(products);
    assert.ok(p);
    assert.ok(!productSeen.includes(p.sku), `duplicate product ${p.sku} within cycle`);
    productSeen.push(p.sku);
}
assert.equal(productSeen.length, 3);

console.log('loading splash rotation smoke tests passed');
