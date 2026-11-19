import test from 'node:test';
import assert from 'node:assert/strict';
import { packageTerms, termPrice, itemTermPrices } from '../../resources/js/utils/packagePricing.mjs';

test('each term has its own price and percentage discount', () => {
    const pkg = { three_month_price: 1000, six_month_price: 2000, twelve_month_price: 3000,
        three_month_discount_percent: 5, six_month_discount_percent: 10, twelve_month_discount_percent: 20 };
    assert.deepEqual(packageTerms.map(term => termPrice(pkg, term).final), [950, 1800, 2400]);
    assert.deepEqual(packageTerms.map(term => term.months), [3, 6, 12]);
});

test('subject selections keep their relative shares and add up to the final price', () => {
    const pkg = { three_month_price: 100, three_month_discount_percent: 10,
        items: [{ id: 1, price: 100 }, { id: 2, price: 200 }] };
    assert.deepEqual(itemTermPrices(pkg, packageTerms[0]), { 1: 30, 2: 60 });
    pkg.items = [{ id: 1, price: 0 }, { id: 2, price: 0 }, { id: 3, price: 0 }];
    pkg.three_month_discount_percent = 0;
    assert.deepEqual(itemTermPrices(pkg, packageTerms[0]), { 1: 33.33, 2: 33.34, 3: 33.33 });
});

test('legacy zero term prices retain subject totals and full discounts stay free', () => {
    const pkg = { three_month_price: 0, items: [{ id: 1, price: 500 }] };
    assert.equal(termPrice(pkg, packageTerms[0]).final, 500);
    pkg.three_month_discount_percent = 100;
    assert.equal(termPrice(pkg, packageTerms[0]).final, 0);
    assert.deepEqual(itemTermPrices(pkg, packageTerms[0]), { 1: 0 });
});
