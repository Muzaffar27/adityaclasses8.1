import test from 'node:test';
import assert from 'node:assert/strict';
import { addMathTerm, editMathValue, mathNodeAt, mathNumber, removeMathNode, replaceMathNode, wrapMathNode } from '../../resources/js/composables/mathEditor.mjs';

const fraction = () => ({ type: 'fraction', numerator: mathNumber('1'), denominator: mathNumber('2') });
test('editing a nested denominator preserves the surrounding expression and original version', () => {
    const original = { type: 'expression', children: [fraction(), { type: 'operator', value: '+' }, { type: 'power', base: mathNumber('3'), exponent: mathNumber('2') }] };
    const changed = replaceMathNode(original, ['children', 0, 'denominator'], mathNumber('7'));
    assert.equal(mathNodeAt(changed, ['children', 0, 'denominator']).value, '7');
    assert.equal(original.children[0].denominator.value, '2');
    assert.deepEqual(changed.children[2], original.children[2]);
    assert.throws(() => replaceMathNode(original, ['missing'], mathNumber('4')), /Choose a part/);
});
test('fraction, power and root tools retain selected content instead of clearing it', () => {
    const original = fraction();
    const squared = wrapMathNode(original, ['numerator'], 'power');
    assert.deepEqual(squared.numerator, { type: 'power', base: mathNumber('1'), exponent: mathNumber('2') });
    assert.equal(original.numerator.type, 'number');
    assert.deepEqual(wrapMathNode(original, [], 'root'), { type: 'root', radicand: original, index: null });
    assert.deepEqual(wrapMathNode(mathNumber('5'), [], 'fraction'), { type: 'fraction', numerator: mathNumber('5'), denominator: mathNumber('1') });
    const sign = { type: 'operator', value: '+' };
    assert.equal(wrapMathNode(sign, [], 'power'), sign);
});
test('adding a term uses the selected expression without losing fractions or root indices', () => {
    const original = { type: 'root', index: mathNumber('3'), radicand: fraction() };
    const changed = addMathTerm(original, ['radicand', 'denominator']);
    assert.deepEqual(changed.index, original.index);
    assert.deepEqual(changed.radicand.numerator, original.radicand.numerator);
    assert.deepEqual(changed.radicand.denominator.children, [mathNumber('2'), { type: 'operator', value: '+' }, mathNumber('1')]);
    const expression = addMathTerm(mathNumber('2'), []);
    assert.equal(addMathTerm(expression, ['children', 0]).children.length, 5);
});
test('direct value editing distinguishes numbers, letters and signs while preserving partial typing', () => {
    assert.deepEqual(editMathValue(mathNumber('2'), 'x'), { type: 'variable', value: 'x' });
    assert.deepEqual(editMathValue({ type: 'variable', value: 'x' }, '-2.5'), mathNumber('-2.5'));
    assert.deepEqual(editMathValue(mathNumber('2'), '÷'), { type: 'operator', value: '÷' });
    assert.deepEqual(editMathValue(mathNumber('2'), ''), mathNumber(''));
});
test('remove can drop an expression part or optional root index without breaking required fields', () => {
    const original = { type: 'expression', children: [mathNumber('2'), mathNumber('3')] };
    assert.deepEqual(removeMathNode(original, ['children', 0]).children, [mathNumber('3')]);
    assert.equal(original.children.length, 2);
    const single = { type: 'expression', children: [mathNumber('2')] };
    assert.equal(removeMathNode(single, ['children', 0]), single);
    const root = { type: 'root', radicand: fraction(), index: mathNumber('3') };
    assert.equal(removeMathNode(root, ['index']).index, null);
    assert.deepEqual(removeMathNode(root, ['radicand', 'denominator']), root);
});
