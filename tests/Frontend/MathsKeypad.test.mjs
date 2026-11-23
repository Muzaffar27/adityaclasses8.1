import test from 'node:test';
import assert from 'node:assert/strict';
import { editMathsEntry, mathsKeys } from '../../resources/js/utils/mathsKeypad.mjs';

test('keypad inserts and replaces at the selected cursor position', () => {
    assert.deepEqual(editMathsEntry('34', 1, 1, '/'), { value: '3/4', caret: 2 });
    assert.deepEqual(editMathsEntry('2+3', 1, 2, '×'), { value: '2×3', caret: 2 });
    assert.deepEqual(editMathsEntry('', 0, 0, '√('), { value: '√(', caret: 2 });
});
test('delete respects selected ranges and whole Unicode characters', () => {
    assert.deepEqual(editMathsEntry('2𝑥', 3, 3, 'backspace'), { value: '2', caret: 1 });
    assert.deepEqual(editMathsEntry('1234', 1, 3, 'backspace'), { value: '14', caret: 1 });
    assert.deepEqual(editMathsEntry('', 0, 0, 'backspace'), { value: '', caret: 0 });
});
test('length limits preserve existing work and symbols have visible labels', () => {
    assert.equal(editMathsEntry('1234', 4, 4, '5', 4).limitReached, true);
    for (const symbol of ['/', '^', '×', '÷', '√']) assert.ok(mathsKeys.some(key => key.symbol === symbol && key.label.length > 2));
    for (const symbol of ['%', 'π', '°']) assert.ok(mathsKeys.some(key => key.symbol === symbol && key.label));
});
