export const packageTerms = [
    { months: 3, label: '1 Term (3 months)', key: 'three_month' },
    { months: 6, label: '2 Terms (6 months)', key: 'six_month' },
    { months: 12, label: '3 Terms (12 months)', key: 'twelve_month' },
];

function discountPrice(original, discount) {
    discount = Math.min(100, Math.max(0, Number(discount || 0)));
    original = Number(original || 0);
    const grossCents = Math.round(original * 100);
    const discountBasisPoints = Math.round(discount * 100);
    return { original, discount, final: Math.floor((grossCents * (10000 - discountBasisPoints) + 5000) / 10000) / 100 };
}

function legacyTermPrice(pkg, term) {
    const fallback = (pkg.items || []).reduce((sum, item) => sum + Number(item.price || 0), 0);
    const entered = Number(pkg[term.key + '_price'] || 0);
    return discountPrice(entered > 0 ? entered : fallback, pkg[term.key + '_discount_percent']);
}

function allocate(items, amount) {
    const weight = items.reduce((sum, item) => sum + Number(item.price || 0), 0);
    const cents = Math.round(amount * 100);
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

export function itemTermDetails(pkg, term) {
    const items = [...(pkg.items || [])].sort((a, b) => a.id - b.id);
    const legacy = legacyTermPrice(pkg, term);
    const originals = allocate(items, legacy.original);
    const finals = allocate(items, legacy.final);
    return Object.fromEntries(items.map(item => [item.id, item[term.key + '_price'] != null
        ? discountPrice(item[term.key + '_price'], item[term.key + '_discount_percent'])
        : { original: originals[item.id], final: finals[item.id], discount: legacy.discount }]));
}

export function subjectTermPrice(pkg, item, term) {
    return itemTermDetails(pkg, term)[item.id] || discountPrice(item[term.key + '_price'], item[term.key + '_discount_percent']);
}

export function termPrice(pkg, term) {
    if (!(pkg.items || []).some(item => item[term.key + '_price'] != null)) return legacyTermPrice(pkg, term);
    const details = Object.values(itemTermDetails(pkg, term));
    const sum = key => Math.round(details.reduce((total, price) => total + price[key], 0) * 100) / 100;
    return { original: sum('original'), final: sum('final'),
        discount: details.every(price => price.discount === details[0].discount) ? details[0].discount : 0 };
}

export function itemTermPrices(pkg, term) {
    return Object.fromEntries(Object.entries(itemTermDetails(pkg, term)).map(([id, price]) => [id, price.final]));
}
