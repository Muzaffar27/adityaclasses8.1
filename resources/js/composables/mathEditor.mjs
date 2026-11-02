export const mathNumber = value => ({ type: 'number', value });
export function mathNodeAt(math, path) { return path.reduce((node, key) => node?.[key], math); }
export function replaceMathNode(math, path, replacement) {
    if (!path.length) return replacement;
    const [key, ...rest] = path;
    if (math?.[key] === undefined) throw new Error('Choose a part of the formula first.');
    const copy = Array.isArray(math) ? [...math] : { ...math };
    copy[key] = replaceMathNode(math[key], rest, replacement);
    return copy;
}
export function wrapMathNode(math, path, type) {
    const node = mathNodeAt(math, path);
    if (!node || node.type === 'operator') return math;
    const replacements = {
        fraction: () => ({ type, numerator: node, denominator: mathNumber('1') }),
        power: () => ({ type, base: node, exponent: mathNumber('2') }),
        root: () => ({ type, radicand: node, index: null }),
    };
    return replacements[type] ? replaceMathNode(math, path, replacements[type]()) : math;
}
export function addMathTerm(math, path) {
    const node = mathNodeAt(math, path);
    if (!node) return math;
    const extra = [{ type: 'operator', value: '+' }, mathNumber('1')];
    if (node.type === 'expression') return replaceMathNode(math, path, { ...node, children: [...node.children, ...extra] });
    if (path.at(-2) === 'children') return addMathTerm(math, path.slice(0, -2));
    return replaceMathNode(math, path, { type: 'expression', children: [node, ...extra] });
}
export function canRemoveMathNode(math, path) {
    if (path.at(-1) === 'index') return mathNodeAt(math, path.slice(0, -1))?.type === 'root';
    return path.at(-2) === 'children' && mathNodeAt(math, path.slice(0, -2))?.children?.length > 1;
}
export function removeMathNode(math, path) {
    if (!canRemoveMathNode(math, path)) return math;
    if (path.at(-1) === 'index') return replaceMathNode(math, path, null);
    const parentPath = path.slice(0, -2), parent = mathNodeAt(math, parentPath);
    return replaceMathNode(math, parentPath, { ...parent, children: parent.children.filter((_, index) => index !== path.at(-1)) });
}
export function editMathValue(node, value) {
    let type = node.type;
    if (/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/.test(value)) type = 'number';
    else if (/^\p{L}+$/u.test(value)) type = 'variable';
    else if (/^[+\-−×÷*/=<>≤≥≠():,;\[\]{}]+$/.test(value)) type = 'operator';
    return { ...node, type, value };
}
