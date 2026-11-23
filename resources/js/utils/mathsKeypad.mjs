export const numberKeys = ['7', '8', '9', '4', '5', '6', '1', '2', '3', '0', '.', 'backspace'];
export const mathsKeys = [
    { label: 'Fraction', symbol: '/', value: '/' }, { label: 'Power', symbol: '^', value: '^' },
    { label: 'Add', symbol: '+', value: '+' }, { label: 'Subtract', symbol: '−', value: '−' },
    { label: 'Multiply', symbol: '×', value: '×' }, { label: 'Divide', symbol: '÷', value: '÷' },
    { label: 'Open', symbol: '(', value: '(' }, { label: 'Close', symbol: ')', value: ')' },
    { label: 'Root', symbol: '√', value: '√(' }, { label: 'Equals', symbol: '=', value: '=' },
    { label: 'Variable', symbol: 'x', value: 'x' }, { label: 'Space', symbol: '␣', value: ' ' },
    { label: 'Percent', symbol: '%', value: '%' }, { label: 'Pi', symbol: 'π', value: 'π' }, { label: 'Degrees', symbol: '°', value: '°' },
];
export function editMathsEntry(value, start, end, key, maximum = 1000) {
    start = Math.max(0, Math.min(value.length, start ?? value.length));
    end = Math.max(start, Math.min(value.length, end ?? start));
    if (key === 'backspace' && start === end && start > 0) start -= [...value.slice(0, start)].at(-1).length;
    const insertion = key === 'backspace' ? '' : key;
    const next = value.slice(0, start) + insertion + value.slice(end);
    return next.length <= maximum ? { value: next, caret: start + insertion.length } : { value, caret: end, limitReached: true };
}
