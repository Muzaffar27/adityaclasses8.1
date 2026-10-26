const normalise = value => value.normalize('NFKC').replace(/[–−]/g, '-');
const escape = value => String(value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

// Coordinate-aware transcription for supported digital-PDF maths. No image is used as question content.
export function transcribeMath(items, label) {
    let glyphs = items.filter(i => i.text.trim()).map(i => ({ ...i, text: normalise(i.text.trim()) }));
    const left = [...glyphs].sort((a, b) => a.x - b.x || a.y - b.y);
    if (label && left[0]?.text.startsWith(label) && left[0].text.length > label.length) {
        const first = left[0], offset = first.width * label.length / first.text.length;
        first.text = first.text.slice(label.length).trim(); first.x += offset; first.width -= offset;
    }
    let prefix = '';
    for (let i = 0; i < Math.min(4, left.length); i++) {
        prefix += left[i].text;
        if (prefix === label) { const remove = new Set(left.slice(0, i + 1)); glyphs = glyphs.filter(g => !remove.has(g)); break; }
    }
    if (!glyphs.length) throw new Error('empty_question');
    const tokens = [];
    for (const glyph of glyphs) {
        const text = glyph.text.replace(/\s+/g, '');
        const matches = [...text.matchAll(/\d+(?:\.\d+)?|[a-z]|[+\-×÷=()√]/g)];
        if (matches.map(m => m[0]).join('') !== text) throw new Error('unsupported_text_or_symbol');
        for (const match of matches) tokens.push({ ...glyph, text: match[0], x: glyph.x + glyph.width * match.index / text.length, width: glyph.width * match[0].length / text.length });
    }
    const maxHeight = Math.max(...tokens.map(t => t.height));
    let units = tokens.map(t => ({ ...t, node: { type: /^\d/.test(t.text) ? 'number' : /^[a-z]$/.test(t.text) ? 'variable' : 'operator', value: t.text }, consumed: 1 }));
    // PDF exporters often split a long integer into groups such as "1 000 000".
    units.sort((a, b) => a.x - b.x);
    for (let i = 0; i < units.length - 1; i++) {
        const a = units[i], b = units[i + 1];
        if (a.node.type === 'number' && b.node.type === 'number' && Math.abs(a.y - b.y) < 2 && Math.abs(a.height - b.height) < 2 && b.x - (a.x + a.width) >= -1 && b.x - (a.x + a.width) < 5) {
            a.node.value += b.node.value; a.width = b.x + b.width - a.x; a.consumed += b.consumed; units.splice(i + 1, 1); i--;
        }
    }
    // Pair aligned numerator/denominator glyphs; retain a structured fraction, not flattened digits.
    const used = new Set(), fractions = [];
    for (const top of units) {
        if (used.has(top) || !['number', 'variable'].includes(top.node.type)) continue;
        const candidates = units.filter(bottom => !used.has(bottom) && bottom !== top && ['number', 'variable'].includes(bottom.node.type)
            && bottom.y - top.y > top.height * 0.8 && bottom.y - top.y < top.height * 2.8
            && Math.abs(bottom.height - top.height) < 2 && Math.abs(bottom.x + bottom.width / 2 - top.x - top.width / 2) < 3);
        if (candidates.length > 1) throw new Error('ambiguous_fraction');
        if (!candidates.length) continue;
        const bottom = candidates[0]; used.add(top); used.add(bottom);
        fractions.push({ x: Math.min(top.x, bottom.x), y: (top.y + bottom.y) / 2, width: Math.max(top.x + top.width, bottom.x + bottom.width) - Math.min(top.x, bottom.x), height: top.height, consumed: top.consumed + bottom.consumed, node: { type: 'fraction', numerator: top.node, denominator: bottom.node } });
    }
    units = [...units.filter(u => !used.has(u)), ...fractions].sort((a, b) => a.x - b.x || a.y - b.y);
    for (const root of [...units].filter(u => u.node.value === '√')) {
        const radicand = units.find(u => u !== root && ['number', 'variable'].includes(u.node.type) && u.x >= root.x && u.x - root.x - root.width < 7 && Math.abs(u.y - root.y) < 3);
        if (!radicand) throw new Error('unsupported_root');
        const index = units.find(u => u !== root && u !== radicand && u.node.type === 'number' && u.height < root.height * 0.8 && Math.abs(u.x - root.x) < 5 && u.y < root.y - 2);
        root.node = { type: 'root', radicand: radicand.node, index: index?.node || null };
        root.width = radicand.x + radicand.width - root.x; root.consumed += radicand.consumed + (index?.consumed || 0);
        units = units.filter(u => u !== radicand && u !== index);
    }
    for (const exponent of [...units].filter(u => u.height < maxHeight * 0.85)) {
        const bases = units.filter(base => base !== exponent && ['number', 'variable', 'root'].includes(base.node.type) && base.height > exponent.height * 1.15 && exponent.y < base.y - 2 && exponent.x >= base.x + base.width - 3 && exponent.x <= base.x + base.width + 10);
        if (bases.length !== 1) throw new Error('ambiguous_superscript');
        const base = bases[0]; base.node = { type: 'power', base: base.node, exponent: exponent.node }; base.consumed += exponent.consumed; base.width = exponent.x + exponent.width - base.x;
        units = units.filter(u => u !== exponent);
    }
    units.sort((a, b) => a.x - b.x);
    const baseline = units[0]?.y;
    if (units.some(u => Math.abs(u.y - baseline) > 5)) throw new Error('unsupported_multiline_maths');
    if (units.reduce((sum, u) => sum + u.consumed, 0) !== tokens.length) throw new Error('unconsumed_symbols');
    let depth = 0;
    for (const u of units) { if (u.node.value === '(') depth++; if (u.node.value === ')') depth--; if (depth < 0) throw new Error('unbalanced_parentheses'); }
    if (depth) throw new Error('unbalanced_parentheses');
    return { type: 'expression', children: units.map(u => u.node) };
}

export function mathMarkup(node) {
    switch (node.type) {
        case 'expression': return `<mrow>${node.children.map(mathMarkup).join('')}</mrow>`;
        case 'number': return `<mn>${escape(node.value)}</mn>`;
        case 'variable': return `<mi>${escape(node.value)}</mi>`;
        case 'operator': return `<mo>${escape(node.value)}</mo>`;
        case 'fraction': return `<mfrac>${mathMarkup(node.numerator)}${mathMarkup(node.denominator)}</mfrac>`;
        case 'power': return `<msup>${mathMarkup(node.base)}${mathMarkup(node.exponent)}</msup>`;
        case 'root': return node.index ? `<mroot>${mathMarkup(node.radicand)}${mathMarkup(node.index)}</mroot>` : `<msqrt>${mathMarkup(node.radicand)}</msqrt>`;
        default: throw new Error('Unknown maths structure.');
    }
}

export function renderStructuredTest(test) {
    return `<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" href="data:,"><title>General test · structured questions</title><style>*{box-sizing:border-box}body{margin:0;background:#eef2f8;color:#18233b;font:18px/1.6 system-ui}main{max-width:820px;margin:auto;padding:24px}h1{font-size:1.65rem;margin:0}header p{margin:4px 0 24px;color:#56647b}article{background:white;padding:24px;margin:18px 0;border-radius:14px;break-inside:avoid}h2{font-size:1rem;margin:0 0 18px}.maths{overflow-x:auto;padding:10px 2px}math{font-size:1.55rem}p{white-space:pre-line;margin:0 0 14px}@media(max-width:480px){main{padding:14px}article{padding:18px}math{font-size:1.4rem}}@media print{body{background:white}main{padding:0}article{border:1px solid #ddd}}</style><main><header><h1>General test</h1><p>${test.count} questions</p></header>${test.questions.map((q, i) => `<article><h2>Question ${i + 1}</h2>${q.instructions ? `<p>${escape(q.instructions)}</p>` : ''}${q.text ? `<p>${escape(q.text)}</p>` : `<div class="maths"><math xmlns="http://www.w3.org/1998/Math/MathML" display="block">${mathMarkup(q.math)}</math></div>`}</article>`).join('')}</main></html>`;
}
