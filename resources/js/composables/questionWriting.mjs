const superscript = { '0': '⁰', '1': '¹', '2': '²', '3': '³', '4': '⁴', '5': '⁵', '6': '⁶', '7': '⁷', '8': '⁸', '9': '⁹', '-': '⁻', '+': '⁺' };

// This is an editable text representation, not a parser or an extraction repair.
export function mathToWriting(node) {
    if (!node) return '';
    if (['number', 'variable'].includes(node.type)) return node.value;
    if (node.type === 'operator') return /^[+−\-×÷=<>≤≥≠]$/.test(node.value) ? ` ${node.value} ` : node.value;
    if (node.type === 'expression') return node.children.map(mathToWriting).join('').replace(/(\d)(and|or|to)(?=\d)/gi, '$1 $2 ').replace(/[ \t]+/g, ' ').trim();
    if (node.type === 'fraction') return `(${mathToWriting(node.numerator)})/(${mathToWriting(node.denominator)})`;
    if (node.type === 'power') {
        const base = mathToWriting(node.base), exponent = mathToWriting(node.exponent);
        const power = /^[+\-]?\d+$/.test(exponent) ? [...exponent].map(value => superscript[value]).join('') : `^(${exponent})`;
        return `${['number', 'variable'].includes(node.base.type) ? base : `(${base})`}${power}`;
    }
    if (node.type === 'root') return node.index ? `root(${mathToWriting(node.index)}, ${mathToWriting(node.radicand)})` : `√(${mathToWriting(node.radicand)})`;
    throw new Error('This formula cannot be converted to text safely.');
}

export function needsFormulaLayout(node) {
    return !node || !['number', 'variable', 'operator', 'expression'].includes(node.type)
        || (node.type === 'expression' && node.children.some(needsFormulaLayout));
}
