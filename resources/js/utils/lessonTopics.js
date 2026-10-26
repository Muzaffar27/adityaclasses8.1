export function uniqueSorted(values) {
    return [...new Set(
        values
            .map(value => String(value || '').trim())
            .filter(Boolean)
    )].sort((first, second) => first.localeCompare(second, undefined, { numeric: true, sensitivity: 'base' }));
}

export function compareTopics(first, second) {
    const firstMatch = first.match(/^(\d+)/);
    const secondMatch = second.match(/^(\d+)/);
    const firstNumber = firstMatch ? parseInt(firstMatch[1], 10) : null;
    const secondNumber = secondMatch ? parseInt(secondMatch[1], 10) : null;

    if (firstNumber === null && secondNumber !== null) return -1;
    if (firstNumber !== null && secondNumber === null) return 1;
    if (firstNumber === null && secondNumber === null) return first.localeCompare(second);
    return firstNumber - secondNumber;
}
