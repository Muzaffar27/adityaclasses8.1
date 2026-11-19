export const packageTerms = [
    { months: 3, label: '1 Term (3 months)', key: 'three_month' },
    { months: 6, label: '2 Terms (6 months)', key: 'six_month' },
    { months: 12, label: '3 Terms (12 months)', key: 'twelve_month' },
];

export function termPrice(pkg, term) {
    const fallback = (pkg.items || []).reduce((sum, item) => sum + Number(item.price || 0), 0);
    const entered = Number(pkg[`${term.key}_price`] || 0);
    const original = entered > 0 ? entered : fallback;
    const discount = Math.min(100, Math.max(0, Number(pkg[`${term.key}_discount_percent`] || 0)));
    return { original, discount, final: Math.round(original * (1 - discount / 100) * 100) / 100 };
}

export function itemTermPrices(pkg, term) {
    const items = [...(pkg.items || [])].sort((a, b) => a.id - b.id);
    const weight = items.reduce((sum, item) => sum + Number(item.price || 0), 0);
    const cents = Math.round(termPrice(pkg, term).final * 100);
    let accumulated = 0;
    let allocated = 0;
    return Object.fromEntries(items.map(item => {
        accumulated += weight > 0 ? Number(item.price || 0) / weight : 1 / items.length;
        const next = Math.round(cents * accumulated);
        const price = (next - allocated) / 100;
        allocated = next;
        return [item.id, price];
    }));
}
