import test from 'node:test';
import assert from 'node:assert/strict';
import { mathToWriting, needsFormulaLayout } from '../../resources/js/composables/questionWriting.mjs';

const number = value => ({ type: 'number', value });
test('split extraction words become editable wording without changing the original maths', () => {
    const original = { type: 'expression', children: [number('32'), ...[...'and'].map(value => ({ type: 'variable', value })), number('36')] };
    assert.equal(mathToWriting(original), '32 and 36');
    assert.equal(needsFormulaLayout(original), false);
    assert.equal(original.children.length, 5);
    assert.equal(original.children[1].value, 'a');
});
test('formula text keeps grouping, denominators, powers and root indices', () => {
    const fraction = { type: 'fraction', numerator: number('1'), denominator: number('2') };
    const power = { type: 'power', base: fraction, exponent: number('-2') };
    assert.equal(mathToWriting(power), '((1)/(2))⁻²');
    assert.equal(mathToWriting({ type: 'root', radicand: number('27'), index: number('3') }), 'root(3, 27)');
    assert.equal(mathToWriting({ type: 'root', radicand: number('9'), index: null }), '√(9)');
    assert.equal(needsFormulaLayout(power), true);
});
test('word spacing does not rewrite identifiers and unsupported nodes do not silently disappear', () => {
    assert.equal(mathToWriting({ type: 'expression', children: [number('2'), { type: 'variable', value: 'candy' }, number('3')] }), '2candy3');
    assert.throws(() => mathToWriting({ type: 'unsupported' }), /cannot be converted/);
    assert.equal(needsFormulaLayout({ type: 'unsupported' }), true);
});
