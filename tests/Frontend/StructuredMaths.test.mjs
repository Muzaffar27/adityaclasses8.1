import test from 'node:test';
import assert from 'node:assert/strict';
import { transcribeMath, mathMarkup, renderStructuredTest } from '../../scripts/questions/structured-maths.mjs';

const glyph = (text, x, y = 100, height = 14, width = text.length * 7) => ({ text, x, y, height, width });
test('arithmetic becomes structured operators and numbers without raster content', () => {
    const result = transcribeMath([glyph('18 ÷ 3 – 7 + 2 × 5', 50)], '(n)');
    assert.equal(result.children.map(n => n.value).join(''), '18÷3-7+2×5');
    assert.match(mathMarkup(result), /<mo>÷<\/mo>/);
});
test('a source label embedded in the same text run is removed before maths transcription', () => {
    const result = transcribeMath([glyph('(i) -4 - 5 × (-1)', 50)], '(i)');
    assert.ok(!JSON.stringify(result).includes('variable'));
    assert.equal(result.children[0].value, '-');
});
test('mixed fractions retain whole parts, numerators and denominators in source order', () => {
    const result = transcribeMath([glyph('6', 100, 100), glyph('1', 111, 90), glyph('3', 111, 110), glyph('–', 130), glyph('5', 150), glyph('5', 161, 90), glyph('6', 161, 110), glyph('=', 180)], '(c)');
    assert.deepEqual(result.children.map(n => n.type), ['number', 'fraction', 'operator', 'number', 'fraction', 'operator']);
    assert.equal(result.children[1].numerator.value, '1');
    assert.equal(result.children[4].denominator.value, '6');
    assert.match(mathMarkup(result), /<mfrac><mn>1<\/mn><mn>3<\/mn><\/mfrac>/);
});
test('cube roots keep their raised index and square roots have no fabricated index', () => {
    const cube = transcribeMath([glyph('3', 50, 93, 7, 4), glyph('√', 50, 100, 14, 8), glyph('125', 58)], '9.');
    assert.equal(cube.children[0].index.value, '3');
    assert.match(mathMarkup(cube), /<mroot><mn>125<\/mn><mn>3<\/mn><\/mroot>/);
    const square = transcribeMath([glyph('√', 50, 100, 14, 8), glyph('2025', 58)], '6.');
    assert.equal(square.children[0].index, null);
});
test('superscripts are attached to the correct base rather than flattened into digits', () => {
    const result = transcribeMath([glyph('7', 50), glyph('3', 57, 94, 7, 4), glyph('×', 70), glyph('7', 85), glyph('4', 92, 94, 7, 4)], '1.');
    assert.equal(result.children[0].type, 'power');
    assert.equal(result.children[0].base.value, '7');
    assert.equal(result.children[2].exponent.value, '4');
});
test('ambiguous or unsupported content is rejected instead of copied as a question image', () => {
    assert.throws(() => transcribeMath([glyph('Solve the diagram', 50)], '(a)'), /unsupported/);
    assert.throws(() => transcribeMath([glyph('2', 50, 100, 14), glyph('3', 100, 90, 7)], '1.'), /ambiguous_superscript/);
    assert.throws(() => transcribeMath([glyph('(2+3', 50)], '1.'), /unbalanced_parentheses/);
});
test('structured preview contains native maths and selectable text, no image or source furniture', () => {
    const html = renderStructuredTest({ count: 1, questions: [{ instructions: 'Simplify:', math: { type: 'fraction', numerator: { type: 'number', value: '1' }, denominator: { type: 'number', value: '3' } } }] });
    assert.ok(html.includes('<math') && html.includes('<mfrac>'));
    assert.ok(!html.includes('<img') && !html.includes('Source page'));
});
